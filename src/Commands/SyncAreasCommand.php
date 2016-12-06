<?php

namespace Commands;

use Entity\Config;
use Entity\LocalDB;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class SyncAreasCommand extends Command
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
            ->setName('sync:areas')
            ->setDescription('FTNW: Sync areas with ftnw database')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger->info("{time} Syncing areas with database",['time'=>date('r')]);
        // query
        $sql = "SELECT id,name FROM echoarea";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $db_areas = array();
        foreach($stmt->fetchAll() as $adb) {
            $db_areas[$adb['name']] = $adb['id'];
        }
        $local_areas = array('areas'=>$this->ftnconfig->areas);
        $areas_to_add = array();
        foreach ($local_areas['areas'] as $area => $adata) {
            if (!array_key_exists($area,$db_areas)) {
                $areas_to_add []= $area;
            }
        }
        // Update areas list in DB
        if (count($areas_to_add)>0) {
            foreach ($areas_to_add as $area_name) {
                $sql = "INSERT into echoarea set name=:aname";
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue("aname",$area_name);
                $stmt->execute();
                $this->logger->notice("Area {$area_name} created in DB");
            }
        }
    }

}
