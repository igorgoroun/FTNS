<?php

namespace Commands;

use Entity\Config;
use Entity\LocalDB;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Yaml\Yaml;

class EchoSubCommand extends Command
{
    private $ftnconfig;
    private $logger;

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
            ->setName('echomail:subscribe')
            ->setDescription('Subscribe point to areas')
            ->addArgument('point', InputArgument::REQUIRED, "RFC-style point address")
            ->addArgument('echo', InputArgument::IS_ARRAY | InputArgument::REQUIRED, "List of EchoAreas (space separated), or 'all' for subscribe ALL areas")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // get areas list
        $sql = "SELECT id,name FROM echoarea";
        $dbm = $this->db->prepare($sql);
        $dbm->execute();
        $db_areas = array();
        foreach($dbm->fetchAll() as $ad) {
            $db_areas[$ad['name']] = $ad['id'];
        }
        // get point db data
        $sql = "SELECT id,ifaddr,classic FROM point WHERE ifaddr=:pnt";
        $dbm = $this->db->prepare($sql);
        $dbm->bindValue("pnt",$input->getArgument('point'));
        $dbm->execute();
        $pnt = $dbm->fetchAll()[0];
        //print_r($pnt);

        $local_areas = array('areas'=>$this->ftnconfig->areas);

        $area_file_changed = false;
        $areas_to_subscribe = array();

        if ($input->getArgument('echo')[0] == "all") {
            foreach ($local_areas['areas'] as $area=>$data) {
                if (!array_key_exists($input->getArgument('point'),$data['subscribers'])) {
                    $area_file_changed = true;
                    $areas_to_subscribe [] = $area;
                } else {
                    $output->writeln("<info>".$input->getArgument('point')." already subscribed to ".$area."</info>");
                }
            }
        } else {
            foreach ($input->getArgument('echo') as $newarea) {
                if (array_key_exists($newarea, $local_areas['areas'])) {
                    if (!array_key_exists($input->getArgument('point'),$local_areas['areas'][$newarea]['subscribers'])) {
                        $area_file_changed = true;
                        $areas_to_subscribe [] = $newarea;
                    } else {
                        $output->writeln("<info>".$input->getArgument('point')." already subscribed to ".$newarea."</info>");
                    }
                } else {
                    $output->writeln("<error>Unexistent echoarea ".$newarea."</error>");
                }
            }
        }
        //print_r($areas_to_subscribe);
        // Add areas to point
        foreach ($areas_to_subscribe as $area) {
            $local_areas['areas'][$area]['subscribers'][$pnt['ifaddr']] = intval($pnt['classic']);
        }
        // Save areas file
        if ($area_file_changed && count($areas_to_subscribe)>0) {
            echo "da";
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
        // Save DB subscription
        foreach ($areas_to_subscribe as $area) {
            $sql = "INSERT INTO subscription SET point_id=:pid, area_id=:aid, created=NOW()";
            $ibm = $this->db->prepare($sql);
            $ibm->bindValue("pid", $pnt['id']);
            $ibm->bindValue("aid", $db_areas[$area]);
            $ibm->execute();
        }

    }

}
