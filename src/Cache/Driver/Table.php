<?php
namespace Julibo\Msfoole\Cache\Driver;

use Julibo\Msfoole\Cache\Driver;
use Julibo\Msfoole\CacheTable;

class Table extends Driver
{
    protected $options = [
        'expire' => 0,
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

    public function get($name, $default = false)
    {
        $key = $this->getCacheKey($name);
        $value = $this->handler->get($key, $default);
        if ($this->options['serialize'] && !is_scalar($value)) {
            $value = json_decode($value, true);
        }
        return $value;
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
            $result = $this->handler->set($key, $value);
        }
        return $result;
    }

    public function inc($name, $step = 1)
    {
        if ($this->has($name)) {
            $value  = $this->get($name) + $step;
            $expire = 0;
        } else {
            $value  = $step;
            $expire = 0;
        }
        return $this->set($name, $value, $expire) ? $value : false;
    }

    public function dec($name, $step = 1)
    {
        if ($this->has($name)) {
            $value  = $this->get($name) - $step;
            $expire = 0;
        } else {
            $value  = -$step;
            $expire = 0;
        }
        return $this->set($name, $value, $expire) ? $value : false;
    }

    public function del($name)
    {
        return $this->handler->del($name);
    }

    public function clear()
    {
        $this->handler->clear();
    }
}
