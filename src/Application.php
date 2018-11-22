<?php

namespace Julibo\Msfoole;

use Swoole\Http\Request as SwooleRequest;
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
     * webSocket连接开启
     * @param Websocket $server
     * @param SwooleRequest $request
     */
    public function swooleWebSocketOpen(Websocket $server, SwooleRequest $request)
    {
        $this->request = WebSocketRequest::getInstance($request);
        $this->request->getParam['code'];

        var_dump($this->request);
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
            WebSocketFrame::destroy();
            $this->websocketFrame = WebSocketFrame::getInstance($server, $frame);
            $server->push($frame->fd, $frame->data);
//            $request = json_decode($frame->data, true);
//            $data = $this->run();


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