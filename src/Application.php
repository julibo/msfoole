<?php

namespace Julibo\Msfoole;

use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Websocket\Server as Websocket;
use Swoole\WebSocket\Frame as Webframe;
use Julibo\Msfoole\Facade\Config;

class Application
{
    // 开始时间和内存占用
    private $beginTime;
    private $beginMem;
    private $websocketFrame;

    public $swoole;
    public $table;
    public $cache;

    private $request;


    public static $error = [
        'CON_EXCEPTION' => ['code' => 10000, 'msg' => '连接异常'],
        'SIGN_EXCEPTION' => ['code' => 10001, 'msg' => '签名异常'],
    ];

    public function initialize()
    {

    }

    /**
     * 获取TOKEN
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
        $token = substr(Helper::guid(), 16);
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
     * webSocket连接开启
     * @param Websocket $server
     * @param SwooleRequest $request
     */
    public function swooleWebSocketOpen(Websocket $server, SwooleRequest $request)
    {
        $this->request = WebSocketRequest::getInstance($request);
        $token = $this->request->getQuery('token');
        $carno = $this->request->getQuery('cardno');
        $timestamp = $this->request->getQuery('timestamp');
        $sign = $this->request->getQuery('sign');

        if ($token && $carno && $timestamp && $sign) {
            $token = $this->getToken($token, $carno, $timestamp, $sign);
        }
        if ($token === false) {
            $server->disconnect($this->request->getFd(), self::$error['CON_EXCEPTION']['code'], self::$error['CON_EXCEPTION']['msg']);
        } else {
            $this->request->setToken($token);
            $server->push($this->request->getFd(), $token);
        }
        // 创建内存表记录
        $this->table->set($this->request->getFd(), ['cardno' => $carno, 'token' => $token, 'create_time' => $timestamp, 'last_time'=>$timestamp, 'user_info'=>'{}']);
        // 将请求保存到内存表后销毁request记录
        $this->destroyRequest();
    }

    /**
     * 解析并验证请求
     * @param string $data
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
        $checkSign = base64_encode(openssl_encrypt(json_encode($data['data']),"AES-128-CBC", Config::get('msfoole.websocket.key'),OPENSSL_RAW_DATA, $data['token']));
        if ($data['sign'] != $checkSign && false) {
            return false;
        }
        $req = json_decode($data['data'], true);
        return [
            'module' => $req['module'] ?? 'Index',
            'method' => $req['method'] ?? 'index',
            'arguments' => $req['arguments'] ?? [],
        ];
    }

    /**
     * 处理websocket请求
     * @param Websocket $server
     * @param Webframe $frame
     */
    public function swooleWebSocket(Websocket $server, Webframe $frame)
    {
        try {
            // 重置应用的开始时间和内存占用
            $this->beginTime = microtime(true);
            $this->beginMem  = memory_get_usage();
            $this->websocketFrame = WebSocketFrame::getInstance($server, $frame);
            // 解析并验证请求
            $checkResult = $this->explainMessage($this->websocketFrame->getData());
            if ($checkResult === false) {
                $this->websocketFrame->disconnect($frame->fd, self::$error['SIGN_EXCEPTION']['code'], self::$error['SIGN_EXCEPTION']['msg']);
            }
            $result = $this->run($checkResult['module'], $checkResult['method'], $checkResult['arguments']);
            $this->send($frame->fd, $result);
            WebSocketFrame::destroy();
        } catch (\Throwable $e) {
            var_dump($e->getMessage(), $e->getFile(), $e->getLine());
        }
    }

    /**
     * 业务处理
     * @param $module
     * @param $method
     * @param array $arguments
     * @return mixed
     */
    private function run($module, $method, $arguments = [])
    {
        $controller = Loader::factory($module, '\\App\\Controller\\');
        $result = $controller->$method($arguments);
        return $result;
    }

    /**
     * 数据发送
     */
    private function send($fd, $result)
    {
        $data = ['code'=>0, 'msg'=>'', 'data'=>$result];
        $this->websocketFrame->sendToClient($fd, $data);
    }

    /**
     * 处理http请求
     * @param SwooleRequest $request
     * @param SwooleResponse $response
     */
    public function swooleHttp(SwooleRequest $request, SwooleResponse $response)
    {
        try {
            // 重置应用的开始时间和内存占用
            $this->beginTime = microtime(true);
            $this->beginMem  = memory_get_usage();

            ob_start();
            echo "Hello World";
            $content = ob_get_clean();
            $response->end($content);
        } catch (\Throwable $e) {
            var_dump($e->getMessage(), $e->getFile(), $e->getLine());
        }
    }



}