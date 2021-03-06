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
     * @param $token
     * @param $user
     * @param $params
     */
    final public function init(string $token, object $user, array $params)
    {
        $this->token = $token;
        $this->user = $user;
        $this->params = $params;
    }

}
