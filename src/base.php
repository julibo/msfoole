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
// | 基础文件
// +----------------------------------------------------------------------

define('SMFOOLE_VERSION', '0.0.1');
define('SMFOOLE_START_TIME', microtime(true));
define('SMFOOLE_START_MEM', memory_get_usage());
defined('DS') or define('DS', DIRECTORY_SEPARATOR);
defined('ROOT_PATH') or define('ROOT_PATH', realpath(dirname(dirname($_SERVER['SCRIPT_FILENAME']))) . DS);
defined('APP_PATH') or define('APP_PATH', ROOT_PATH . 'app' . DS);
defined('RUN_PATH') or define('RUN_PATH', ROOT_PATH . 'runtime' . DS);
defined('CACHE_PATH') or define('CACHE_PATH', RUN_PATH . 'cache' . DS);
defined('TEMP_PATH') or define('TEMP_PATH', RUN_PATH . 'temp' . DS);
defined('LOG_PATH') or define('LOG_PATH', RUN_PATH . 'logs' . DS);
defined('CONF_PATH') or define('CONF_PATH', ROOT_PATH . 'config' . DS);
defined('CONF_EXT') or define('CONF_EXT', '.ini');
defined('ENV_EXT') or define('ENV_EXT', '.yml');
defined('RUN_ENV') or define('RUN_ENV', 'DEV');
defined('GOAL_ENV') or define('GOAL_ENV', 'MSFOOLE_RUNTIME');

// 环境常量
define('IS_CLI', PHP_SAPI == 'cli' ? true : false);
define('IS_WIN', strpos(PHP_OS, 'WIN') !== false);

require ROOT_PATH . 'vendor/autoload.php';

// 注册错误和异常处理机制
# \Julibo\Msfoole\Error::register();

// 加载项目默认配置
\Julibo\Msfoole\Facade\Config::loadFile(__DIR__ . '/project.yml', ENV_EXT);

// 配置文件解析
if (!is_dir(CONF_PATH)) {
    throw new \Exception("项目配置文件夹不存在");
}
$files = glob(CONF_PATH . '*' . CONF_EXT);
if (empty($files)) {
    throw new \Exception("项目配置文件不存在");
}
\Julibo\Msfoole\Facade\Config::loadFile($files, CONF_EXT);

// 非命令行下根据常量配置和环境变量加载对应环境配置文件
if (!IS_CLI) {
    $env = getenv(GOAL_ENV) ?: RUN_ENV;
    // 根据环境加载对应的环境配置文件
    $file = CONF_PATH . 'php-' . strtolower($env) . ENV_EXT;
    if (!file_exists($file)) {
        throw new \Exception("环境配置文件不存在");
    }

    \Julibo\Msfoole\Facade\Config::loadFile($file, ENV_EXT);
}
