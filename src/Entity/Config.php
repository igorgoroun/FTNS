<?php
/**
 * Created by PhpStorm.
 * User: snake
 * Date: 8/8/16
 * Time: 22:00
 */

namespace Entity;


use Symfony\Component\Yaml\Yaml;

class Config
{
    public $echomail_spool;
    public $netmail_spool;
    public $node;
    public $node_rfc;
    public $node_short;
    public $origin;
    public $tearline;
    public $log_file;
    public $ftnw;
    public $uplink;
    public $route;
    public $ifmail;
    public $areas;
    public $areas_file;
    public $points_file;
    public $binkd_points_file;
    public $points;
    public $point_autosubscribe;
    public $areafix_help_file;
    public $version;

    public function __construct($yaml_file)
    {
        $yaml = Yaml::parse(file_get_contents($yaml_file));
        foreach($yaml as $item => $value) {
            $this->$item = $value;
        }
        if ($this->areas_file && is_file($this->areas_file)) {
            $yaml = Yaml::parse(file_get_contents($this->areas_file));
            foreach($yaml as $item => $value) {
                $this->$item = $value;
            }
        }
        if ($this->points_file && is_file($this->points_file)) {
            $yaml = Yaml::parse(file_get_contents($this->points_file));
            foreach($yaml as $item => $value) {
                $this->$item = $value;
            }
        }
    }

}