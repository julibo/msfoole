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
// | 命令行起始任务
// +----------------------------------------------------------------------

namespace Julibo\Msfoole\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Julibo\Msfoole\Interfaces\Console;
use Julibo\Msfoole\Facade\Config;

class Task extends Command implements Console
{

    public function __construct()
    {
        parent::__construct();
        $this->init();
    }

    public function init()
    {
        try {
            // step 1 加载环境变量


        } catch (\Exception $e) {
            echo $e->getMessage(), 1;
        } catch (\Throwable $e) {
            echo $e->getMessage(), 2;
        }



    }

    public function configure()
    {
        $this->setName('msfoole')
            ->setProcessTitle('msfoole:master')
            ->setDescription('msfoole命令行工具')
            ->setHelp('msfoole是基于swoole的简易微服务框架')
            ->addArgument('task', InputArgument::REQUIRED, '可选择值为start、stop、reload')
            ->addArgument('env', InputArgument::OPTIONAL, '运行环境， 可选值为dev、online', 'dev')
            ->addArgument('mode', InputArgument::OPTIONAL, '守护运行， 可选值为i、d', 'i')
            ->addOption('server', 's', InputOption::VALUE_REQUIRED, '服务类型，可选值为server、http、socket', 'http');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        // step 1 对命令行参数进行校验
        $command = $input->getArgument('task');
        $env = $input->getArgument('env');
        $mode = $input->getArgument('mode');
        $server = $input->getOption('server');
        var_dump($command, $env, $mode, $server);
    }
}