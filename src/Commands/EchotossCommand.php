<?php

namespace Commands;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Entity\Config;
use Entity\LocalDB;
use Entity\MessageParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class EchotossCommand extends Command
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
            ->setName('echomail:toss')
            ->setDescription('Toss spooled packets and store them to database / pack to classic points')
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
        $this->logger->notice("{time} echotoss got {cnt} areas from db",['time'=>date('r'),'cnt'=>count($db_areas)]);

        // get web-points list
        $sql = "SELECT id,ifaddr FROM point WHERE active=1 and classic=0";
        $dbm = $this->db->prepare($sql);
        $dbm->execute();
        $web_points = array();
        foreach($dbm->fetchAll() as $pn) {
            $web_points[$pn['ifaddr']] = $pn['id'];
        }
        $this->logger->notice("{time} echotoss got {cnt} point from db",['time'=>date('r'),'cnt'=>count($web_points)]);

        $finder = new Finder();
        $finder->in($this->ftnconfig->echomail_spool)->name("*.msg")->date(" < now - 1 minute");
        $to_parse = 100;
        $parsed_count = 0;
        foreach($finder->files() as $mfile) {
            if ($parsed_count>=$to_parse) break;
            $parsed = new MessageParser($mfile->getContents());
            //print_r($parsed->message);

            foreach($parsed->message->echoareas as $area) {
                if (array_key_exists($area,$db_areas)) {
                    // Save message to database
                    $sql = "INSERT INTO message_cache SET
                    body=:m_body,
                    h_from=:m_from,
                    h_from_rfc=:m_fromrfc,
                    h_from_ftn=:m_fromftn,
                    h_to=:m_to,
                    h_to_ftn=:m_toftn,
                    h_to_rfc=:m_torfc,
                    h_ftnmid=:m_ftnmid,
                    h_ftnreply=:m_ftnreply,
                    h_date=:m_date,
                    subject=:m_subj,
                    tearline=:m_tear,
                    origin=:m_origin,
                    tid=:m_tid,
                    pid=:m_pid,
                    avatar=:m_avatar,
                    src_header=:m_srchead,
                    group_id=:m_groupid,
                    message_id=:m_mid,
                    cached_at=NOW()
                ";
                    //print $parsed->message->subject;
                    $query = $this->db->prepare($sql);
                    $query->bindValue("m_body", $parsed->message->body);
                    $query->bindValue("m_from", $parsed->message->from_name);
                    $query->bindValue("m_fromrfc", $parsed->message->from_rfc);
                    $query->bindValue("m_fromftn", $parsed->message->from_ftn);
                    $query->bindValue("m_to", $parsed->message->to_name);
                    $query->bindValue("m_toftn", $parsed->message->to_ftn);
                    $query->bindValue("m_torfc", $parsed->message->to_rfc);
                    $query->bindValue("m_ftnmid", $parsed->message->ftnMSGID);
                    $query->bindValue("m_ftnreply", $parsed->message->ftnREPLY);
                    $query->bindValue("m_date", $parsed->message->date);
                    $query->bindValue("m_subj", $parsed->message->subject);
                    $query->bindValue("m_tear", $parsed->message->tearline);
                    $query->bindValue("m_origin", $parsed->message->origin);
                    $query->bindValue("m_tid", $parsed->message->ftnTID);
                    $query->bindValue("m_pid", $parsed->message->ftnPID);
                    $query->bindValue("m_avatar", $parsed->message->avatar);
                    $query->bindValue("m_srchead", $parsed->message->srcHeader);
                    $query->bindValue("m_groupid", $db_areas[$area]);
                    $query->bindValue("m_mid", $parsed->message->messageid);
                    try {
                        $query->execute();
                    } catch (UniqueConstraintViolationException $e) {
                        //$output->writeln("Not uniq message ID ".$parsed->message->messageid);
                        $this->logger->info("Not uniq message ID: {mid}",['mid'=>$parsed->message->messageid]);
                        continue;
                    }
                    //print_r($query->errorCode());
                    // get message id
                    $message_id = $this->db->lastInsertId();
                    $this->logger->notice("{time} echotoss saved massage to db, id: {mid}, area: {are}",['time'=>date('r'),'mid'=>$message_id,'are'=>$area]);
                    //print $message_id;

                    // downlinks (points)
                    if (count($this->ftnconfig->areas[$area]['subscribers']) > 0) {
                        foreach ($this->ftnconfig->areas[$area]['subscribers'] as $point => $classic) {
                            // pack for classic point/downlink
                            if ($classic == 1) {
                                // check no-route to recipient
                                if (!in_array($point, $parsed->message->noroute)) {
                                    // send packet to ifnews
                                    $ifnews = new Process(
                                        $this->ftnconfig->ifmail." -n -r ".$point,
                                        null,
                                        null,
                                        $parsed->message->srcFull
                                    );
                                    $ifnews->run();
                                }
                                // create numerable reading record for point
                            } else {
                                //echo "write user message db";
                                $sql = "INSERT INTO point_message SET point_id=:pid, area_id=:aid, message_id=:mid, created=NOW(), seen=0";
                                $pm = $this->db->prepare($sql);
                                $pm->bindValue("pid", $web_points[$point]);
                                $pm->bindValue("aid", $db_areas[$area]);
                                $pm->bindValue("mid", $message_id);
                                $pm->execute();
                            }
                        }
                    }
                    // uplink
                    if (!in_array($this->ftnconfig->areas[$area]['uplink'], $parsed->message->noroute)) {
                        // pack for uplink
                        $ifnews = new Process(
                            $this->ftnconfig->ifmail." -x 16 -n -r ".$this->ftnconfig->areas[$area]['uplink'],
                            null,
                            null,
                            $parsed->message->srcFull
                        );
                        $ifnews->run();
                    }
                }
            }

            unlink($mfile->getPathname());
            $parsed_count++;

        }
        $this->logger->info("@{time} echomail tossed: {cnt} in {spent} s",['time'=>date('r'),'cnt'=>$parsed_count,'spent'=>microtime(true)-$this->ftnconfig->starttime]);

    }
}
