<?php
namespace Julibo\Msfoole\Commands;

use Julibo\Msfoole\Interfaces\Process as BaseProcess;
use Julibo\Msfoole\Interfaces\Server as BaseServer;

class HttpServer extends BaseProcess implements BaseServer
{

    public function init() {

    }

    public function main($param = null)
    {
        parent::main($param); // TODO: Change the autogenerated stub
    }

    public function start()
    {
        // TODO: Implement start() method.
    }

    public function stop()
    {
        // TODO: Implement stop() method.
    }

    public function reload()
    {
        // TODO: Implement reload() method.
    }
}

