<?php

namespace Commands;

use Entity\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\StreamOutput;

class MailrawCommand extends Command
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
            ->setName('netmail:spool')
            ->setDescription('Get netmail packet from STDIN and save to spool dir')
            ->addArgument('file',InputArgument::OPTIONAL,'Input file')
            ->addOption('from','f',InputOption::VALUE_REQUIRED, "From address")
            ->addOption('to','t',InputOption::VALUE_REQUIRED,"To address")
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
            $msg = new StreamOutput(fopen($this->ftnconfig->netmail_spool.substr(md5(time().$input->getOption('to')), 0, 8).'.msg','a+',false));
            foreach($parr as $line) {
                $msg->writeln($line);
            }
            if (is_resource($msg)) unset($msg);
            $this->logger->info("@{time} Netmail {from}->{to} spooled",['time'=>date('r'),'from'=>$input->getOption('from'),'to'=>$input->getOption('to')]);
        } else {
            // Log usage error
            $this->logger->error("No stdin input found");
        }
    }
}
