<?php
namespace Julibo\Msfoole;

//use Symfony\Component\Console\Command\Command;
//use Symfony\Component\Console\Input\InputInterface;
//use Symfony\Component\Console\Output\OutputInterface;
//use Symfony\Component\Console\Input\InputArgument;
//use Julibo\Msfoole\Interfaces\Console;

class Server
{
    public function test()
    {
        echo time();
    }
//    public function __construct()
//    {
//        parent::__construct();
//    }
//
//    public function configure()
//    {
//        $this->setName('server')
//            ->setDescription('运行kafka同步服务')
//            ->setProcessTitle("swoole-server_main")
//            ->setHelp('该命令为kafka数据同步和数据推送服务')
//            ->addArgument('commands', InputArgument::REQUIRED, '命令参数，可选值为start、reload、restart、stop');
//    }
//
//    public function execute(InputInterface $input, OutputInterface $output)
//    {
//        $param = $input->getArgument('commands');
//        switch ($param) {
//            case "start":
//                echo 1;
//                break;
//            case "reload":
//                echo 2;
//                break;
//            case "restart":
//                echo 3;
//                break;
//            case "stop":
//                echo 4;
//                break;
//            default:
//                $output->writeln([
//                    '命令参数，可选值为start、reload、restart、stop'
//                ]);
//                break;
//        }
//    }
}