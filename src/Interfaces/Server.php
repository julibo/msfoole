<?php
/**
 * Created by PhpStorm.
 * User: carson
 * Date: 2018/11/10
 * Time: 4:18 PM
 */

namespace Julibo\Msfoole\Interfaces;


interface Server
{

    public function start();

    public function stop();

    public function reload();

}