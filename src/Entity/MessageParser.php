<?php
/**
 * Created by PhpStorm.
 * User: snake
 * Date: 8/9/16
 * Time: 22:34
 */

namespace Entity;


class MessageParser
{
    private $headers;
    private $body;
    private $charset;
    private $tzutc;
    public $message;

    public function __construct($message_packet)
    {
        // Returned object
        $this->message = new Message();
        // Save source message, remove 'Received:'
        $this->message->srcFull = preg_replace("/Received\: .+\n\t.+\n/",'',$message_packet);
        //$this->message->srcFull = $message_packet;
        // Split headers/body
        $this->splitMessage($this->message->srcFull);
        // Parse cutted headers
        $this->parseHeaders();
    }

    private function splitMessage($message) {
        // Check charset
        if (preg_match("/X\-FTN\-CHRS\:\ (.+)/",$message,$chr_data)) {
            $this->charset = explode(" ",trim($chr_data[1]))[0];
        } else {
            $this->charset = 'CP866';
        }

        // Check timezone
        if (preg_match("/X\-FTN\-TZUTC\:\ (.+)/",$message,$chr_data)) {
            $inptz = trim($chr_data[1]);
            // check plus/minus
            if (substr($inptz,0,1)=='-' || substr($inptz,0,1)=='+') {
                $this->tzutc = $inptz;
            } else {
                $this->tzutc = "+".$inptz;
            }

        } else {
            $this->tzutc = NULL;
        }

        $message_utf = iconv($this->charset,'UTF-8',$message);
        // Split message
        list($this->headers,$this->body) = explode("\n\n",$message_utf,2);
    }

    private function parseHeaders() {
        $lines = explode("\n",$this->headers);
        foreach($lines as $line) {
            if (preg_match("/^([a-zA-Z0-9\-\_.]+)\:\ (.+)$/",$line,$ldata)) {
                $header = mb_strtolower(trim($ldata[1]));
                $data = trim($ldata[2]);
                switch ($header) {
                    case "path":
                        $this->parseNoRoute($data);
                        break;
                    case "newsgroups":
                        $this->parseEchoareas($data);
                        break;
                    case "x-ftn-chrs":
                        $this->parseCharset($data);
                        break;
                    case "x-comment-to":
                        $this->parseToName($data);
                        break;
                    case "x-ftn-reply":
                        $this->parseToFTN($data);
                        break;
                    case "from":
                        $this->parseFromNameRFC($data);
                        break;
                    case "x-ftn-sender":
                        $this->parseFromNameRFC($data);
                        break;
                    case "to":
                        $this->parseToRFC($data);
                        break;
                    case "x-ftn-msgid":
                        $this->parseFromFTN($data);
                        break;
                    case "date":
                        $this->parseDate($data);
                        break;
                    case "x-origin-date":
                        $this->parseDate($data);
                        break;
                    case "subject":
                        $this->parseSubject($data);
                        break;
                    case "x-ftn-pid":
                        $this->message->ftnPID = $data;
                        break;
                    case "x-ftn-tid":
                        $this->message->ftnTID = $data;
                        break;
                    /*case "x-ftn-tzutc":
                        $this->message->ftnTZ = $data;
                        break;*/
                    case "x-ftn-tearline":
                        $this->message->tearline = $data;
                        break;
                    case "x-ftn-origin":
                        $this->message->origin = $data;
                        break;
                    case "x-ftn-avatar":
                        $this->message->avatar = $data;
                        break;
                    case "x-ftn-location":
                        $this->message->location = $data;
                        break;
                    case "x-ftn-kludge":
                        $this->parseKludge($data);
                        break;
                    case "x-ftn-via":
                        $this->parseVia($data);
                        break;
                    case "message-id":
                        $this->message->messageid = $data;
                        break;
                }
            }
        }
        $this->message->srcHeader = $this->headers;
        $this->message->body = $this->body;
    }

    private function parseNoRoute($data) {
        preg_match_all("/f\d{1,}\.n\d{1,}/",$data,$values,PREG_PATTERN_ORDER);
        foreach ($values[0] as $route) {
            $this->message->noroute []= $route.".z2.fidonet.org";
        }
    }
    private function parseEchoareas($data) {
        $this->message->echoareas = explode(",",$data);
    }
    private function parseCharset($data) {
        $this->message->charset = $this->charset;
    }
    private function parseToName($data) {
        $this->message->to_name = $data;
    }
    private function parseToFTN($data) {
        list($this->message->to_ftn,$this->message->ftnREPLY) = explode(" ",$data);
        if ($this->message->ftnREPLY) {
            $this->message->to_rfc = $this->message->makeRFC($this->message->to_name,$this->message->to_ftn);
        }
    }
    private function parseToRFC($data) {
        if(preg_match("/^(.+)\ \<(.+)\>$/", $data, $todata)) {
            $this->message->to_name = $todata[1];
            $this->message->to_rfc = $todata[2];
            list($rfname,$this->message->to_ifaddr) = explode("@",$this->message->to_rfc);
            if ($toftn = $this->message->makeFTN($this->message->to_rfc)) {
                $this->message->to_ftn = $toftn;
            }
        }
    }
    private function parseFromNameRFC($data) {
        if (preg_match("/^(.+)\ \<(.+)\>$/", $data, $fromdata)) {
          $this->message->from_name = $fromdata[1];
          $this->message->from_rfc = $fromdata[2];
          list($rfname,$this->message->from_ifaddr) = explode("@",$this->message->from_rfc);
        } else {
          $this->message->from_name = $data;
          $this->message->from_rfc = "";
        }
    }
    private function parseFromFTN($data) {
        list($this->message->from_ftn,$this->message->ftnMSGID) = explode(" ",$data);
    }
    private function parseDate($data) {
        preg_match("/^(\w{3})\, (\d{1,2}) (\w{3}) (\d{4}) (\d{1,2}\:\d{1,2}\:\d{1,2}) (.+)$/",$data,$src_date);
        if ($this->tzutc != NULL) {
            $dd = new \DateTime();
            $ddtz = $dd->createFromFormat("O",$this->tzutc)->getTimezone();
        } else $ddtz = NULL;
        $sd = new \DateTime($src_date[2]." ".$src_date[3]." ".$src_date[4]." ".$src_date[5],$ddtz);
        if (($sd->getTimestamp()+3600)<=(new \DateTime())->getTimestamp()) {
            $sd->setTimestamp($sd->getTimestamp() + 3600);
        }
        $sd->setTimezone((new \DateTime())->getTimezone());
        $this->message->date = $sd->format('Y-m-d H:i:s');
        $this->message->ftnTZ = $sd->getTimezone()->getName();
    }
    private function parseSubject($data) {
        $this->message->subject = str_replace(['Re: ','Re:'],"",$data);
    }
    private function parseKludge($data) {
        $this->message->kludges []= $data;
    }
    private function parseVia($data) {
        $this->message->via []= $data;
    }

}