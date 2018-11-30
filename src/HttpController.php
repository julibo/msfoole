<?php
/**
 * Http控制器基类
 */

namespace Julibo\Msfoole;

use Julibo\Msfoole\Facade\Config;
use Julibo\Msfoole\Facade\Cookie;

abstract class HttpController
{
    protected $request;

    protected $user;

    protected $params;

    /**
     * 依赖注入HttpRequest
     * HttpController constructor.
     * @param HttpRequest $request
     * @throws \Exception
     */
    public function __construct(HttpRequest $request)
    {
        $this->request = $request;
        $this->authentication();
        $this->paramChecking();
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
        echo static::class;
        var_dump($allow);
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
            $user = Cookie::getTokenCache();
            if ($user) {
                $this->user = $user;
            } else {
                throw new \Exception("用户认证未通过", 100);
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

}
