<?php
/**
 * Created by PhpStorm.
 * User: Snake
 * Date: 10.08.2016
 * Time: 16:04
 */

namespace Entity;

use Entity\Config;

class LocalDB
{
    private $db_host;
    private $db_name;
    private $db_user;
    private $db_pass;

    public function __construct(Config $config) {
        $this->db_host = $config->ftnw['mysql_host'];
        $this->db_name = $config->ftnw['mysql_db'];
        $this->db_user = $config->ftnw['mysql_user'];
        $this->db_pass = $config->ftnw['mysql_pass'];
    }

    public function getDBConnection() {
        $config = new \Doctrine\DBAL\Configuration();
        $connectionParams = array(
            'dbname' => $this->db_name,
            'user' => $this->db_user,
            'password' => $this->db_pass,
            'host' => $this->db_host,
            'driver' => 'pdo_mysql',
            'charset'  => 'utf8',
            'driverOptions' => array(
                1002 => 'SET NAMES utf8'
            )
        );
        return $conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams,$config);
    }
}