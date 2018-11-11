<?php
/**
 * Created by PhpStorm.
 * User: carson
 * Date: 2018/11/10
 * Time: 3:59 PM
 */

namespace Julibo\Msfoole\Interfaces;


abstract class Process
{
    /**
     * 进程初始化
     * @return mixed
     */
    abstract public function init();

    /**
     * 进程入口
     * @return mixed
     */
    public function main($param = null)
    {
        static::init();
    }
}