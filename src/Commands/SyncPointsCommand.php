<?php

namespace Commands;

use Entity\Config;
use Entity\LocalDB;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class SyncPointsCommand extends Command
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
            ->setName('sync:points')
            ->setDescription('FTNW: Sync points registered in ftnw')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger->info("@{time} Syncing points from database",['time'=>date('Y-m-d H:i:s')]);
        // query
        $sql = "SELECT id,classic,ifaddr FROM point WHERE active=0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $points = $stmt->fetchAll();
        //print_r($points);
        //print_r($this->ftnconfig->points);
        $local_points = array('points'=>$this->ftnconfig->points);
        $update_points_file = false;
        $update_points_ids = array();
        foreach ($points as $point) {
            if (!array_key_exists($point['ifaddr'],$local_points)) {
                $local_points['points'][$point['ifaddr']] = intval($point['classic']);
                $update_points_ids []= $point['id'];
                $update_points_file = true;
            }
        }
        //print_r($local_points);
        if ($update_points_file) {
            // Make backup
            if (copy($this->ftnconfig->points_file,$this->ftnconfig->points_file."-".date('Ymd-His').".bak")) {
                $this->logger->notice("Point list backup created");
            } else {
                $this->logger->warning("Can't backup points list");
            }
            // Update local points list
            if ($pf = fopen($this->ftnconfig->points_file,"w+")) {
                if (fwrite($pf,Yaml::dump($local_points))) {
                    $this->logger->notice("Points list updated");
                } else {
                    $this->logger->warning("Can't save points list");
                }
                fclose($pf);
            } else {
                $this->logger->warning("Can't create points list file");
            }
            // Activate users in db
            if ($this->activateRemotePoints($update_points_ids)) {
                $this->logger->notice("Points db updated");
            } else {
                $this->logger->notice("Points db was not updated");
            }
            // Autosubscribe new points
            if (count($this->ftnconfig->point_autosubscribe)>0) {
                $local_areas = array("areas"=>$this->ftnconfig->areas);
                // Get areas_db ids
                $sql = "SELECT id,name FROM echoarea";
                $dbm = $this->db->prepare($sql);
                $dbm->execute();
                $db_areas = array();
                foreach($dbm->fetchAll() as $ad) {
                    $db_areas[$ad['name']] = $ad['id'];
                }
                foreach ($this->ftnconfig->point_autosubscribe as $area) {
                    if (array_key_exists($area,$local_areas['areas'])) {
                        foreach ($points as $point) {
                            $local_areas['areas'][$area]['subscribers'][$point['ifaddr']] = intval($point['classic']);
                            // DB subscription
                            $sql = "INSERT INTO subscription SET point_id=:pid, area_id=:aid, created=NOW()";
                            $ibm = $this->db->prepare($sql);
                            $ibm->bindValue("pid",$point['id']);
                            $ibm->bindValue("aid",$db_areas[$area]);
                            $ibm->execute();
                        }
                    }
                }
                // dump areas file
                if ($af = fopen($this->ftnconfig->areas_file,"w+")) {
                    if (fwrite($af,Yaml::dump($local_areas))) {
                        $this->logger->notice("Areas list updated");
                    } else {
                        $this->logger->warning("Can't save areas list");
                    }
                    fclose($af);
                } else {
                    $this->logger->warning("Can't create areas list file");
                }

            }
            // Update binkd points file
            $sql = "SELECT ftnaddr,plain_password FROM point WHERE active=1 and classic=1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $cl_points = $stmt->fetchAll();
            if ($fb = fopen($this->ftnconfig->binkd_points_file,"w+")) {
                foreach ($cl_points as $point) {
                    fwrite($fb,"node ".$point['ftnaddr']." - ".$point['plain_password']."\n");
                }
                fclose($fb);
            } else {
                $this->logger->warning("Can't create binkd points file");
            }


        }
    }

    private function activateRemotePoints($ids=array()) {
        if (count($ids)>0) {
            $sql = "UPDATE point set active=1 where id IN (?)";
            $stmt = $this->db->executeQuery($sql,
                array($ids),
                array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY)
            );

            if ($stmt) {
                return true;
            } else return false;

        } else {
            return false;
        }
    }
}
