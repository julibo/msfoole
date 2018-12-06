<?php
/**
 * swoole队列
 */

namespace Julibo\Msfoole;

use Swoole\Channel as SwooleChannle;

class Channel
{
    private static $instance;

    private $chan;

    private function __construct($size)
    {
        $this->chan = new SwooleChannle($size);
    }

    public static function instance($size = 65536)
    {
        if (!self::$instance) {
            self::$instance = new static($size);
        }
        return self::$instance;
    }

    public function push($data)
    {
        return $this->chan->push($data);
    }

    public function pop()
    {
        return $this->chan->pop();
    }

    public function stats()
    {
        return $this->chan->stats();
    }

}
