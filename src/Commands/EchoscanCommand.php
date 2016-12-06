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
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class EchoscanCommand extends Command
{
    private $ftnconfig;
    private $logger;
    private $db;

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
            ->setName('echomail:scan')
            ->setDescription('Scan unbatched messages and spool them for tosser')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // get unbatched messages
        $sql = "SELECT * FROM message_batch WHERE batched=0";
        $dbm = $this->db->prepare($sql);
        $dbm->execute();
        $unbatched = $dbm->fetchAll();
        $spooled_count = 0;
        foreach($unbatched as $mess) {
            $packet = new StreamOutput(fopen($this->ftnconfig->echomail_spool."/".$mess['h_ftnmid'].".msg","w+"));
            $packet->writeln("Path: ".$this->ftnconfig->node_rfc."!not-for-mail");
            $packet->writeln("Newsgroups: ".$mess['area']);
            $packet->writeln("X-Comment-To: ".iconv("UTF-8","CP866",$mess["h_to"]));
            $packet->writeln("From: ".$mess["h_from"]." <".str_replace([" ","_"],".",$mess['h_from'])."@".$mess['h_from_rfc'].">");
            $packet->writeln("Date: ".(new \DateTime($mess["h_date"]))->format('r'));
            $packet->writeln("Subject: ".iconv("UTF-8","CP866",$mess['subject']));
            $packet->writeln("Message-ID: ".$mess['message_id']);
            if (!is_null($mess['h_to_rfc']) && !is_null($mess['reply_id'])) {
                $packet->writeln("References: ".$mess['reply_id']);
            }
            $packet->writeln("X-Newsreader: ".iconv("UTF-8","CP866",$mess["pid"]));
            $packet->writeln("X-FTN-CHRS: CP866");
            $packet->writeln("Mime-Version: 1.0");
            $packet->writeln("Content-Type: text/plain; charset=x-cp866");
            $packet->writeln("Content-Transfer-Encoding: 8bit");
            $packet->writeln("X-FTN-AREA: ".mb_strtoupper($mess['area']));
            $packet->writeln("X-FTN-MSGID: ".$mess['h_from_ftn']." ".$mess['h_ftnmid']);
            if (!is_null($mess['h_to_ftn']) && !is_null($mess['h_ftnreply'])) {
                $packet->writeln("X-FTN-REPLY: ".$mess["h_to_ftn"]." ".$mess["h_ftnreply"]);
            }
            $packet->writeln("X-FTN-TID: ".iconv("UTF-8","CP866",$this->ftnconfig->version));
            $packet->writeln("X-FTN-PID: ".iconv("UTF-8","CP866",$mess["pid"]));
            $packet->writeln("X-FTN-TZUTC: ".(new \DateTime($mess["h_date"]))->format('O'));
            $packet->writeln("X-FTN-Tearline: ".iconv("UTF-8","CP866",$mess['tearline']));
            $packet->writeln("X-FTN-Origin: ".iconv("UTF-8","CP866",$mess['origin']));
            $packet->writeln("");
            $packet->writeln(iconv("UTF-8","CP866",$mess['body']));
            $packet->writeln("");

            $sql = "UPDATE message_batch set batched=1 WHERE id=:bid";
            $query = $this->db->prepare($sql);
            $query->bindValue("bid", $mess['id']);
            $query->execute();
            $spooled_count++;
        }

        // $this->ftnconfig->echomail_spool

        $this->logger->info("{time} echomail spooled: {cnt}",['time'=>date('r'),'cnt'=>$spooled_count]);

    }
}
