<?php
// +----------------------------------------------------------------------
// | msfoole [ 基于swoole的多进程API服务框架 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 http://julibo.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: carson <yuzhanwei@aliyun.com>
// +----------------------------------------------------------------------

namespace Julibo\Msfoole;

use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Websocket\Server as Websocket;
use Swoole\WebSocket\Frame as Webframe;
use Julibo\Msfoole\Facade\Config;
use Julibo\Msfoole\Facade\Cookie;
use Julibo\Msfoole\Facade\Log;

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

    /**
     * 异常信息
     * @var array
     */
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
     * 析构
     */
    public function destruct()
    {
        $executionTime = round(microtime(true) - $this->beginTime, 6) . 's';
        $consumeMem = round((memory_get_usage() - $this->beginMem) / 1024, 2) . 'K';
        Log::info('请求结束，执行时间{executionTime}，消耗内存{consumeMem}', ['executionTime' => $executionTime, 'consumeMem' => $consumeMem]);
        if ($executionTime > Config::get('log.slow_time')) {
            Log::slow('当前方法执行时间{executionTime}，消耗内存{consumeMem}', ['executionTime' => $executionTime, 'consumeMem' => $consumeMem]);
        }
    }

    /**
     * 处理http请求
     * @param SwooleRequest $request
     * @param SwooleResponse $response
     * @throws \Throwable
     */
    public function swooleHttp(SwooleRequest $request, SwooleResponse $response)
    {
         try {
             ob_start();
            $this->httpRequest = new HttpRequest($request);
            $this->httpResponse = new Response($response);
            Log::setEnv($this->httpRequest->identification, $this->httpRequest->request_method, $this->httpRequest->request_uri, $this->httpRequest->remote_addr);
            Log::info('请求开始，请求参数为 {message}', ['message' => json_encode($this->httpRequest->params)]);
            Cookie::init($this->httpRequest, $this->httpResponse, $this->cache);
            $this->working();
            $content = ob_get_clean();
            $this->httpResponse->end($content);
        } catch (\Throwable $e) {
             if (Config::get('application.debug')) {
                 $content = ['code'=>$e->getCode(), 'msg'=>$e->getMessage(), 'extra'=>['file'=>$e->getFile(), 'line'=>$e->getLine()]];
             } else {
                 $content = ['code'=>$e->getCode(), 'msg'=>$e->getMessage()];
             }
             $this->httpResponse->end(json_encode($content));
             throw $e;
         }
    }

    /**
     * 运行请求
     */
    private function working()
    {
        throw new Exception('worinmi');
        $controller = Loader::factory($this->httpRequest->controller, $this->httpRequest->namespace, $this->httpRequest);
        $data = call_user_func([$controller, $this->httpRequest->action]);
        if (Config::get('application.allow.output') && in_array($data, Config::get('application.allow.output'))) {
            echo $data;
        } else {
            if (Config::get('application.debug')) {
                $executionTime = round(microtime(true) - $this->beginTime, 6) . 's';
                $consumeMem = round((memory_get_usage() - $this->beginMem) / 1024, 2) . 'K';
                $result = ['code' => 0, 'msg' => '', 'data' => $data, 'executionTime' =>$executionTime, 'consumeMem' => $consumeMem ];
            } else {
                $result = ['code' => 0, 'msg' => '', 'data' => $data];
            }
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
                $user['ip'] = $this->request->getRemoteAddr();
                // 创建内存表记录
                $this->table->set($request->fd, ['token' => $token, 'counter' => 0, 'create_time' => time(), 'last_time'=>time(), 'user_info'=>json_encode($user)]);
                // 向客户端发送授权
                $server->push($request->fd, $token);
            }
        } catch (\Throwable $e) {
           $server->disconnect($request->fd, self::$error['CON_EXCEPTION']['code'], self::$error['CON_EXCEPTION']['msg']);
        }
    }

    /**
     * 处理websocket请求
     * @param Websocket $server
     * @param Webframe $frame
     * @throws \Throwable
     */
    public function swooleWebSocket(Websocket $server, Webframe $frame)
    {
        try {
            $this->websocketFrame = new WebSocketFrame($server, $frame);
            // 解析并验证请求
            $checkResult = $this->explainMessage($this->websocketFrame->getData());
            if ($checkResult === false) {
                $this->websocketFrame->disconnect($frame->fd, self::$error['SIGN_EXCEPTION']['code'], self::$error['SIGN_EXCEPTION']['msg']);
            } else {
                $result = $this->runing($checkResult);
                if (Config::get('application.debug')) {
                    $executionTime = round(microtime(true) - $this->beginTime, 6) . 's';
                    $consumeMem = round((memory_get_usage() - $this->beginMem) / 1024, 2) . 'K';
                    $data = ['code'=>0, 'msg'=>'', 'data'=>$result, 'requestId'=>$checkResult['requestId'], 'executionTime' =>$executionTime, 'consumeMem' => $consumeMem];
                } else {
                    $data = ['code'=>0, 'msg'=>'', 'data'=>$result, 'requestId'=>$checkResult['requestId']];
                }
                $this->websocketFrame->sendToClient($frame->fd, $data);
            }
        } catch (\Throwable $e) {
            $req = json_decode($frame->data, true);
            $data = ['code'=>$e->getCode(), 'msg'=>$e->getMessage(), 'data'=>[], 'requestId'=>$req['requestId']];
            $this->websocketFrame->sendToClient($frame->fd, $data);
            throw $e;
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
            'user' => json_decode($user['user_info']),
            'token' => $data['token']
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
        $controller->init($args['token'], $args['user'], $args['arguments']);
        $result = call_user_func([$controller, $args['method']]);
        return $result;
    }

}
