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

class EchoNewCommand extends Command
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
            ->setName('echomail:newarea')
            ->setDescription('Subscribe new area and configure it')
            ->addArgument('uplink', InputArgument::REQUIRED, "RFC-style uplink address")
            ->addArgument('echo', InputArgument::IS_ARRAY | InputArgument::REQUIRED, "List of EchoAreas (space separated)")
            ->addOption('nosubscribe','s', InputOption::VALUE_NONE, "Do not send subscribe message to uplink areafix")
            ->addOption("resubscribe","r",InputOption::VALUE_NONE, "Resend subscription message")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $local_areas = array('areas'=>$this->ftnconfig->areas);
        $area_file_changed = false;
        $areas_to_subscribe = array();
        foreach($input->getArgument('echo') as $newarea) {
            $newarea = mb_strtolower($newarea);
            if (array_key_exists($newarea, $local_areas['areas']) && !$input->getOption('resubscribe')) {
                $output->writeln(
                    "Area ".$newarea." already exists, linked from ".$local_areas['areas'][$newarea]['uplink']
                );
            } elseif (array_key_exists($newarea, $local_areas['areas']) && $input->getOption('resubscribe')) {
                $this->sendUplinkAreafix($input->getArgument('uplink'), ["+".$newarea]);
            } else {
                $area_file_changed = true;
                $local_areas['areas'][$newarea] = array('uplink'=>$input->getArgument('uplink'));
                $local_areas['areas'][$newarea]['subscribers'] = array();
                $areas_to_subscribe []= "+".$newarea;
            }
        }
        if ($area_file_changed) {
            if (!$input->getOption('nosubscribe') && count($areas_to_subscribe)>0) {
                $this->sendUplinkAreafix($input->getArgument('uplink'), $areas_to_subscribe);
            }
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

    }

    private function sendUplinkAreafix($uplink, $newareas) {
        $ifp = new IfmailPacket($this->ftnconfig);
        $ifp->setFrom('SysOp',$this->ftnconfig->node_rfc);
        $ifp->setTo('Areafix',$uplink);
        $ifp->setSubject($this->ftnconfig->uplink[$uplink]);
        $ifp->setBody(implode("\n",$newareas));
        $ifmail = new Process($this->ftnconfig->ifmail." -x 8 -r ".$uplink." areafix@".$uplink,null,null,$ifp->getPacket());
        $ifmail->run();
    }
}
