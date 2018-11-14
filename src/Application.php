<?php

namespace Julibo\Msfoole;

use Swoole\Http\Request;
use Swoole\Http\Response;

class Application
{
    /**
     * 处理Swoole请求
     * @access public
     * @param  \Swoole\Http\Request $request
     * @param  \Swoole\Http\Response $response
     * @param  void
     */
    public function swooleHttp(Request $request, Response $response)
    {
        try {
            ob_start();
            echo "Hello World";
            $content = ob_get_clean();
            $response->end($content);
        } catch (\Throwable $e) {
            var_dump($e);
        }
    }

    public function swooleWebSocket($server, $frame)
    {
        try {
            // 重置应用的开始时间和内存占用
            $this->beginTime = microtime(true);
            $this->beginMem  = memory_get_usage();

            $server->push($frame->fd, "this is server");

        } catch (\Throwable $e) {
            var_dump($e);
        }
    }

}