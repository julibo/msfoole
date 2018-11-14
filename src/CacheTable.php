<?php
namespace Julibo\Msfoole;

use think\facade\Config;

class CacheTable
{
    private $table;

    public function __construct()
    {
        $cache_size      = Config::get('swoole.cache_size') ?: 1024;
        $cache_data_size = Config::get('swoole.cache_data_size') ?: 1024 * 1024;

        $this->table = new \swoole\table($cache_size);
        $this->table->column('time', \swoole\table::TYPE_INT, 15);
        $this->table->column('data', \swoole\table::TYPE_STRING, $cache_data_size);
        $this->table->create();
    }

    public function getTable()
    {
        return $this->table;
    }

    public function set($key, $value)
    {
        $this->table->set($key, ['time' => 0, 'data' => $value]);
    }

    public function setex($key, $expire, $value)
    {
        $this->table->set($key, ['time' => time() + $expire, 'data' => $value]);
    }

    public function incr($key, $column, $incrby = 1)
    {
        $this->table->incr($key, $column, $incrby);
    }

    public function decr($key, $column, $decrby = 1)
    {
        $this->table->decr($key, $column, $decrby);
    }

    public function get($key, $field = null)
    {
        $data = $this->table->get($key, $field);
        if (false == $data) {
            return $data;
        }
        if (0 == $data['time']) {
            return $data['data'];
        }
        if (0 <= $data['time'] && $data['time'] >= time()) {
            return false;
        }
        return $data['data'];
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
