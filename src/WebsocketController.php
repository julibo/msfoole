<?php
/**
 * websocket控制器基类
 */

namespace Julibo\Msfoole;


abstract class WebsocketController
{
    /**
     * 用户标识
     * @var
     */
    protected $token;

    /**
     * 当前用户
     * @var
     */
    protected $user;

    /**
     * 请求参数
     * @var
     */
    protected $params;

    /**
     * 初始化方法
     * @param $user
     * @param $params
     */
    final public function init($token, $user, $params)
    {
        $this->token = $token;
        $this->user = $user;
        $this->params = $params;
    }

}
