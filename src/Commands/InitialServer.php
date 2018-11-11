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

namespace Julibo\Msfoole\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Julibo\Msfoole\Interfaces\Console;
use Julibo\Msfoole\Facade\Config;

class InitialServer extends Command implements Console
{

    public function __construct()
    {
        parent::__construct();
        $this->init();
    }

    public function init()
    {

    }

    public function configure()
    {
        $this->setName('msfoole')
            ->setProcessTitle('msfoole:master')
            ->setDescription('msfoole命令行工具')
            ->setHelp('msfoole是基于swoole的简易微服务框架')
            ->addArgument('task', InputArgument::REQUIRED, '执行操作：可选择值为start、stop、reload')
            ->addArgument('mode', InputArgument::OPTIONAL, '运行模式：可选值为t、d', 't')
            ->addOption('env', 'e', InputOption::VALUE_REQUIRED, '运行环境：可选值为dev、test、demo、online', 'dev')
            ->addOption('server', 's', InputOption::VALUE_OPTIONAL, '附加服务：websocket', 'none')
            ->addOption('plan', 'g', InputOption::VALUE_OPTIONAL, '服务架构：group、alone', 'alone')
            ->addOption('level', 'm', InputOption::VALUE_OPTIONAL, '服务层级：main、client', 'client');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        // step 1 获取命令行参数
        $command = $input->getArgument('task');
        $mode = $input->getArgument('mode');
        $env = $input->getOption('env');
        $server = $input->getOption('server');
        $server = $server === null || $server == 'websocket' ? 'websocket' : 'none';
        $plan = $input->getOption('plan');
        $plan = $plan === null || $plan == 'group' ? 'group' : 'alone';
        $level = $input->getOption('level');
        $level = $level === null || $level == 'main' ? 'main' : 'client';
        // step 2 校验命令行参数
        $this->parameterCheck($output, $command, $mode, $env, $server, $plan, $level);
        // step 3 解析环境配置
        $this->setEnvConfig($env);

        // step 4 初始化相应服务
        $process = new HttpServer();

        // step 5 执行相应命令
        switch ($command) {
            case 'stop':
                $process->stop();
                break;
            case 'reload':
                $process->reload();
                break;
            default :
                $process->main();
                $process->start();
                break;
        }
    }

    /**
     * 参数和选项校验
     * 输出样式：comment，info，error，question
     * @param $output
     * @param $server
     * @param $command
     * @param $mode
     * @param $env
     * @param $plan
     * @param $level
     */
    private function parameterCheck($output, $command, $mode, $env, $server, $plan, $level)
    {
        $optionParams = [
            'command' => ['start', 'stop', 'reload'],
            'mode' => ['t', 'd'],
            'env' => ['dev', 'test', 'demo', 'online'],
            'server' => ['none', 'websocket'],
            'plan' => ['alone', 'group'],
            'level' => ['client', 'main'],
        ];
        if (!in_array($command, $optionParams['command'])) {
            $output->writeln([
                "<error>\r\n\r\n执行操作：可选择值为start（启动）、stop（停止）、reload（重载）\r\n</error>"
            ]);
            exit(1);
        }
        if (!in_array($mode, $optionParams['mode'])) {
            $output->writeln([
                "<error>\r\n\r\n运行模式：可选值为t（命令行模式）、d（守护模式）\r\n</error>"
            ]);
            exit(2);
        }
        if (!in_array($env, $optionParams['env'])) {
            $output->writeln([
                "<error>\r\n\r\n运行环境：可选值为dev（开发环境）、test（测试环境）、demo（演示环境）、online（生产环境）\r\n</error>"
            ]);
            exit(3);
        }
        if (!in_array($server, $optionParams['server'])) {
            $output->writeln([
                "<error>\r\n\r\n附加服务：websocket（websocket模式）\r\n</error>"
            ]);
            exit(4);
        }
        if (!in_array($plan, $optionParams['plan'])) {
            $output->writeln([
                "<error>\r\n\r\n服务架构：可选值为group（集群架构）、alone（单机架构）\r\n</error>"
            ]);
            exit(5);
        }
        if (!in_array($level, $optionParams['level'])) {
            $output->writeln([
                "<error>\r\n\r\n服务层级：可选值为main（集群主机）、client（客户机）\r\n</error>"
            ]);
            exit(6);
        }
    }

    /**
     * 根据环境加载对应的环境配置文件
     * @param $env
     * @throws \Exception
     */
    private function setEnvConfig($env)
    {
        $file = CONF_PATH . 'php-' . strtolower($env) . ENV_EXT;
        if (!file_exists($file)) {
            throw new \Exception("环境配置文件不存在");
        }
        Config::loadFile($file, ENV_EXT);
    }
}