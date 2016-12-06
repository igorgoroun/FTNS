<?php

namespace Commands;

use Entity\Config;
use Entity\LocalDB;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class StatDailyCommand extends Command
{
    private $ftnconfig;
    private $logger;
    private $db;

    public function __construct($name, Config $config, ConsoleLogger $logger)
    {
        parent::__construct($name);
        $this->ftnconfig = $config;
        $this->logger = $logger;
        $this->db = (new LocalDB($config))->getDBConnection();
    }

    protected function configure()
    {
        $this
            ->setName('stat:daily')
            ->setDescription('Shows daily stats')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // total points
        $sql = "SELECT count(*) as cnt, classic from point group by classic";
        $dbm = $this->db->prepare($sql);
        $dbm->execute();
        $stats_points = $dbm->fetchAll();
        $ptypes = [0=>'Web points',1=>'Classic points'];

        $output->writeln("POINTS");
        $tbl = new Table($output);
        $tbl->setHeaders(['Type','Total']);
        $tbl->addRow([$ptypes[$stats_points[0]['classic']],$stats_points[0]['cnt']]);
        $tbl->addRow([$ptypes[$stats_points[1]['classic']],$stats_points[1]['cnt']]);
        $tbl->render();

        /*
        $poster = $this->getApplication()->find('echomail:post');
        $args = [
            'command' => 'echomail:post',
            'echo' => 'snake.local',
            '--subject' => 'Stats test',
            '--message' => "Message test",
        ];
        $retCode = $poster->run(new ArrayInput($args), $output);
        print_r($retCode);
        */

        // echomail areas_cnt/sent/received
        /*
        $sql = "SELECT count(*),ea.name
            from message_cache
            left join echoarea ea on ea.id=group_id
            where cached_at>=:date_back
            and (h_from_ftn like :node_points or h_from_ftn=:node_addr)
            group by ea.id";
        $em = $this->db->prepare($sql);
        $em->bindValue('date_back',date('Y-m-d H:i:s',time()-24*60*60));
        $em->bindValue('node_addr',$this->ftnconfig->node);
        $em->bindValue('node_points',$this->ftnconfig->node.".%");
        $em->execute();
        $stats_areas_sent = $em->fetchAll();
        print_r($stats_areas_sent);
        */


    }
}
