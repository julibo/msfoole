<?php
/**
 * 令牌桶
 */

namespace Julibo\Msfoole;

use Julibo\Msfoole\Facade\Config;

class Token
{
    /**
     * 默认配置
     * @var array
     */
    private $config = [
        'switch' => 1, # 令牌开关
        'code' => 1, # 附带验证码
        'prefix' => 'token:', # 令牌前缀
        'expire' => 1800, # 令牌有效期
        'length' => 4, # 验证码位数
        'imageH' => 0, # 验证码高度
        'imageW' => 0, # 验证码宽度
    ];

    /**
     * @var \Julibo\Msfoole\Cache
     */
    private $cache;

    /**
     * @var
     */
    private $captcha;

    /**
     * @var
     */
    public static $instance;

    /**
     * Token constructor.
     */
    public function __construct()
    {
        $tokenConfig = Config::get('application.token') ?? [];
        $this->config = array_merge($this->config, $tokenConfig);
        $this->captcha = new Captcha($this->config);
        $cacheConfig = Config::get('cache.default') ?? [];
        $cacheConfig = array_merge($cacheConfig, $this->config);
        $this->cache = new Cache($cacheConfig);

    }


    /**
     * 实例化对象
     * @return Token
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new static;
        }
        return self::$instance;
    }

    /**
     * 创建令牌
     * @param bool $code
     * @return array
     */
    public function createToken($code = false)
    {
        $uuid = Helper::guid();
        if ($this->config['code'] || $code) { // 生成验证码
            $verifyCode = $this->captcha->entry();
            $this->cache->set($uuid, $verifyCode['verifyCode']);
            $result = [
                '_token' => $uuid,
                '_code' => $verifyCode['verifyImg']
            ];
        } else {
            $this->cache->set($uuid, $uuid);
            $result = [
                '_token' => $uuid
            ];
        }
        return $result;
    }

    /**
     * 验证令牌及验证码
     * @param $token
     * @param null $code
     * @param bool $force
     * @return bool
     */
    public function validateToken($token, $code = null, $force = false)
    {
        if ($this->config['switch']) {
            $result = $this->cache->pull($token);
            if ($this->config['code'] || $force) {
                if (!empty($result) && $result == $code) {
                    return true;
                } else {
                    return false;
                }
            } else {
                if ($result == $token) {
                    return true;
                } else {
                    return false;
                }
            }
        } else {
            return true;
        }
    }

}
