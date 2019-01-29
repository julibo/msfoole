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

namespace Julibo\Msfoole\Exception;

/**
 * PDO异常处理类
 * 重新封装了系统的\PDOException类
 */
class PDOException extends DbException
{
    /**
     * PDOException constructor.
     * @access public
     * @param  \PDOException $exception
     * @param  array         $config
     * @param  string        $sql
     * @param  int           $code
     */
    public function __construct(\PDOException $exception, array $config, $sql, $code = 10501)
    {
        $error = $exception->errorInfo;

        $this->setData('PDO Error Info', [
            'SQLSTATE'             => $error[0],
            'Driver Error Code'    => isset($error[1]) ? $error[1] : 0,
            'Driver Error Message' => isset($error[2]) ? $error[2] : '',
        ]);

        parent::__construct($exception->getMessage(), $config, $sql, $code);
    }
}
