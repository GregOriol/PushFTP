<?php

/**
 * This script will check for SCM changes and update a target
 * 
 * Based on :
 *  - Symfony Console : https://symfony.com/doc/current/components/console.html
 *  - phpseclib : http://phpseclib.sourceforge.net
 */

/**
 * Configuration
 */
define('PUSHFTP_VERSION', '0.9.0a');

ini_set('memory_limit', '512M');
set_time_limit(30*60*60); // 30 min
define('BASE_PATH', __DIR__);

error_reporting(E_ALL ^ E_DEPRECATED);

/**
 * Base
 */
ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . BASE_PATH);
set_include_path(get_include_path() . PATH_SEPARATOR . BASE_PATH);

/**
 * Loading
 */
require_once('vendor/autoload.php');

/**
 * Main
 */
use \Symfony\Component\Console\Application;

$application = new Application('PushFTP', PUSHFTP_VERSION);
$application->addCommand(new \PushFTP\Command\PushCommand());
$application->addCommand(new \PushFTP\Command\EncryptPasswordCommand());
$application->run();
