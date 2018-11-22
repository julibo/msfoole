<?php
namespace Julibo\Msfoole\Server;

use Julibo\Msfoole\Interfaces\Server as BaseServer;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Websocket\Server as Websocket;
use Swoole\WebSocket\Frame as Webframe;
use Julibo\Msfoole\Application;

class AloneHttpServer extends BaseServer
{

    protected $serverType = 'http';

    protected $app;

    protected $event = [
        'Start',
        'Close',
        'Request',
        'Open',
        'Message',
    ];

    protected function init()
    {
        $this->option['upload_tmp_dir'] = TEMP_PATH;
        $this->option['http_parse_post'] = true;
    }

    protected function startup()
    {
        # todo
    }

    public function onStart()
    {
        // var_dump($this->swoole->setting);
    }

    public function onClose($ser, $fd)
    {
        echo "client {$fd} closed\n";
    }

    public function onWorkerStart()
    {
        // 应用实例化

    }

    /**
     * request回调
     * @param $request
     * @param $response
     */
    public function onRequest(SwooleRequest $request, SwooleResponse $response)
    {
        // 执行应用并响应
        $this->app  = new Application();
        $this->app->swooleHttp($request, $response);
    }


    public function WebsocketonOpen(Websocket $server, SwooleRequest $request)
    {
        $this->app  = new Application();
        $this->app->swooleWebSocketOpen($server, $request);
    }

    /**
     * Message回调
     * @param $server
     * @param $frame
     */
    public function WebsocketonMessage(Websocket $server, Webframe $frame)
    {
        // echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
        // 执行应用并响应
        $this->app  = new Application();
        $this->app->swooleWebSocket($server, $frame);
    }





}

