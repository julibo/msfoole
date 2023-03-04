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

use Swoole\Coroutine\Redis;

class RedisDriver
{

    /**
     * @var Swoole\Coroutine\Redis
     */
    private $redis;

    /**
     * 默认配置
     * @var array
     */
    private $config = [
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => '',
        'db' => 0,
        'expire' => 3600,
        'prefix' => '',
    ];

    /**
     * 构造方法
     * RedisDriver constructor.
     * @param array $config
     */
    private function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 实例化
     * @param array $config
     * @return RedisDriver
     */
    public static function instance(array $config = []): self
    {
        return new self($config);
    }

    /**
     * 建立连接
     * @param array $config
     */
    private function connect()
    {
        $this->redis = new Redis();
        $this->redis->connect($this->config['host'], $this->config['port']);
        if (!empty($this->config['password'])) {
            $this->redis->auth($this->config['password']);
        }
        $this->redis->select($this->config['db'] ?? 0);
    }

    /**
     * @return bool
     */
    public function ping()
    {
        if ($this->redis->ping() == '+PONG')
            return true;
        else
            return false;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param $key
     * @return bool|string
     */
    public function get($key)
    {
        $this->connect();
        return $this->redis->get($key);
    }

    /**
     * @param $key
     * @param $val
     * @param null $expire
     * @return bool
     */
    public function set($key, $val, $expire = null)
    {
        $this->connect();
        if (is_null($expire)) {
            return $this->redis->set($key, $val, $this->config['expire']);
        } else {
            return $this->redis->setEx($key, $expire, $val);
        }
    }

    /**
     * @param $key
     * @return bool|string
     */
    public function del($key)
    {
        $this->connect();
        return $this->redis->del($key);
    }

    /**
     * @param $key
     * @param int $step
     * @return bool|int
     */
    public function incrby($key, $step = 1)
    {
        $this->connect();
        return $this->redis->incrBy($key, $step);
    }

    /**
     * @param $key
     * @param int $step
     * @return bool|int
     */
    public function decrby($key, $step = 1)
    {
        $this->connect();
        return $this->redis->decrBy($key, $step);
    }

    /**
     *
     * @param $name
     * @param $arguments
     * @return bool
     */
    public function __call($name, $arguments)
    {
        $this->connect();
        return $this->redis->$name(...$arguments);
    }

}
