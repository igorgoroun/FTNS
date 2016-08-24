<?php

namespace Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class TestdbCommand extends Command
{

    protected function configure()
    {
        $this
            ->setName('connect')
            ->setDescription('Test db connect')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = new \Doctrine\DBAL\Configuration();
        $params = array(
    'dbname' => 'fidonews',
    'user' => 'snake',
    'password' => 'dreams',
    'host' => 'localhost',
    'driver' => 'pdo_mysql',
        );
        $db = \Doctrine\DBAL\DriverManager::getConnection($params,$config);
    }        
}
