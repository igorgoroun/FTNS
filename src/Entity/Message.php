<?php
/**
 * Created by PhpStorm.
 * User: snake
 * Date: 8/9/16
 * Time: 22:36
 */

namespace Entity;


class Message
{
    public $srcFull;
    public $srcHeader;
    public $messageid;
    public $body;
    public $date;
    public $noroute = array();
    public $echoareas = array();
    public $charset = 'CP866';
    public $to_name = 'All';
    public $to_ftn;
    public $to_rfc;
    public $to_ifaddr;
    public $from_name;
    public $from_rfc;
    public $from_ftn;
    public $from_ifaddr;
    public $ftnMSGID;
    public $ftnREPLY;
    public $ftnPID;
    public $ftnTID;
    public $ftnTZ;
    public $tearline;
    public $origin;
    public $avatar;
    public $location;
    public $subject;
    public $kludges = array();
    public $via = array();

    public function __construct()
    {
      $this->date = date('r');
    }

    public static function makeRFC($name=false,$ftn) {
        if (preg_match("/^(\d{1})\:(\d{1,4})\/(\d{1,4})\.(\d{1,4})/",$ftn,$data)) {
            $addr = "p".$data[4].".f".$data[3].".n".$data[2].".z".$data[1].".fidonet.org";
            //return $addr;
        } elseif (preg_match("/^(\d{1})\:(\d{1,4})\/(\d{1,4})/",$ftn,$data)) {
            $addr = "f".$data[3].".n".$data[2].".z".$data[1].".fidonet.org";
            //return $addr;
        } else return false;
        if ($name) {
            $rfc_name = str_replace(['_',' ',"'","\""],['.','.','',''],$name);
            return $rfc_name."@".$addr;
        } else {
            return $addr;
        }

    }
    public static function makeFTN($rfc) {
        if (strpos($rfc,'@') === false) {
            $ad = $rfc;
        } else {
            list($fr,$ad) = explode("@",$rfc);
        }
        if (preg_match("/^p(\d{1,4})\.f(\d{1,4})\.n(\d{1,4})\.z(\d{1})\.(.+)/",$ad,$data)) {
            return $data[4].":".$data[3]."/".$data[2].".".$data[1];
        } elseif (preg_match("/^f(\d{1,4})\.n(\d{1,4})\.z(\d{1})\.(.+)/",$ad,$data)) {
            return $data[3].":".$data[2]."/".$data[1];
        } else {
            return false;
        }
    }
}