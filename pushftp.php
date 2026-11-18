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

/**
 * Loading
 */
require_once(__DIR__.'/vendor/autoload.php');

/**
 * Main
 */
use \Symfony\Component\Console\Application;

$application = new Application('PushFTP', PUSHFTP_VERSION);
$application->addCommand(new \PushFTP\Command\PushCommand());
$application->addCommand(new \PushFTP\Command\EncryptPasswordCommand());
$application->run();
