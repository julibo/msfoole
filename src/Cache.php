<?php
namespace Julibo\Msfoole;

use Julibo\Msfoole\Cache\Driver;

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

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->init($config);
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
            $type = !empty($options['type']) ? $options['type'] : 'File';
            if (true === $name) {
                $name = md5(serialize($options));
            }
            $this->instance[$name] = Loader::factory($type, '\\Julibo\\Msfoole\\Cache\\Driver\\', $options);
        }
        return $this->instance[$name];
    }

    public function init(array $options = [], $force = false)
    {
        if (isset($this->handle) || $force) {
            if ('complex' == $options['type']) {
                $default = $options['default'];
                $options = isset($options[$default['type']]) ? $options[$default['type']] : $default;
            }
            $this->handle = $this->connect($options);

        }
        return $this->handle;
    }

    public static function __make(Config $config)
    {
        return new static($config->get('cache'));
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function setConfig(array $config)
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 切换缓存类型 需要配置 cache.type 为complex
     * @param string $name 缓存标识
     * @return mixed
     */
    public function store($name = '')
    {
        if ($name !== '' && $this->config['type'] == 'complex') {
            return $this->connect($this->config[$name], strtolower($name));
        }
        return $this->init();
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->init(), $method], $args);
    }

}
