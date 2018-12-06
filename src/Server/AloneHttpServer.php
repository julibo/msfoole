<?php
namespace Julibo\Msfoole\Server;

use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Websocket\Server as Websocket;
use Swoole\WebSocket\Frame as Webframe;
use Swoole\Process;
use Swoole\Table;
use Julibo\Msfoole\Application;
use Julibo\Msfoole\Facade\Config;
use Julibo\Msfoole\Cache;
use Julibo\Msfoole\Channel;
use Julibo\Msfoole\Interfaces\Server as BaseServer;

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

    /**
     * websocket状态内存表
     */
    private $table;

    /**
     * 全局缓存
     * @var Cache
     */
    protected $cache;

    /**
     * 开启队列投递服务
     */
    protected $channelOpen = false;

    public function createTable()
    {
        $this->table = new table(Config::get('msfoole.table.size'));
        $this->table->column('token', table::TYPE_STRING, 32);
        $this->table->column('counter', table::TYPE_INT, 4);
        $this->table->column('create_time', table::TYPE_INT, 4);
        $this->table->column('last_time', table::TYPE_INT, 4);
        $this->table->column('user_info', table::TYPE_STRING, 1024);
        $this->table->create();
    }

    protected function init()
    {
        $this->option['upload_tmp_dir'] = TEMP_PATH;
        $this->option['http_parse_post'] = true;
    }

    protected function startLogic()
    {
        $channelSize = Config::get('msfoole.channel.size');
        if ($channelSize) {
            Channel::instance($channelSize);
            $this->channelOpen = true;
        }
        # 创建websocket状态内存表
        if ($this->serverType == 'socket') {
            $this->createTable();
        }

        # 开启全局缓存
        $cacheConfig = Config::get('cache.default') ?? [];
        $this->cache = new Cache($cacheConfig);
        # 开启监控
        $this->monitorProcess();
    }

    /**
     * 文件监控，不包含配置变化
     * table内存表监控
     */
    protected function monitorProcess()
    {
        $tableMonitor = false;
        if ($this->cache) {
            $driver = $this->cache->getDriver();
            if (strtolower($driver) == 'table') {
                $tableMonitor = true;
            }
        }
        $paths = Config::get('msfoole.monitor.path');
        if ($paths || $tableMonitor || $this->channelOpen) {
            $mp = new Process(function (Process $process) use ($paths, $tableMonitor) {
                $process->name("msfoole:monitor");
                if ($this->channelOpen) {
                    swoole_timer_tick(1000, function () {
                        $data = Channel::instance()->pop();
                        if (!empty($data)) {
                            // websocket 广播
                            if ($data['type'] == 1) {
                               foreach($this->table as $fd => $row)
                               {
                                   if ($row['token'] == $data['client']) {
                                       $this->swoole->push($fd, json_encode($data));
                                       break;
                                   }
                               }
                            }
                        }
                    });
                }
                if ($tableMonitor) {
                    swoole_timer_tick(60000, function () {
                        $timestamp = time();
                        $table = $this->cache->getTable();
                        foreach ($table as $key => $val) {
                            if ($val['time'] > 0 && $val['time'] < $timestamp)
                                $table->del($key);
                        }
                    });
                }
                if ($paths) {
                    $timer = Config::get('msfoole.monitor.interval') ?? 10;
                    swoole_timer_tick($timer*6000, function () use($paths) {
                        foreach ($paths as $path) {
                            $dir      = new \RecursiveDirectoryIterator($path);
                            $iterator = new \RecursiveIteratorIterator($dir);

                            foreach ($iterator as $file) {
                                if (pathinfo($file, PATHINFO_EXTENSION) != 'php') {
                                    continue;
                                }

                                if ($this->lastMtime < $file->getMTime()) {
                                    $this->lastMtime = $file->getMTime();
                                    echo '[update]' . $file . " reload...\n";
                                    $this->swoole->reload();
                                    break 2;
                                }
                            }
                        }
                    });
                }
            });
            $this->swoole->addProcess($mp);
        }
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
        swoole_set_process_name("msfoole:manager");
        var_dump("管理进程启动");
    }

    public function onManagerStop(\Swoole\Server $server)
    {
        var_dump("管理进程停止");
    }

    public function onWorkerStart(\Swoole\Server $server, int $worker_id)
    {
        swoole_set_process_name("msfoole:worker");
        // 应用实例化
        $this->app = new Application();
        // Swoole Server保存到容器
        $this->app->swoole = $server;
        $this->app->cache = $this->cache;
        if ($this->table) {
            $this->app->table = $this->table;
        }
        $this->app->initialize();
//        $data = [
//            'namespace' => '\\App\\Service\\',
//            'class' =>'Robot',
//            'action' =>'register',
//            'data' => ['fd'=>1]
//        ];
//        Channel::instance()->push($data);
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
        // 销毁内存表记录
        if (!is_null($this->table) && $this->table->exist($fd))
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
        // print_r($request);
        $this->app->swooleHttp($request, $response);
    }


    /**
     * 连接开启回调
     * @param Websocket $server
     * @param SwooleRequest $request
     */
    public function WebsocketonOpen(Websocket $server, SwooleRequest $request)
    {
        // print_r($request);
        $this->app->swooleWebSocketOpen($server, $request);
        $server->push($request->fd, 10000);
    }

    /**
     * Message回调
     * @param $server
     * @param $frame
     */
    public function WebsocketonMessage(Websocket $server, Webframe $frame)
    {
        // print_r("receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}");
        // 执行应用并响应
        $this->app->swooleWebSocket($server, $frame);
    }
}
