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

$application = new \Symfony\Component\Console\Application();
$application->add(new \Julibo\Msfoole\Commands\InitialServer('msfoole'));
$application->run();
