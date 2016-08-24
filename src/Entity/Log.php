<?php

namespace Entity;

use Psr\Log\LoggerInterface;

class Log
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

}