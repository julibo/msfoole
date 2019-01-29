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

class Cache
{

    /**
     *  缓存实例
     */
    protected $instance = [];

    /**
     * 缓存配置
     */
    protected $config = [];

    /**
     * 操作句柄
     */
    protected $handle;

    /**
     * 驱动
     * @var
     */
    protected $driver;


    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->init($config);
    }

    public function init(array $options = [], $force = false)
    {
        if (is_null($this->handle) || $force) {
            $this->handle = $this->connect($options, $force);
        }
        return $this->handle;
    }

    /**
     * 连接缓存
     * @param array $options 配置数组
     * @param bool $name 缓存连接标识 true 强制重连
     * @return mixed
     */
    public function connect(array $options = [], $name = false)
    {
        if (false === $name) {
            $name = md5(serialize($options));
        }
        if ($name === true || !isset($this->instance[$name])) {
            $type = !empty($options['driver']) ? $options['driver'] : 'Table';
            $this->driver = $type;
            if (true === $name) {
                $name = md5(serialize($options));
            }
            $this->instance[$name] = Loader::factory($type, '\\Julibo\\Msfoole\\Cache\\Driver\\', $options);
        }
        return $this->instance[$name];
    }

    public function getDriver()
    {
        return $this->driver;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->handle, $method], $args);
    }

}
