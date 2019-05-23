<?php
ini_set('date.timezone', 'Asia/Shanghai');
ini_set('default_socket_timeout', -1);
define('SERVER_ROOT', realpath(__DIR__));
require_once SERVER_ROOT . "/vendor/autoload.php";
error_reporting(E_ALL ^ E_NOTICE);

define('DOMAIN', 'http://192.168.79.206:8081');

$server = new \App\Server();