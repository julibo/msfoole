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
     * 异常信息
     * @var array
     */
    public static $error = [
        'AUTH_FAILED' => ['code' => 210, 'msg' => '认证失败'],
        'CON_EXCEPTION' => ['code' => 211, 'msg' => '连接异常'],
        'SIGN_EXCEPTION' => ['code' => 212, 'msg' => '签名异常'],
        'METHOD_NOT_EXIST' => ['code' => 213, 'msg' => '请求方法不存在'],
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
            $this->working($this->httpRequest->identification);
            $content = ob_get_clean();
            $this->httpResponse->end($content);
        } catch (\Throwable $e) {
             if ($e->getCode() == 401) {
                 $this->httpResponse->status(401);
                 $this->httpResponse->end($e->getMessage());
             } else if ($e->getCode() == 301 || $e->getCode() == 302) {
                 $this->httpResponse->redirect($e->getMessage, $e->getCode);
             }  else {
                 if (Config::get('application.debug')) {
                     $content = ['code'=>$e->getCode(), 'msg'=>$e->getMessage(), 'identification' => $this->httpRequest->identification, 'extra'=>['file'=>$e->getFile(), 'line'=>$e->getLine()]];
                 } else {
                     $content = ['code'=>$e->getCode(), 'msg'=>$e->getMessage(), 'identification' => $this->httpRequest->identification];
                 }
                 $this->httpResponse->end(json_encode($content));
                 if ($e->getCode() >= 500) {
                     throw $e;
                 }
             }
         }
    }

    /**
     * 运行请求
     * @param null $identification
     * @throws Exception
     */
    private function working($identification =  null)
    {
        $controller = Loader::factory($this->httpRequest->controller, $this->httpRequest->namespace, $this->httpRequest, $this->cache);
        if(!is_callable(array($controller, $this->httpRequest->action))) {
            throw new Exception(self::$error['METHOD_NOT_EXIST']['msg'], self::$error['METHOD_NOT_EXIST']['code']);
        }
        $data = call_user_func([$controller, $this->httpRequest->action]);
        if ($data === null && ob_get_contents() != '') {
        } else if (is_string($data) && Config::get('application.allow.output') && in_array($data, Config::get('application.allow.output'))) {
            echo $data;
        } else {
            if (Config::get('application.debug')) {
                $executionTime = round(microtime(true) - $this->beginTime, 6) . 's';
                $consumeMem = round((memory_get_usage() - $this->beginMem) / 1024, 2) . 'K';
                $result = ['code' => 0, 'msg' => '', 'data' => $data, 'identification' => $identification, 'executionTime' =>$executionTime, 'consumeMem' => $consumeMem ];
            } else {
                $result = ['code' => 0, 'msg' => '', 'data' => $data, 'identification' => $identification];
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
            if(!class_exists($authClass) || !is_callable(array($authClass, $authAction))) {
                $server->disconnect($request->fd, self::$error['AUTH_FAILED']['code'], self::$error['AUTH_FAILED']['msg']);
            }
            $user = call_user_func_array([new $authClass, $authAction], [$params]);
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
            $this->websocketFrame = WebSocketFrame::getInstance($server, $frame);
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
            WebSocketFrame::destroy();
        } catch (\Throwable $e) {
            $req = json_decode($frame->data, true);
            if (Config::get('application.debug')) {
                $data = ['code'=>$e->getCode(), 'msg'=>$e->getMessage(), 'data'=>[], 'requestId'=>$req['requestId'], 'extra'=>['file'=>$e->getFile(), 'line'=>$e->getLine()]];
            } else {
                $data = ['code'=>$e->getCode(), 'msg'=>$e->getMessage(), 'data'=>[], 'requestId'=>$req['requestId']];
            }
            $this->websocketFrame->sendToClient($frame->fd, $data);
            if ($e->getCode() >= 500) {
                throw $e;
            }
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
     * @throws Exception
     */
    private function runing(array $args)
    {
        $controller = Loader::factory($args['module'], Config::get('msfoole.websocket.namespace'));
        if(!is_callable(array($controller, $args['method']))) {
            throw new Exception(self::$error['METHOD_NOT_EXIST']['msg'], self::$error['METHOD_NOT_EXIST']['code']);
        } else {
            Log::setEnv($args['requestId'], 'websocket', "{$args['module']}/{$args['method']}", $args['user']->ip ?? '');
            Log::info('请求开始，请求参数为 {message}', ['message' => json_encode($args)]);
            $controller->init($args['token'], $args['user'], $args['arguments']);
            $result = call_user_func([$controller, $args['method']]);
            return $result;
        }
    }

}
