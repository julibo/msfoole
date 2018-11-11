<?php
// +----------------------------------------------------------------------
// | msfoole [ 基于swoole的简易微服务框架 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 http://julibo.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: carson <yuzhanwei@aliyun.com>
// +----------------------------------------------------------------------
// | 错误处理类
// +----------------------------------------------------------------------

namespace Julibo\Msfoole;

use Julibo\Msfoole\Exception\ErrorException;
use Julibo\Msfoole\Exception\ThrowableError;
use Julibo\Msfoole\Exception\Handle;
use Julibo\Msfoole\Facade\Log;

class Error
{
    /**
     * 配置参数
     * @var array
     */
    protected static $exceptionHandler;

    /**
     * 注册异常处理
     * @access public
     * @return void
     */
    public static function register()
    {
        error_reporting(E_ALL);
        set_error_handler([__CLASS__, 'appError']);
        set_exception_handler([__CLASS__, 'appException']);
        register_shutdown_function([__CLASS__, 'appShutdown']);
    }

    /**
     * 确定错误类型是否致命
     *
     * @access protected
     * @param  int $type
     * @return bool
     */
    protected static function isFatal($type)
    {
        return in_array($type, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE]);
    }

    /**
     * Exception Handler
     * @access public
     * @param  \Exception|\Throwable $e
     */
    public static function appException($e)
    {
        if (!$e instanceof \Exception) {
            $e = new ThrowableError($e);
        }
        
        self::getExceptionHandler()->report($e);

        if (PHP_SAPI == 'cli') {
            self::getExceptionHandler()->renderForConsole($e);
        } else {
            self::getExceptionHandler()->render($e);
        }
    }

    /**
     * Shutdown Handler
     * @access public
     */
    public static function appShutdown()
    {
        if (!is_null($error = error_get_last()) && self::isFatal($error['type'])) {
            // 将错误信息托管至Julibo\Msfoole\Exception\ErrorException
            $exception = new ErrorException($error['type'], $error['message'], $error['file'], $error['line']);

            self::appException($exception);
        }
        // 写入日志
        Log::save();
    }

    /**
     * Error Handler
     * @access public
     * @param  integer $errno   错误编号
     * @param  integer $errstr  详细错误信息
     * @param  string  $errfile 出错的文件
     * @param  integer $errline 出错行号
     * @throws ErrorException
     */
    public static function appError($errno, $errstr, $errfile = '', $errline = 0)
    {
        $exception = new ErrorException($errno, $errstr, $errfile, $errline);
        if (error_reporting() & $errno) {
            // 将错误信息托管至 Julibo\Msfoole\Exception\ErrorException
            throw $exception;
        }
        # 其他错误处理
       self::getExceptionHandler()->report($exception);
    }

    /**
     * 设置异常处理类
     *
     * @access public
     * @param  mixed $handle
     * @return void
     */
    public static function setExceptionHandler($handle)
    {
        self::$exceptionHandler = $handle;
    }

    /**
     * Get an instance of the exception handler.
     *
     * @access public
     * @return Handle
     */
    public static function getExceptionHandler()
    {
        static $handle;
        if (!$handle) {
            // 异常处理handle
            $class = self::$exceptionHandler;
            if ($class && is_string($class) && class_exists($class) && is_subclass_of($class, "\\Julibo\\Msfoole\\Exception\\Handle")) {
                $handle = new $class;
            } else {
                $handle = new Handle;
                if ($class instanceof \Closure) {
                    $handle->setRender($class);
                }
            }
        }
        return $handle;
    }
}

