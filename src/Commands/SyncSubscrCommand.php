<?php

namespace Commands;

use Entity\Config;
use Entity\LocalDB;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class SyncSubscrCommand extends Command
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
            ->setName('sync:subscr')
            ->setDescription('FTNW: Sync points subscriptions to areas')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger->info("{time} Syncing points subscriptions",['time'=>date('r')]);

        // get areas ids
        $sql = "SELECT id,name FROM echoarea";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $db_areas = array();
        foreach($stmt->fetchAll() as $adb) {
            $db_areas[$adb['name']] = $adb['id'];
        }

        // get local areas data
        $local_areas = $this->ftnconfig->areas;

        // Get web-points existed subscriptions
        $sql = "SELECT s.id,p.ifaddr as point,e.name as area
                FROM subscription s
                LEFT JOIN point p ON (p.id=s.point_id)
                LEFT JOIN echoarea e ON (e.id=s.area_id)
                WHERE p.classic=0
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $db_subs = $stmt->fetchAll();

        // create unsubscribe array and create checking array
        $to_delete = array();
        $remote_areas = array();
        foreach ($db_subs as $sub) {
            // checking array
            $remote_areas[$sub['point']][] = $sub['area'];
            // create to_delete array
            if (!array_key_exists($sub['point'],$local_areas[$sub['area']]['subscribers'])) {
                $to_delete[] = $sub['id'];
            }
        }
        $this->logger->info("{time} Areas to delete {cnt}",['time'=>date('r'),'cnt'=>count($to_delete)]);

        // create subscribed
        $subscribed = array();
        foreach ($local_areas as $area=>$data) {
            foreach ($data['subscribers'] as $point=>$classic) {
                if ($classic == 0) {
                    $subscribed[$point][] = $area;
                }
            }
        }

        // subscribe points
        $to_subscribe = array();
        foreach ($subscribed as $point=>$areas) {
            foreach ($areas as $area) {
                if (!in_array($area, $remote_areas[$point])) {
                    $to_subscribe[$point][] = $db_areas[$area];
                }
            }
        }

        // DELETE SUBSCRIPTION
        if (count($to_delete)>0) {
            $sql = "DELETE FROM subscription WHERE id IN (?)";
            $this->db->executeQuery($sql,array($to_delete),array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY));

        }

        // CREATE SUBSCRIPTION
        if (count($to_subscribe)>0) {
            // get point db data
            $sql = "SELECT id,ifaddr FROM point WHERE classic=0";
            $dbm = $this->db->prepare($sql);
            $dbm->execute();
            $points = array();
            foreach ($dbm->fetchAll() as $key=>$pdata) {
                $points[$pdata['ifaddr']] = $pdata['id'];
            }
            foreach ($to_subscribe as $point=>$areas) {
                foreach ($areas as $area) {
                    $this->logger->info("{time} Subscribe {pnt} to {ar}",['time'=>date('r'),'pnt'=>$point,'ar'=>$area]);
                    $sql = "INSERT INTO subscription SET point_id=:pid, area_id=:aid, created=NOW()";
                    $ibm = $this->db->prepare($sql);
                    $ibm->bindValue("pid", $points[$point]);
                    $ibm->bindValue("aid", $area);
                    $ibm->execute();
                }
            }
        }
    }

}
