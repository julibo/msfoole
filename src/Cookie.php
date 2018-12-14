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

class Cookie
{

    /**
     * @var 请求cookie
     */
    private $cookies;

    /**
     * http响应对象
     * @var
     */
    private $response;

    /**
     * 全局缓存
     * @var
     */
    private $cache;

    /**
     * 默认配置
     * @var
     */
    private $config = [
        // cookie 名称前缀
        'prefix'    => '',
        // cookie 保存时间
        'expire'    => 0,
        // cookie 保存路径
        'path'      => '/',
        // cookie 有效域名
        'domain'    => '',
        //  cookie 启用安全传输
        'secure'    => false,
        // httponly设置
        'httponly'  => '',
        // 用户标识
        'token' => '_token',
        // 自动授时临界值
        'auto_selling' => 600,
    ];

    /**
     * 初始化
     * @param HttpRequest $request
     * @param Response $response
     * @param Cache $cache
     */
    public function init(HttpRequest $request, Response $response, Cache $cache)
    {
        $this->cookies = $request->getCookie();
        $this->response = $response;
        $this->cache = $cache;
        $this->config = array_merge($this->config, Config::get('cookie'));
    }

    /**
     * 设置cookie
     * @param string $key
     * @param string $value
     * @param int $expire
     */
    public function setCookie(string $key, string $value = '', int $expire = 0)
    {
        $key = $this->config['prefix'] . $key;
        if ($expire == 0) {
            $expire = $this->config['expire'] + time();
        } else {
            $expire = time()+$expire;
        }
        $expire = strtotime('+8 hours', $expire);
        $path = $this->config['path'];
        $domain = $this->config['domain'];
        $secure = $this->config['secure'] ? true : false;
        $httponly = $this->config['httponly'] ? true : false;
        $this->response->cookie($key, $value, $expire, $path, $domain, $secure, $httponly);
    }

    /**
     * 获取cookie
     * @param string $key
     * @return string|null
     */
    public function getCookie(string $key)
    {
        $key = $this->config['prefix'] . $key;
        return $this->cookies[$key] ?? null;
    }

    /**
     * 设置用户token
     * @param array $user
     * @param null $uuid
     */
    public function setToken(array $user = [], $uuid = null)
    {
        $token = $this->config['token'] ?: '_token';
        $uuid = $uuid ?? Helper::guid();
        $this->setCookie($token, $uuid, $this->config['expire']);
        $this->cache->set($uuid, $user, $this->config['expire']);
    }

    /**
     * 获取用户token
     * @return string|null
     */
    public function getToken()
    {
        $key = $this->config['token'] ?: '_token';
        $token = $this->getCookie($key);
        return $token;
    }

    /**
     * 获取用户缓存
     * @return mixed|null
     */
    public function getTokenCache()
    {
        $token = $this->getToken();
        $user = $this->cache->get($token);
        if ($user) {
            $deadline = $this->cache->getPeriod($token);
            if ($deadline < $this->config['auto_selling']) {
                $this->setToken($user, $token);
            }
            return $user;
        } else {
            return null;
        }
    }

}
