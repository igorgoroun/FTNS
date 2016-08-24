<?php

namespace Commands;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Entity\Config;
use Entity\IfmailPacket;
use Entity\LocalDB;
use Entity\MessageParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class MailtossCommand extends Command
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
            ->setName('netmail:toss')
            ->setDescription('Toss spooled netmails and store them to database / pack to classic points')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $finder = new Finder();
        $finder->in($this->ftnconfig->netmail_spool)->name("*.msg")->date(" < now - 1 minute");
        $to_parse = 50;
        $parsed_count = 0;
        foreach($finder->files() as $mfile) {
            if ($parsed_count>=$to_parse) break;
            $parsed = new MessageParser($mfile->getContents());
            //print_r($parsed->message);
            if (array_key_exists($parsed->message->to_ifaddr,$this->ftnconfig->points)) {
                /*
                 * FOR WEB POINT
                 */
                if ($this->ftnconfig->points[$parsed->message->to_ifaddr] == 0) {
                    $this->saveToDB($parsed);
                /*
                 * FOR CLASSIC POINT
                 */
                } elseif ($this->ftnconfig->points[$parsed->message->to_ifaddr] == 1) {
                    $this->sentToIfmail($parsed,$parsed->message->to_ifaddr);
                }
            /*
             * LOCAL MESSAGE FOR SYSOP OR FOR ROBOTS
             */
            } elseif ($parsed->message->to_ifaddr == $this->ftnconfig->node_rfc) {
                $this->logger->info("@{time} got netmail to my node",['time'=>date('r')]);
                if (in_array(mb_strtolower($parsed->message->to_name),['areafix','area-fix','area fix'])) {
                    $this->logger->info("@{time} got areafix request from {sender}",['time'=>date('r'),'sender'=>$parsed->message->from_ftn]);
                }

            /*
             * TO UNEXISTENT POINT - SEND BACK
             */
            } elseif (strpos($parsed->message->to_ifaddr,$this->ftnconfig->node_rfc)) {
                // TODO: REFACTOR NEEDED AND ROUTING
                $route = false;
                /*
                 *  IN MY NODE
                 */
                if (strpos($parsed->message->from_ifaddr,$this->ftnconfig->node_rfc)) {
                    if (array_key_exists($parsed->message->from_ifaddr,$this->ftnconfig->points)) {
                        $route = $parsed->message->from_ifaddr;
                    }
                /*
                 * OTHER NODE
                 */
                } else {
                    $route = $this->ftnconfig->route['default'];
                }

                // SEND packet
                if ($route) {
                    $packet = new IfmailPacket($this->ftnconfig);
                    $packet->setFrom("SysOp", $this->ftnconfig->node_rfc);
                    $packet->setTo($parsed->message->from_name, $parsed->message->from_ifaddr);
                    $packet->setSubject("Error: ".$parsed->message->subject);
                    $packet->setBody("Error: Unexistent point ".$parsed->message->to_ftn."\n\n");
                    $ifmail = new Process(
                        $this->ftnconfig->ifmail." -r ".$route." ".$parsed->message->from_rfc,
                        null,
                        null,
                        $packet->getPacket()
                    );
                    $ifmail->run();
                    $this->logger->info("@{time} netmail error return to {to}",['time'=>date('r'),'to'=>$packet->getTo()]);
                } else {
                    $this->logger->info("@{time} netmail no return because of possible loop",['time'=>date('r')]);
                }
            /*
             * FOR NOT MY POINT - SEND TO UPLINK
             */
            } else {
                $this->sentToIfmail($parsed,$this->ftnconfig->route['default']);
                // TODO: ROUTING PARSER !!
            }

            /*
             * delete source msg file
             */
            unlink($mfile->getPathname());
            $parsed_count++;
        }
        $this->logger->info("@{time} netmail tossed: {cnt}",['time'=>date('r'),'cnt'=>$parsed_count]);

    }

    private function sentToIfmail ($parsed,$route) {
        $ifmail = new Process(
            $this->ftnconfig->ifmail." -x 8 -r ".$route." ".$parsed->message->to_rfc,
            null,
            null,
            $parsed->message->srcFull
        );
        $ifmail->run();
        $this->logger->info("@{time} netmail for {to}",['time'=>date('r'),'to'=>$parsed->message->to_ftn]);
    }
    private function saveToDB ($parsed) {
        // Check point db ID
        $psql = "SELECT id from point where ifaddr=:ifadr LIMIT 1";
        $pq = $this->db->prepare($psql);
        $pq->bindValue('ifadr',$parsed->message->to_ifaddr);
        $pq->execute();
        $point_id = $pq->fetchColumn(0);
        if (is_numeric($point_id) && $point_id>0) {
            // Save message to database
            $sql = "INSERT INTO netmail SET
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
                            point_id=:m_pointid,
                            message_id=:m_mid,
                            seen=0,
                            batched=1,
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
            $query->bindValue("m_pointid", $point_id);
            $query->bindValue("m_mid", $parsed->message->messageid);
            try {
                $query->execute();
                $this->logger->info("@{time} Netmail from {fr} to {to} sent to DB", ['time'=>date('r'),'fr'=>$parsed->message->from_rfc,'to'=>$parsed->message->to_rfc]);
            } catch (UniqueConstraintViolationException $e) {
                $this->logger->info("@{time} Not uniq message ID: {mid}", ['time'=>date('r'),'mid' => $parsed->message->messageid]);
            }
        }
    }
}
