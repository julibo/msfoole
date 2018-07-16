<?php
namespace Julibo\Msfoole\Interfaces;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface Console
{
    /**
     * 进程配置
     */
    public function configure();

    /**
     * 进程执行
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return mixed
     */
    public function execute(InputInterface $input, OutputInterface $output);
}