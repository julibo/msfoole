<?php
/**
 * websocket控制器基类
 */

namespace Julibo\Msfoole;


abstract class WebsocketController
{
    protected $request;

    protected $user;

    protected $params;

    public function __construct(WebSocketRequest $request, $user)
    {
        $this->request = $request;
        $this->user = $user;
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

    }

    /**
     * 参数校验
     */
    protected function paramChecking()
    {

    }

}