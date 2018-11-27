<?php

namespace Julibo\Msfoole;

use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Websocket\Server as Websocket;
use Swoole\WebSocket\Frame as Webframe;
use Julibo\Msfoole\Facade\Config;
use Julibo\Msfoole\Facade\Cookie;

class Application
{
    // 开始时间和内存占用
    private $beginTime;
    private $beginMem;
    /**
     * @var
     */
    private $websocketFrame;

    /**
     * swoole服务
     * @var
     */
    public $swoole;

    /**
     * websocket内存表
     * @var
     */
    public $table;

    /**
     *  全局缓存
     * @var
     */
    public $cache;

    /**
     * websocket request
     * @var
     */
    private $request;

    /**
     * http请求
     * @var
     */
    private $httpRequest;
    /**
     * http应答
     * @var
     */
    private $httpResponse;

    public static $error = [
        'CON_EXCEPTION' => ['code' => 10000, 'msg' => '连接异常'],
        'SIGN_EXCEPTION' => ['code' => 10001, 'msg' => '签名异常'],
    ];

    /**
     * 初始化
     */
    public function initialize()
    {
        $this->beginTime = microtime(true);
        $this->beginMem  = memory_get_usage();
    }

    /**
     * 处理http请求
     * @param SwooleRequest $request
     * @param SwooleResponse $response
     */
    public function swooleHttp(SwooleRequest $request, SwooleResponse $response)
    {
        try {
            ob_start();
            $this->httpRequest = new HttpRequest($request);
            $this->httpResponse = new Response($response);
            Cookie::init($this->httpRequest, $this->httpResponse, $this->cache);
            $this->working();
        } catch (\Throwable $e) {
            echo  json_encode(['code'=>$e->getCode(), 'msg'=>$e->getMessage(), 'data'=>['file'=>$e->getFile(), 'line'=>$e->getLine()]]);
        }
        $content = ob_get_clean();
        $this->httpResponse->end($content);
    }

    /**
     * 运行请求
     */
    private function working()
    {
        $controller = Loader::factory($this->httpRequest->controller, $this->httpRequest->namespace, $this->httpRequest);
        $method = $this->httpRequest->action;
        $data = $controller->$method();
        $result = ['code' => 0, 'msg' => '', 'data' => $data];
        echo json_encode($result);
    }

    /**
     * webSocket连接开启
     * @param Websocket $server
     * @param SwooleRequest $request
     */
    public function swooleWebSocketOpen(Websocket $server, SwooleRequest $request)
    {
        $this->request = WebSocketRequest::getInstance($request);
        $token = $this->request->getQuery('token');
        $cardno = $this->request->getQuery('cardno');
        $timestamp = $this->request->getQuery('timestamp');
        $sign = $this->request->getQuery('sign');

        if ($token && $cardno && $timestamp && $sign) {
            $token = $this->getToken($token, $cardno, $timestamp, $sign);
        }
        if ($token === false)
            $server->disconnect($this->request->getFd(), self::$error['CON_EXCEPTION']['code'], self::$error['CON_EXCEPTION']['msg']);
        else
            $server->push($this->request->getFd(), $token);

        $userInfo = ['cardno' => $cardno];
        // 创建内存表记录
        $this->table->set($this->request->getFd(), ['token' => $token, 'counter' => 0, 'create_time' => $timestamp, 'last_time'=>$timestamp, 'user_info'=>$userInfo]);
        // 将请求保存到内存表后销毁request记录
        $this->destroyRequest();
    }

    /**
     * 生成websocket TOKEN
     * @param $wvi
     * @param $cardno
     * @param $timestamp
     * @param $sign
     * @return bool|mixed|string
     */
    private function getToken($wvi, $cardno, $timestamp, $sign)
    {
        if ($timestamp + 600 < time() ||  $timestamp - 600 > time() || Config::get('msfoole.websocket.vi') != $wvi) {
            return false;
        }
        $pass = base64_encode(openssl_encrypt($cardno.$timestamp,"AES-128-CBC", Config::get('msfoole.websocket.key'),OPENSSL_RAW_DATA, $wvi));
        if ($pass != $sign) {
            return false;
        }
        $token = Helper::guid();
        return $token;
    }

    /**
     * 销毁请求request
     */
    private function destroyRequest()
    {
        WebSocketRequest::destroy($this->request);
        unset($this->request);
    }

    /**
     * 处理websocket请求
     * @param Websocket $server
     * @param Webframe $frame
     */
    public function swooleWebSocket(Websocket $server, Webframe $frame)
    {
        try {
            $this->websocketFrame = WebSocketFrame::getInstance($server, $frame);
            // 解析并验证请求
            $checkResult = $this->explainMessage($this->websocketFrame->getData());
            if ($checkResult === false) {
                $this->websocketFrame->disconnect($frame->fd, self::$error['SIGN_EXCEPTION']['code'], self::$error['SIGN_EXCEPTION']['msg']);
            }
            $result = $this->runing($checkResult);
            $data = ['code'=>0, 'msg'=>'', 'data'=>$result];
            $this->websocketFrame->sendToClient($frame->fd, $data);
            WebSocketFrame::destroy();
        } catch (\Throwable $e) {
            var_dump($e->getMessage(), $e->getFile(), $e->getLine());
        }
    }

    /**
     * 解析并验证请求
     * @param array $data
     * @return array|bool
     */
    private function explainMessage(array $data)
    {
        $user = $this->table->get($this->websocketFrame->getFd());
        if (empty($user) || empty($data['data']) || empty($data['token']) || empty($data['timestamp']) || empty($data['sign'])) {
            return false;
        }
        if ($user['token'] != $data['token']) {
            return false;
        }

        if ($data['timestamp'] + 600 < time() ||  $data['timestamp'] - 600 > time()) {
            return false;
        }
        $vi = substr($data['token'], 0, 16);
        $checkSign = base64_encode(openssl_encrypt(json_encode($data['data']),"AES-128-CBC", Config::get('msfoole.websocket.key'),OPENSSL_RAW_DATA, $vi));
        if ($data['sign'] != $checkSign) {
            return false;
        }
        $req = json_decode($data['data'], true);
        if (empty($req['timestamp']) || $req['timestamp'] != $data['timestamp']) {
            return false;
        }

        return [
            'module' => $req['module'] ?? Config::get('application.default.controller'),
            'method' => $req['method'] ?? Config::get('application.default.action'),
            'arguments' => $req['arguments'] ?? [],
            'user' => $user
        ];
    }

    /**
     * 业务逻辑运行
     * @param array $args
     * @return mixed
     */
    private function runing(array $args)
    {
        $controller = Loader::factory($args['module'], CONTROLLER_NAMESPACE . 'Robot\\');
        $controller->init($args['user'], $args['arguments']);
        $result = $controller->$args['method']($args['arguments']);
        return $result;
    }

}
