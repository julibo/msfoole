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

namespace Julibo\Msfoole\Cache\Driver;

use Julibo\Msfoole\RedisDriver;
use Julibo\Msfoole\Cache\Driver;
use Julibo\Msfoole\Helper;

class Redis extends Driver
{

    public function __construct(array $options = [])
    {
        $this->handler = RedisDriver::instance($options);
        $this->options = $this->handler->getConfig();
    }

    public function has($name)
    {
        $key = $this->getCacheKey($name);
        return $this->handler->exists($key);
    }


    public function getPeriod($name)
    {
        $key = $this->getCacheKey($name);
        $deadline = $this->handler->ttl($key);
        return $deadline;
    }

    public function get($name, $default = null)
    {
        $key = $this->getCacheKey($name);
        $value = $this->handler->get($key);
        if ($this->options['serialize'] && $this->isJson($value)) {
            return json_decode($value, true);
        }
        return $value;
    }

    public function set($name, $value, $expire = null)
    {
        if (!is_scalar($value) && $this->options['serialize']) {
            $value = json_encode($value);
        }
        if (empty($value) || $value == "{}" || (is_numeric($value) && strpos($name,"mobile") !== 0)) {
            return;
        }
        $key = $this->getCacheKey($name);
        $this->handler->set($key, $value, $expire);
    }

    public function clear()
    {
        return $this->handler->clear();
    }

    public function del($name)
    {
        $key = $this->getCacheKey($name);
        return $this->handler->del($key);
    }

    public function inc($name, $step = 1)
    {
        $key = $this->getCacheKey($name);
        return $this->handler->incrby($key, $step);
    }

    public function dec($name, $step = 1)
    {
        $key = $this->getCacheKey($name);
        return $this->handler->decrby($key, $step);
    }

}
