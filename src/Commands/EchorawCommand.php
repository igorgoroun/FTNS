<?php

namespace Commands;

use Entity\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\StreamOutput;

class EchorawCommand extends Command
{
    private $ftnconfig;
    private $logger;

    public function __construct($name, Config $config, ConsoleLogger $logger)
    {
        parent::__construct($name);
        $this->ftnconfig = $config;
        $this->logger = $logger;
    }

    protected function configure()
    {
        $this
            ->setName('echomail:spool')
            ->setDescription('Get packet from STDIN')
            ->addArgument('file',InputArgument::OPTIONAL,'Input file')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (0 === ftell(STDIN)) {
            $package = '';
            while (!feof(STDIN)) {
                $package .= fread(STDIN, 1024);
            }
            $parr = explode("\n",$package);
            $msg = false;
            $total = 0;
            foreach($parr as $line) {
                if(preg_match("/^(\#\!)\ (rnews)\ (\d{1,})$/",$line,$rndata)) {
                    if (is_resource($msg)) unset($msg);
                    $msg = new StreamOutput(fopen($this->ftnconfig->echomail_spool.substr(md5(time().$rndata[3]), 0, 8).'.msg','a+',false));
                    $total++;
                    $skip = true;
                } else {
                    $skip = false;
                }

                if (!$skip) {
                    $msg->writeln($line);
                }
            }
            if (is_resource($msg)) unset($msg);
            $this->logger->info("{total} echomail spooled",['total'=>$total]);
        } else {
            // Log usage error
            $this->logger->error("No stdin input found");
        }
    }
}
