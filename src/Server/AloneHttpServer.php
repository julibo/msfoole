<?php
namespace Julibo\Msfoole\Server;

use Julibo\Msfoole\Interfaces\Server as BaseServer;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Websocket\Server as Websocket;
use Swoole\WebSocket\Frame as Webframe;
use Swoole\Table;
use Julibo\Msfoole\Application;
use Julibo\Msfoole\Facade\Config;


class AloneHttpServer extends BaseServer
{

    protected $serverType = 'http';

    protected $event = [
        'Start',
        'Shutdown',
        'ManagerStart',
        'ManagerStop',
        'WorkerStart',
        'WorkerStop',
        'WorkerExit',
        'WorkerError',
        'Close',
        'Open',
        'Message',
        'Request'
    ];

    protected $app;

    private $table;

    private $cacheTable;

    public function createTable()
    {
        $this->table = new table(Config::get('msfoole.table.size'));
        $this->table->column('cardno', table::TYPE_STRING, 20);
        $this->table->column('token', table::TYPE_STRING, 32);
        $this->table->column('create_time', table::TYPE_INT, 4);
        $this->table->column('last_time', table::TYPE_INT, 4);
        $this->table->column('user_info', table::TYPE_STRING, 1024);
        $this->table->create();
    }

    protected function init()
    {
        # todo
        # $this->option['upload_tmp_dir'] = TEMP_PATH;
        # $this->option['http_parse_post'] = true;
    }

    protected function startLogic()
    {
        # 创建内存表
        $this->createTable();
    }

    public function onStart(\Swoole\Server $server)
    {
        var_dump("主进程启动");
    }

    public function onShutdown(\Swoole\Server $server)
    {
        var_dump("主进程结束");
    }

    public function onManagerStart(\Swoole\Server $server)
    {
        var_dump("管理进程启动");
    }

    public function onManagerStop(\Swoole\Server $server)
    {
        var_dump("管理进程停止");
    }

    public function onWorkerStart(\Swoole\Server $server, int $worker_id)
    {
        // 应用实例化
        $this->app       = new Application();
        // Swoole Server保存到容器
        $this->app->swoole = $server;
        # todo
        # $this->app->cacheTable = $this->cacheTable;
        if ($this->table) {
            $this->app->table = $this->table;
        }
        $this->app->initialize();
        var_dump("worker进程启动");
    }

    public function onWorkerStop(\Swoole\Server $server, int $worker_id)
    {
        var_dump("worker进程停止");
    }

    public function onWorkerExit(\Swoole\Server $server, int $worker_id)
    {
        var_dump("worker进程退出");
    }

    public function onWorkerError(\Swoole\Server $serv, int $worker_id, int $worker_pid, int $exit_code, int $signal)
    {
        var_dump("worker进程异常");
    }

    public function onClose(\Swoole\Server $server, int $fd, int $reactorId)
    {
        echo "client {$fd} closed\n";
        // 销毁内存表记录
        $this->table->del($fd);
    }

    /**
     * request回调
     * @param $request
     * @param $response
     */
    public function onRequest(SwooleRequest $request, SwooleResponse $response)
    {
        // 执行应用并响应
        print_r($request);
        $this->app->swooleHttp($request, $response);
    }


    /**
     * 连接开启回调
     * @param Websocket $server
     * @param SwooleRequest $request
     */
    public function WebsocketonOpen(Websocket $server, SwooleRequest $request)
    {
        $this->app->swooleWebSocketOpen($server, $request);
    }

    /**
     * Message回调
     * @param $server
     * @param $frame
     */
    public function WebsocketonMessage(Websocket $server, Webframe $frame)
    {
        print_r("receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}");
        // 执行应用并响应
        $this->app->swooleWebSocket($server, $frame);
    }
}
