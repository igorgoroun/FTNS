<?php
/**
 * Created by PhpStorm.
 * User: snake
 * Date: 8/23/16
 * Time: 20:13
 */

namespace Entity;


class Areafix
{
    private $ftnconfig;
    private $tpls=array();

    public function __construct(Config $config) {
        $this->ftnconfig = $config;
        $this->tpls['help'] = "%help";
        $this->tpls['list'] = "%list";
        $this->tpls['sub'] = "+";
        $this->tpls['unsub'] = "-";
    }

    public function processMessage(Message $message) {
        $lines = explode("\n",$message->body);

    }
}