<?php
/**
 * Created by PhpStorm.
 * User: Snake
 * Date: 11.08.2016
 * Time: 17:29
 */

namespace Entity;


class IfmailPacket
{
    private $ngroup = false;
    private $from=false;
    private $to=false;
    private $xto=false;
    private $date=false;
    private $subject=false;
    private $msgid_rfc = false;
    private $r_mime = "1.0";
    private $r_ctype = "text/plain; charset=x-cp866";
    private $r_tenc = "8bit";
    private $msgid = false;
    private $area = false;
    private $tz=false;
    private $charset="CP866";
    private $origin=false;
    private $tearline=false;
    private $pid = false;
    private $tid = false;
    private $via = false;


    private $body;

    private $headers_list = array(
        'from'=>'From',
        'ngroup'=>'Newsgroups',
        'to'=>'To',
        'xto'=>'X-Comment-To',
        'date'=>'Date',
        'subject'=>'Subject',
        'msgid'=>'X-FTN-MSGID',
        'msgid_rfc'=>'Message-ID',
        'area'=>'X-FTN-AREA',
        'tz'=>'X-FTN-TZUTC',
        'origin'=>'X-FTN-Origin',
        'tearline'=>'X-FTN-Tearline',
        'charset'=>'X-FTN-CHRS',
        'pid'=>'X-FTN-PID',
        'tid'=>'X-FTN-TID',
        'via'=>'X-FTN-Via',
        'r_mime'=>'Mime-Version',
        'r_ctype'=>'Content-Type',
        'r_tenc'=>'Content-Transfer-Encoding',
    );
    private $headers = array();

    public function __construct(Config $config) {
        $sd = new \DateTime();
        $this->date = $sd->format('r');
        $this->tz = $sd->format('O');
        if ($config->version) {
            $this->tid = $config->version;
            $this->via = $config->version;
        }
    }

    public function getTo() {
        return $this->to;
    }

    public function createHeaders() {
        if ($this->from && $this->subject) {
            foreach ($this as $hdr => $value) {
                if (!in_array($hdr,['body','headers','headers_list']) && $this->$hdr) {
                    $this->addHeader($this->headers_list[$hdr].": ".$this->$hdr);
                }
            }
            return true;
        } else return false;
    }

    public function getPacket($iconv=array('f'=>'UTF8','t'=>'CP866')) {

        if (strlen($this->body)>0 && $this->createHeaders()) {
            $packet = "";
            $packet .= implode("\n",$this->headers);
            $packet .= "\n\n";
            $packet .= $this->body;
            $packet .= "\n\n";
            return iconv($iconv['f'], $iconv['t'], $packet);
        } else return false;
    }

    public function addHeader($hdr=false) {
        if ($hdr) {
            $this->headers []= $hdr;
        } else return false;

    }


    /**
     * @param boolean $from
     */
    public function setFrom($from_name=false,$from_rfc=false)
    {
        if (!$from_rfc) throw new \ErrorException("from_rfc not set");
        if (!$from_name) {
            $this->from = $from_rfc;
        } else {
            $msgid_hash = substr(md5(time()),0,8);
            $this->msgid = Message::makeFTN($from_rfc)." ".$msgid_hash;
            $this->msgid_rfc = "<".$msgid_hash."@".$from_rfc.">";
            $this->from = $from_name." <".$this->parseFromName($from_name)."@".$from_rfc.">";
        }
    }

    private function parseFromName($name) {
        $pattern = ["_"," ","'","\""];
        $replace = [".",".","",""];
        return mb_strtolower(str_replace($pattern,$replace,$name));
    }

    /**
     * @param boolean $to
     */
    public function setTo($to_name,$to_rfc)
    {
        if (!$to_rfc) throw new \ErrorException("from_rfc not set");
        if (!$to_name) {
            $this->to = $to_rfc;
        } else {
            $this->to = "\"".$to_name."\" <".$this->parseFromName($to_name)."@".$to_rfc.">";
        }
    }

    public function setXTo($to_name) {
        $this->xto = $to_name;
    }

    public function setArea($area_name) {
        $this->area = mb_strtoupper($area_name);
        $this->ngroup = mb_strtolower($area_name);
    }

    /**
     * @param boolean $subject
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
    }

    /**
     * @param boolean $origin
     */
    public function setOrigin($origin)
    {
        $this->origin = $origin;
    }

    /**
     * @param boolean $tearline
     */
    public function setTearline($tearline)
    {
        $this->tearline = $tearline;
    }

    /**
     * @param boolean $tz
     */
    public function setTz($tz)
    {
        $this->tz = $tz;
    }

    /**
     * @param boolean $date
     */
    public function setDate($date)
    {
        $this->date = $date;
    }

    /**
     * @param boolean $charset
     */
    public function setCharset($charset)
    {
        $this->charset = $charset;
    }

    /**
     * @param string $pid
     */
    public function setPid($pid)
    {
        $this->pid = $pid;
    }

    /**
     * @param string $tid
     */
    public function setTid($tid)
    {
        $this->tid = $tid;
    }

    /**
     * @param mixed $body
     */
    public function setBody($body)
    {
        $this->body = $body;
    }


}