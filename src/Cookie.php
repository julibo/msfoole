<?php
namespace Julibo\Msfoole;

class Cookie
{

    /**
     * @var 请求cookie
     */
    private $cookies;

    /**
     * 用户标识
     * @var
     */
    private $token;

    /**
     * http响应对象
     * @var
     */
    private $response;

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
        'token' => '_token'
    ];

    /**
     * 初始化
     */
    public function init(HttpRequest $request, Response $response)
    {
        $this->cookies = $request->getCookie();
        $this->response = $response;
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
            $expire = $this->config['expire'];
        }
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
     */
    public function setToken(array $user = [])
    {
        $token = $this->config['token'] ?: '_token';
        $uuid = Helper::guid();
        $this->setCookie($token, $uuid);
        Cache::set($uuid, json_encode($user), $this->config['expire']);
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
        $user = Cache::get($token);
        if ($user === null)
            return null;
        else
            return json_decode($user);
    }

}
