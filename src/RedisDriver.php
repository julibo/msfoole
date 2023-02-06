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

use Predis\Client as RedisClient;

class RedisDriver
{
    /**
     * @var array
     */
    public static $instance = [];

    /**
     * @var int
     */
    private $retryTimes = 5;

    /**
     * @var RedisClient
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
        $this->connect();
    }

    /**
     * 实例化
     * @param array $config
     * @return RedisDriver
     */
    public static function instance(array $config = []): self
    {
        $drive = md5(serialize($config));
        if (empty(self::$instance[$drive])) {
            self::$instance[$drive] = new self($config);
        }
        return self::$instance[$drive];
    }

    /**
     * 建立连接
     * @param array $config
     */
    private function connect()
    {
        $this->redis = new RedisClient($this->config);
        if (!empty($password)) {
            $this->redis->auth($password);
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
     * 遍历redis键值对
     * @param string $pattern
     * @param int $count
     * @param null $iterator
     * @return array|bool
     */
    public function scan($pattern = '', $count = 1000, $iterator = null)
    {
        $retryTimes = $this->retryTimes;
        while ($retryTimes) {
            $retryTimes--;
            try {
                $result = $this->redis->scan($iterator, $pattern, $count);
                return ['iterator' => $iterator, 'result' => $result];
            } catch (\Throwable $e) {
                if (strpos($e->getMessage(), 'Redis server went away') !== false) {
                    $this->connect();
                }
            }
        }
        return false;
    }

    /**
     * 清除所有缓存
     * @return bool
     */
    public function clear()
    {
        $iterator = null;
        $pattern = $this->config['prefix'] .  '*';
        do {
            $list = $this->scan($pattern, 1000, $iterator);
            $iterator = $list['iterator'];
            if (!empty($list['result'])) {
                foreach ($list['result'] as $li) {
                    $this->redis->del($li);
                }
            }
        } while ($iterator != 0);
        return true;
    }

    /**
     * @param $key
     * @return bool|string
     */
    public function get($key)
    {
        $retryTimes = $this->retryTimes;
        while ($retryTimes) {
            $retryTimes--;
            try {
                $result = $this->redis->get($key);
                if ($result == "{}" || is_numeric($result)) {
                    continue;
                }
                return $result;
            } catch (\Throwable $e) {
                if (strpos($e->getMessage(), 'Redis server went away') !== false) {
                    $this->connect();
                }
            }
        }
        return false;
    }

    /**
     * @param $key
     * @param $val
     * @param null $expire
     * @return bool
     */
    public function set($key, $val, $expire = null)
    {
        $retryTimes = $this->retryTimes;
        while ($retryTimes) {
            $retryTimes--;
            try {
                if (is_null($expire)) {
                    return $this->redis->set($key, $val);
                } else {
                    return $this->redis->setex($key, $expire, $val);
                }
            } catch (\Throwable $e) {
                if (strpos($e->getMessage(), 'Redis server went away') !== false) {
                    $this->connect();
                }
            }
        }
        return false;
    }

    /**
     * @param $key
     * @return bool|string
     */
    public function del($key)
    {
        $retryTimes = $this->retryTimes;
        while ($retryTimes) {
            $retryTimes--;
            try {
                return $this->redis->del($key);
            } catch (\Throwable $e) {
                if (strpos($e->getMessage(), 'Redis server went away') !== false) {
                    $this->connect();
                }
            }
        }
        return false;
    }

    /**
     * @param $key
     * @param int $step
     * @return bool|int
     */
    public function incrby($key, $step = 1)
    {
        $retryTimes = $this->retryTimes;
        while ($retryTimes) {
            $retryTimes--;
            try {
                return $this->redis->incrby($key, $step);
            } catch (\Throwable $e) {
                if (strpos($e->getMessage(), 'Redis server went away') !== false) {
                    $this->connect();
                }
            }
        }
        return false;
    }

    /**
     * @param $key
     * @param int $step
     * @return bool|int
     */
    public function decrby($key, $step = 1)
    {
        $retryTimes = $this->retryTimes;
        while ($retryTimes) {
            $retryTimes--;
            try {
                return $this->redis->decrby($key, $step);
            } catch (\Throwable $e) {
                if (strpos($e->getMessage(), 'Redis server went away') !== false) {
                    $this->connect();
                }
            }
        }
        return false;
    }

    /**
     *
     * @param $name
     * @param $arguments
     * @return bool
     */
    public function __call($name, $arguments)
    {
        $retryTimes = $this->retryTimes;
        while ($retryTimes) {
            $retryTimes--;
            try {
                $result = $this->redis->$name(...$arguments);
                return $result;
            } catch (\Throwable $e) {
                if (strpos($e->getMessage(), 'Redis server went away') !== false) {
                    $this->connect();
                }
            }
        }
        return false;
    }

}
