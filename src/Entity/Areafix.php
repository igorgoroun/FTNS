<?php
/**
 * Created by PhpStorm.
 * User: snake
 * Date: 8/23/16
 * Time: 20:13
 */

namespace Entity;


use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Yaml\Yaml;


class Areafix
{
    private $ftnconfig;
    private $logger;
    private $requests=array('help','list');
    private $commands=array('+','-');
    private $to_subscr=array();
    private $to_unsub=array();
    private $replies=array();

    public function __construct(Config $config, ConsoleLogger $log) {
        $this->ftnconfig = $config;
        $this->logger = $log;
        $this->db = (new LocalDB($config))->getDBConnection();
    }

    public function getReplies () {
        return $this->replies;
    }

    public function processMessage(Message $message) {
        $lines = explode("\n",$message->body);
        foreach($lines as $str) {
            if (preg_match("/^\%(.+)$/",$str,$data)) {
                $this->logger->info("{time} areafix request {req} from {sender}",['time'=>date('r'),'req'=>$data[1],'sender'=>$message->from_ftn]);
                if (in_array(trim($data[1]),$this->requests)) {
                    $this->doRequest(trim($data[1]),$message->from_ifaddr);
                }
            }
            if (preg_match("/^(\-|\+)(.+)$/",$str,$data)) {
                $this->logger->info("{time} areafix command {com_type} {com_text} from {sender}",['time'=>date('r'),'com_type'=>$data[1],'com_text'=>$data[2],'sender'=>$message->from_ftn]);
                if (in_array(trim($data[1]),$this->commands)) {
                    $this->doCommand(trim($data[1]),trim($data[2]),$message->from_ifaddr);
                }
            }
        }
        // do commands
        if (count($this->to_subscr)>0 || count($this->to_unsub)>0) {
            $this->makeSubscriptions($this->to_subscr,$this->to_unsub,$message->from_ifaddr);
        }
    }

    public function makeSubscriptions($areas_to_subscribe,$areas_to_unsub,$point) {
        $areas_local = array('areas'=>$this->ftnconfig->areas);
        // unsubscribe
        foreach ($areas_to_unsub as $area_u) {
            if (array_key_exists($area_u,$areas_local['areas'])) {
                if (array_key_exists($point,$areas_local['areas'][$area_u]['subscribers'])) {
                    unset($areas_local['areas'][$area_u]['subscribers'][$point]);
                    $this->replies['unsubscribe'][] = "Unsubscribed from ".$area_u;
                } else $this->replies['error'][] = "You are not subscribed to ".$area_u;
            } else $this->replies['error'][] = "Unexistent area ".$area_u;
        }
        // subscribe
        foreach ($areas_to_subscribe as $area_s) {
            if (array_key_exists($area_s,$areas_local['areas'])) {
                if (!array_key_exists($point,$areas_local['areas'][$area_s]['subscribers'])) {
                    $areas_local['areas'][$area_s]['subscribers'][$point] = $this->ftnconfig->points[$point];
                    $this->replies['subscribe'][] = "Subscribed to ".$area_s;
                } else $this->replies['error'][] = "You are already subscribed to ".$area_s;
            } else $this->replies['error'][] = "Unexistent area ".$area_s;
        }
        // save file
        if ($af = fopen($this->ftnconfig->areas_file,"w+")) {
            if (fwrite($af,Yaml::dump($areas_local))) {
                $this->logger->notice("{time} Areas list updated",['time'=>date('r')]);
            } else {
                $this->logger->warning("Can't save areas list");
            }
            fclose($af);
        } else {
            $this->logger->warning("Can't create areas list file");
        }
    }

    private function doHelp() {
        if (!array_key_exists('help',$this->replies)) {
            if ($hf = file_get_contents($this->ftnconfig->areafix_help_file)) {
                $this->replies['help'][] = $hf;
            } else {
                $this->replies['error'][] = "Cannot send help-file";
            }
        }
    }

    private function doList($point) {
        if (!array_key_exists('list',$this->replies)) {
            foreach ($this->ftnconfig->areas as $area=>$data) {
                $aline = $area;
                if (array_key_exists($point,$data['subscribers'])) {
                    $aline .= " [+]";
                }
                $this->replies['list'][] = $aline;
            }

        }
    }

    private function doCommand($com_type,$com_text,$point) {
        switch ($com_type) {
            case '+':
                //$this->doSubscribe($com_text,$point);
                $this->to_subscr[] = $com_text;
                break;
            case '-':
                $this->to_unsub[] = $com_text;
                //$this->doUnsubscribe($com_text,$point);
                break;
        }
    }

    private function doRequest($req,$point) {
        switch ($req) {
            case 'help':
                $this->doHelp();
                break;
            case 'list':
                $this->doList($point);
                break;
        }
    }

}