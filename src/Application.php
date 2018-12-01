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
        'AUTH_FAILED' => ['code' => 10000, 'msg' => '认证失败'],
        'CON_EXCEPTION' => ['code' => 10001, 'msg' => '连接异常'],
        'SIGN_EXCEPTION' => ['code' => 10002, 'msg' => '签名异常'],
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
        $controller = Loader::factory($this->httpRequest->controller, $this->httpRequest->namespace);
        $controller->initHttpRequest($this->httpRequest);
        $method = $this->httpRequest->action;
        $data = $controller->$method();
        if (Config::get('application.allow.output') && in_array($data, Config::get('application.allow.output'))) {
            echo $data;
        } else {
            $result = ['code' => 0, 'msg' => '', 'data' => $data];
            echo json_encode($result);
        }
    }

    /**
     * webSocket连接开启
     * @param Websocket $server
     * @param SwooleRequest $request
     */
    public function swooleWebSocketOpen(Websocket $server, SwooleRequest $request)
    {
        try {
            $this->request = new HttpRequest($request);
            $params = $this->request->getQuery();
            $authClass = Config::get('msfoole.websocket.login_class');
            $authAction = Config::get('msfoole.websocket.login_action');
            $authObject = new $authClass;
            $user = call_user_func_array([$authObject, $authAction], [$params]);
            if (empty($user)) {
                $server->disconnect($request->fd, self::$error['AUTH_FAILED']['code'], self::$error['AUTH_FAILED']['msg']);
            } else {
                $token = Helper::guid();
                // 创建内存表记录
                $this->table->set($request->fd, ['token' => $token, 'counter' => 0, 'create_time' => time(), 'last_time'=>time(), 'user_info'=>json_encode($user)]);
                // 向客户端发送授权
                $server->push($request->fd, $token);
            }
        } catch (\Throwable $e) {
           $server->disconnect($request->fd, self::$error['AUTH_FAILED']['code'], self::$error['AUTH_FAILED']['msg']);
        }
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
            } else {
                $result = $this->runing($checkResult);
                $data = ['code'=>0, 'msg'=>'', 'data'=>$result, 'requestId'=>$checkResult['requestId']];
                $this->websocketFrame->sendToClient($frame->fd, $data);
            }
            unset($this->websocketFrame);
            WebSocketFrame::destroy();
        } catch (\Throwable $e) {
            $req = json_decode($frame->data, true);
            $data = ['code'=>$e->getCode(), 'msg'=>$e->getMessage(), 'data'=>[], 'requestId'=>$req['requestId']];
            $this->websocketFrame->sendToClient($frame->fd, $data);
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
        if (empty($user) || empty($data['data']) || empty($data['token']) || empty($data['timestamp']) || empty($data['sign'])  || empty($data['requestId'])) {
            return false;
        }
        if ($user['token'] != $data['token'] || $data['timestamp'] + 600 < time() ||  $data['timestamp'] - 600 > time()) {
            return false;
        }
        $vi = substr($data['token'], -16);
        if (Config::get('msfoole.websocket.sign') == null) {
            $pass = base64_encode(openssl_encrypt(json_encode($data['data']),"AES-128-CBC", Config::get('msfoole.websocket.key'),OPENSSL_RAW_DATA, $vi));
            if ($pass != $data['sign']) {
                return false;
            }
        } else {
            if (Config::get('msfoole.websocket.sign') != $data['sign']) {
                return false;
            }
        }
        if (empty($data['data']['timestamp']) || $data['data']['timestamp'] != $data['timestamp']) {
            return false;
        }
        return [
            'module' => $data['data']['module'] ?? Config::get('application.default.controller'),
            'method' => $data['data']['method'] ?? Config::get('application.default.action'),
            'arguments' => $data['data']['arguments'] ?? [],
            'requestId' =>  $data['requestId'],
            'user' => json_decode($user['user_info'])
        ];
    }

    /**
     * 业务逻辑运行
     * @param array $args
     * @return mixed
     */
    private function runing(array $args)
    {
        $controller = Loader::factory($args['module'], Config::get('msfoole.websocket.namespace'));
        $controller->init($args['user'], $args['arguments']);
        $result = call_user_func([$controller, $args['method']]);
        return $result;
    }

}
