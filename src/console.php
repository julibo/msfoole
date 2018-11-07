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
// | 命令行引导文件
// +----------------------------------------------------------------------

namespace Julibo\Msfoole;

// 加载基础文件
require __DIR__ . '/base.php';

use Julibo\Msfoole\Commands\Task;
use Symfony\Component\Console\Application;

$task = new Task();



//$application = new Application();
//$application->add($task);
//$application->run();
