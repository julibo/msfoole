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
use Julibo\Msfoole\Interfaces\Server as SwooleServer;
use Swoole\Process;

class InitialServer extends Command implements Console
{
    private $input;
    private $output;
    private $action;
    private $env;
    private $plan;
    private $level;
    private $mix;
    private $daemon;

    public function __construct($name = null)
    {
        parent::__construct($name);
    }

    public function configure()
    {
        $this->setName('msfoole')
            ->setProcessTitle('msfoole:mainer')
            ->setDescription('msfoole命令行工具')
            ->setHelp('msfoole是基于swoole的简易微服务框架')
            ->addArgument('action', InputArgument::REQUIRED, '执行操作：可选择值为start、stop、reload、restart')
            ->addOption('env', 'e', InputOption::VALUE_REQUIRED, '运行环境：可选值为dev、test、demo、online', 'dev')
            ->addOption('plan', 'g', InputOption::VALUE_NONE, '服务架构：group')
            ->addOption('level', 'm', InputOption::VALUE_NONE, '服务层级：master')
            ->addOption('mix', 's', InputOption::VALUE_NONE, '附加服务：websocket')
            ->addOption('daemon', 'd', InputOption::VALUE_NONE, '运行模式：守护模式');
    }

    /**
     * 输出样式：comment, info, error, question
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|mixed|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->init();
        $action = $this->action;
        $this->$action();
    }

    public function init()
    {
        $this->plan = $this->input->getOption('plan');
        $this->level = $this->input->getOption('level');
        $this->mix = $this->input->getOption('mix');
        $this->daemon = $this->input->getOption('daemon');

        $action = $this->input->getArgument('action');
        if (!in_array($action, ['start', 'stop', 'restart','reload'])) {
            $this->output->writeln("<error>执行操作：可选择值为start（启动）、stop（停止）、restart（重启）、reload（重载）</error>");
            exit(1);
        } else {
            $this->action = $action;
        }
        $env = $this->input->getOption('env');
        if (!in_array($env, ['dev', 'test', 'demo', 'online'])) {
            $this->output->writeln("<error>运行环境：可选值为dev（开发环境）、test（测试环境）、demo（演示环境）、online（生产环境）</error>");
            exit(2);
        } else {
            $this->env = $env;
        }
        $this->setEnvConfig($this->env);

        // 避免PID混乱
        $port = $this->getPort();
        Config::set('msfoole.option.pid_file', SERVER_PID . '_' .  $port);
    }

    /**
     * 根据环境加载对应的环境配置文件
     * @param $env
     */
    private function setEnvConfig($env)
    {
        $file = CONF_PATH . 'php-' . strtolower($env) . ENV_EXT;
        if (file_exists($file)) {
            Config::loadFile($file, ENV_EXT);
        }
    }

    private function getHost()
    {
        $host = Config::get('application.host') ?: '0.0.0.0';
        return $host;
    }

    private function getPort()
    {
        $port = Config::get('application.port') ?: 9555;
        return $port;
    }

    /**
     * 判断PID是否在运行
     * @param $pid
     * @return bool
     */
    private function isRunning($pid)
    {
        if (empty($pid)) {
            return false;
        }
        return Process::kill($pid, 0);
    }

    private function getMasterPid()
    {
        $pidFile = Config::get('application.pid_file');
        if (is_file($pidFile)) {
            $masterPid = (int) file_get_contents($pidFile);
        } else {
            $masterPid = false;
        }
        return $masterPid;
    }

    private function removePid()
    {
        $pidFile = Config::get('application.pid_file');
        if (is_file($pidFile)) {
            unlink($pidFile);
        }
    }

    /**
     * 服务启动
     */
    private function start()
    {
        $pid = $this->getMasterPid();
        if ($this->isRunning($pid)) {
            $this->output->writeln("<error>msfoole server process is already running.</error>");
            return false;
        }

        $this->output->writeln("<info>Starting msfoole server...</info>");

        $host = $this->getHost();
        $port = $this->getPort();
        $mode = Config::get('msfoole.mode') ?: SWOOLE_PROCESS;
        $type = Config::get('msfoole.type') ?: SWOOLE_SOCK_TCP;

        $option = Config::get('msfoole.option') ?: [];
        $option['daemonize'] = $this->daemon;
        $option['log_file'] = LOG_PATH . ($option['log_file'] ?: 'msfoole.log');
        $option['request_slowlog_file'] = LOG_PATH . ($option['request_slowlog_file'] ?: 'trace.log');

        $ssl = !empty(Config::get('msfoole.ssl')) || !empty($option['open_http2_protocol']);
        if ($ssl) {
            $type = SWOOLE_SOCK_TCP | SWOOLE_SSL;
        }

        if ($this->plan == false) { // 单机模式
            $swooleClass = '\\Julibo\\Msfoole\\Server\\AloneHttpServer';
        } else {
            if ($this->level == false) { // 集群客户端
                $swooleClass = '\\Julibo\\Msfoole\\Server\\ClientHttpServer';
            } else {  // 集群主机
                $swooleClass = '\\Julibo\\Msfoole\\Server\\MainHttpServer';
            }
        }
        if (!class_exists($swooleClass)) {
            $this->output->writeln("<error>Server Class Not Exists : {$swooleClass}</error>");
            return false;
        }
        $swoole = new $swooleClass($host, $port, $mode, $type, $option, $this->mix);
        if (!$swoole instanceof SwooleServer) {
            $this->output->writeln("<error>Server Class Must extends \\Julibo\\Msfoole\\Interfaces\\Server</error>");
            return false;
        }
        $this->output->writeln("<comment>msfoole server started: <{$host}:{$port}></comment>");
        $this->output->writeln('You can exit with <info>`CTRL-C`</info>');

        // 启动服务
        $swoole->start();
    }

    /**
     * 服务停止
     */
    private function stop()
    {
        $pid = $this->getMasterPid();
        if (!$this->isRunning($pid)) {
            $this->output->writeln('<error>no msfoole server process running.</error>');
        }
        $this->output->writeln('<comment>Stopping msfoole server...</comment>');
        Process::kill($pid, SIGTERM);
        $this->removePid();
        $this->output->writeln('<comment> > sucess<comment>');
    }


    /**
     * 重启服务
     */
    private function restart()
    {
        $pid = $this->getMasterPid();
        if ($this->isRunning($pid)) {
            $this->stop();
        }
        $this->start();
    }

    /**
     * 服务重载
     */
    private function reload()
    {
        $pid = $this->getMasterPid();
        if (!$this->isRunning($pid)) {
            $this->output->writeln('<error>no msfoole server process running.</error>');
            return false;
        }
        $this->output->writeln('<comment>Reloading msfoole server...</comment>');
        Process::kill($pid, SIGUSR1);
        $this->output->writeln('<comment> > sucess<comment>');
    }
}
