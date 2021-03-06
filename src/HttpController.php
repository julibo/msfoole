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
use Julibo\Msfoole\Facade\Cookie;

abstract class HttpController
{
    protected $request;

    protected $user;

    protected $params;

    protected $cache;

    /**
     * 依赖注入HttpRequest
     * HttpController constructor.
     * @param $request
     * @param $cache
     * @throws \Exception
     */
    public function __construct($request, $cache)
    {
        $this->request = $request;
        $this->cache = $cache;
        $this->paramChecking();
        $this->authentication();
        $this->init();
    }

    /**
     * 初始化方法
     * @return mixed
     */
    abstract protected function init();


    /**
     * 用户鉴权
     */
    final protected function authentication()
    {
        $execute = true;
        $allow = Config::get('application.allow.controller');
        if (is_array($allow)) {
            if (in_array(static::class, $allow)) {
                $execute = false;
            }
        } else {
            if (static::class == $allow) {
                $execute = false;
            }
        }
        if ($execute) {
            $header = $this->request->header;
            if (empty($header) || !isset($header['level']) || empty($header['timestamp']) || empty($header['token']) ||
                empty($header['signstr']) || !in_array($header['level'], [0, 1, 2]) || $header['timestamp'] > strtotime('10 minutes') * 1000 ||
                $header['timestamp'] < strtotime('-10 minutes') * 1000) {
                throw new Exception(Application::$error['REQUEST_EXCEPTION']['msg'], Application::$error['REQUEST_EXCEPTION']['code']);
            }
            $signsrc = $header['timestamp'].$header['token'];
            if (!empty($this->params)) {
                ksort($this->params);
                array_walk($this->params, function($value,$key) use (&$signsrc) {
                    $signsrc .= $key.$value;
                });
            }
            if (md5($signsrc) != $header['signstr']) {
                throw new Exception(Application::$error['SIGN_EXCEPTION']['msg'], Application::$error['SIGN_EXCEPTION']['code']);
            }
            $user = $this->getUserByToken();
            if ($user) {
                $this->user = $user;
            } else {
                throw new Exception(Application::$error['AUTH_EXCEPTION']['msg'], Application::$error['AUTH_EXCEPTION']['code']);
            }
        }
    }

    /**
     * 参数检查
     */
    protected function paramChecking()
    {
        $this->params = $this->request->params;
    }

    /**
     * 向客户端授权
     * @param array $user
     */
    protected function setToken(array $user)
    {
        Cookie::setToken($user);
    }

    /**
     * 通过token获取用户信息
     * @return array
     */
    protected function getUserByToken() : array
    {
        $token =  $this->request->getHeader('token') ?? null;
        $user = Cookie::getTokenCache($token);
        return $user ?? [];
    }
}
