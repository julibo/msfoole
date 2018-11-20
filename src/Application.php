<?php

namespace Julibo\Msfoole;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Websocket\Server as Websocket;
use Swoole\WebSocket\Frame as Webframe;

class Application
{

    // 开始时间和内存占用
    private $beginTime;
    private $beginMem;
    private $request;
    private $websocketFrame;

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
            WebSocketFrame::destroy();
            $this->websocketFrame = WebSocketFrame::getInstance($server, $frame);

            $request = json_decode($frame->data, true);
            $_COOKIE                    = isset($request['arguments']['cookie']) ? $request['arguments']['cookie'] : [];
            $_GET                       = isset($request['arguments']['get']) ? $request['arguments']['get'] : [];
            $_POST                      = isset($request['arguments']['post']) ? $request['arguments']['post'] : [];
            $_FILES                     = isset($request['arguments']['files']) ? $request['arguments']['files'] : [];
            $_SERVER["PATH_INFO"]       = $request['url'] ?: '/';
            $_SERVER["REQUEST_URI"]     = $request['url'] ?: '/';
            $_SERVER["SERVER_PROTOCOL"] = 'http';
            $_SERVER["REQUEST_METHOD"]  = 'post';

            // 重新实例化请求对象 处理swoole请求数据
            $this->request = new WebSocketRequest();
            $this->request->withServer($_SERVER)
                ->withGet($_GET)
                ->withPost($_POST)
                ->withCookie($_COOKIE)
                ->withInput($request->rawContent())
                ->withFiles($_FILES)
                ->setBaseUrl($request->server['request_uri'])
                ->setUrl($request->server['request_uri'] . (!empty($request->server['query_string']) ? '&' . $request->server['query_string'] : ''))
                ->setHost($request->header['host'])
                ->setPathinfo(ltrim($request->server['path_info'], '/'));

            $data = $this->run();


        } catch (\Throwable $e) {
            var_dump($e);
        }
    }

    private function run()
    {

    }

    /**
     * 处理http请求
     * @access public
     * @param  \Swoole\Http\Request $request
     * @param  \Swoole\Http\Response $response
     * @param  void
     */
    public function swooleHttp(Request $request, Response $response)
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
            var_dump($e);
        }
    }



}