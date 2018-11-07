<?php
namespace Julibo\Msfoole\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Julibo\Msfoole\Interfaces\Console;
use Julibo\Msfoole\Config;

class MsfooleHttp extends Command implements Console
{

    public function __construct(string $conf)
    {
        parent::__construct();

    }
    
    public function init(string $conf)
    {
        if (!empty($conf) && is_dir($conf)) {
            $Config = new Config($conf, '.ini');
            // 读取配置文件夹下*.ini文件，导入系统配置

        } else {
            exit("必须传入一个配置目录");
        }

    }

    public function configure()
    {

    }

    public function execute(InputInterface $input, OutputInterface $output)
    {

    }
}

