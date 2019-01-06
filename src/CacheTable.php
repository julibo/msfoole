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

namespace Julibo\Msfoole;

use Julibo\Msfoole\Facade\Config;

class CacheTable
{
    private $table;

    public function __construct()
    {
        $cache_size      = Config::get('msfoole.cache.size') ?: 1024;
        $cache_data_size = Config::get('msfoole.cache.data_size') ?: 1024 * 8;
        $this->table = new \swoole\table($cache_size);
        $this->table->column('time', \swoole\table::TYPE_INT, 4);
        $this->table->column('data', \swoole\table::TYPE_STRING, $cache_data_size);
        $this->table->create();
    }

    public function getTable()
    {
        return $this->table;
    }

    public function set($key, $value)
    {
        return $this->table->set($key, ['time' => 0, 'data' => $value]);
    }

    public function setex($key, $expire, $value)
    {
        return $this->table->set($key, ['time' => time() + $expire, 'data' => $value]);
    }

    public function incr($key, $column, $incrby = 1)
    {
        return $this->table->incr($key, $column, $incrby);
    }

    public function decr($key, $column, $decrby = 1)
    {
        return $this->table->decr($key, $column, $decrby);
    }

    public function get($key)
    {
        $data = $this->table->get($key);
        if (false === $data) {
            return null;
        }
        if (0 == $data['time']) {
            return $data['data'];
        }
        if (0 <= $data['time'] && $data['time'] < time()) {
            $this->del($key);
            return null;
        }
        return $data['data'];
    }

    public function getPeriod($key)
    {
        $data = $this->table->get($key);
        if (0 == $data['time']) {
            $deadline = true;
        } else {
            $deadline = $data['time'] - time();
        }
        return $deadline;
    }

    public function exist($key)
    {
        return $this->table->exist($key);
    }

    public function del($key)
    {
        return $this->table->del($key);
    }

    public function clear()
    {
        foreach ($this->table as $key => $val) {
            $this->del($key);
        }
    }
}
