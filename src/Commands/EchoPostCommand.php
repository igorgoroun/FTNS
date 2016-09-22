<?php

namespace Commands;

use Entity\Config;
use Entity\IfmailPacket;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class EchoPostCommand extends Command
{
    private $ftnconfig;
    private $logger;

    public function __construct($name, Config $config, ConsoleLogger $logger)
    {
        $this->ftnconfig = $config;
        $this->logger = $logger;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setName('echomail:post')
            ->setDescription('Post short message to area')
            ->addArgument('echo', InputArgument::REQUIRED, "Echoarea to post message to")
            ->addOption('fromname','fn', InputOption::VALUE_REQUIRED, "Name from, default is ".$this->ftnconfig->node_sysop)
            ->addOption("fromaddr","fa", InputOption::VALUE_REQUIRED, "From FTN/RFC-address, default is ".$this->ftnconfig->node_rfc)
            ->addOption("toname", "tn", InputOption::VALUE_REQUIRED, "To name, default All")
            ->addOption("subject","s", InputOption::VALUE_REQUIRED, "Message subject")
            ->addOption("message","m", InputOption::VALUE_REQUIRED, "Message text")
            ->addOption("tearline","t", InputOption::VALUE_REQUIRED, "Tearline, default is ".$this->ftnconfig->tearline)
            ->addOption("origin","o", InputOption::VALUE_REQUIRED, "Origin, default is ".$this->ftnconfig->origin)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $area_to_post = mb_strtolower($input->getArgument('echo'));
        if (!array_key_exists($area_to_post,$this->ftnconfig->areas)) {
            throw new \ErrorException("Unexistent area");
        }

        $ifp = new IfmailPacket($this->ftnconfig);

        // from
        if ($input->getOption('fromname') && $input->getOption('fromaddr')) {
            $ifp->setFrom($input->getOption('fromname'), $input->getOption('fromaddr'));
        } else {
            $ifp->setFrom($this->ftnconfig->node_sysop,$this->ftnconfig->node_rfc);
        }
        // area
        $ifp->setArea($input->getArgument('echo'));
        // to name
        if ($input->getOption('toname')) {
            $ifp->setXTo($input->getOption('toname'));
        } else {
            $ifp->setXTo('All');
        }
        // subject
        if ($input->getOption('subject')) {
            $ifp->setSubject($input->getOption('subject'));
        } else {
            $ifp->setSubject("undefined");
        }
        // message body
        if ($input->getOption('message')) {
            $ifp->setBody($input->getOption('message'));
        }
        // tearline
        if ($input->getOption('tearline')) {
            $ifp->setTearline($input->getOption('tearline'));
        } else {
            $ifp->setTearline($this->ftnconfig->tearline);
        }
        // origin
        if ($input->getOption('origin')) {
            $ifp->setOrigin($input->getOption('origin'));
        } else {
            $ifp->setOrigin($this->ftnconfig->origin);
        }

        //print_r($ifp);

        if ($packet = $ifp->getPacket()) {
            if (!$mf = fopen($this->ftnconfig->echomail_spool."/lc_".substr(md5(time()),0,8).".msg","w+")) {
                throw new \ErrorException("Cannot create message file in echospool");
            }
            fwrite($mf,$packet,strlen($packet));
            fclose($mf);
        }
        //print $packet;
        //$ifnews = new Process($this->ftnconfig->ifmail." -x 8 -n -r ".$this->ftnconfig->areas[$area_to_post]['uplink'],null,null,$ifp->getPacket());
        //$ifnews->run();
    }


}
