<?php
/**
 * websocket控制器基类
 */

namespace Julibo\Msfoole;


abstract class WebsocketController
{
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
    final public function init($user, $params)
    {
        $this->user = $user;
        $this->params = $params;
    }

}