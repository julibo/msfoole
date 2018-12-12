<?php
/**
 * Redis 缓存类
 */

namespace Julibo\Msfoole\Cache\Driver;

use Julibo\Msfoole\RedisDriver;
use Julibo\Msfoole\Cache\Driver;

class Redis extends Driver
{

    public function __construct(array $options = [])
    {
        $this->handler = RedisDriver::instance($options);
        $this->options = $this->handler->getConfig();
    }

    public function has($name)
    {
        return $this->handler->exists($name);
    }

    public function get($name, $default = null)
    {
        $value = $this->handler->get($name);
        if (!$value) {
            $value = $default;
        } else {
            if ($this->options['serialize'] && $this->isJson($value)) {
                $value = json_decode($value);
            }
        }
        return $value;
    }

    public function set($name, $value, $expire = null)
    {
        if (!is_scalar($value) && $this->options['serialize']) {
            $value = json_encode($value);
        }
        $this->handler->set($name, $value, $expire);
    }

    public function clear()
    {
        return $this->handler->clear();
    }

    public function del($name)
    {
        return $this->handler->del($name);
    }

    public function inc($name, $step = 1)
    {
        return $this->handler->incrby($name, $step);
    }

    public function dec($name, $step = 1)
    {
        return $this->handler->decrby($name, $step);
    }

}
