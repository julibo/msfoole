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

use Julibo\Msfoole\Cache\Driver;
use Julibo\Msfoole\CacheTable;
use Julibo\Msfoole\Helper;

class Table extends Driver
{
    protected $options = [
        'expire' => 3600,
        'prefix' => '',
        'serialize' => true
    ];

    public function __construct($options = [])
    {
        $this->options = array_merge($this->options, $options);
        $this->handler = new CacheTable();
    }

    public function has($name)
    {
        $key = $this->getCacheKey($name);
        return $this->handler->exist($key);
    }

    public function get($name, $default = null)
    {
        if ($this->has($name)) {
            $key = $this->getCacheKey($name);
            $value = $this->handler->get($key);
            $arrValue = Helper::isJson($value, true);
            if ($this->options['serialize'] && $arrValue) {
                $value = $arrValue;
            }
            return $value;
        } else {
            return $default;
        }
    }

    public function getPeriod($name)
    {
        $key = $this->getCacheKey($name);
        $deadline = $this->handler->getPeriod($key);
        return $deadline;
    }

    public function set($name, $value, $expire = null)
    {
        if ($this->options['serialize'] && (is_object($value) || is_array($value))) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        $key = $this->getCacheKey($name);
        if ($expire) {
            $result = $this->handler->setex($key, $expire, $value);
        } else {
            if (is_null($expire)) {
                $result = $this->handler->setex($key, $this->options['expire'], $value);
            } else {
                $result = $this->handler->set($key, $value);
            }
        }
        return $result;
    }

    public function inc($name, $step = 1)
    {
        if ($this->has($name)) {
            $value  = $this->get($name) + $step;
        } else {
            $value  = $step;
        }
        return $this->set($name, $value, 0) ? $value : false;
    }

    public function dec($name, $step = 1)
    {
        if ($this->has($name)) {
            $value  = $this->get($name) - $step;
        } else {
            $value  = -$step;
        }
        return $this->set($name, $value, 0) ? $value : false;
    }

    public function del($name)
    {
        $key = $this->getCacheKey($name);
        return $this->handler->del($key);
    }

    public function clear()
    {
        $this->handler->clear();
        return true;
    }

    public function getTable()
    {
        return $this->handler->getTable();
    }
}
