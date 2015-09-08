<?php

/**
 * This script will check for SCM changes and update a target
 * 
 * Based on :
 *  - Net_FTP : http://pear.php.net/manual/en/package.networking.net-ftp.php
 *  - Console_CommandLine : http://pear.php.net/manual/en/package.console.console-commandline.php
 *  - phpseclib : http://phpseclib.sourceforge.net
 */

/**
 * Configuration
 */
define('PUSHFTP_VERSION', '0.8.0');

ini_set('memory_limit', '512M');
set_time_limit(30*60*60); // 30 min
define('BASE_PATH', __DIR__);

/**
 * Base
 */
ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . BASE_PATH);
set_include_path(get_include_path() . PATH_SEPARATOR . BASE_PATH);

/**
 * Loading
 */
require_once('vendor/autoload.php');
require_once('vendor/load.php');

/**
 * Main
 */
use \PushFTP\PushFTP;

try {
	$pusher = new PushFTP();
	$pusher->parseCommandLine();

	$pusher->parseConfigFile();
	$pusher->prepareTarget();

	$pusher->parseLocalRevision();
	$pusher->parseTargetRevision();

	$pusher->parseChanges();
	try {
		$pusher->pushChanges();
	} catch (Exception $e) {
		$pusher->rollbackChanges();
		throw new \Exception('', 1);
	}

	$pusher->checkPermissions();

	if ($pusher->cdnflushlist) {
		$pusher->makeCdnFlushList();
	}

	$pusher->updateRemoteRevision();
} catch (Exception $e) {
	$ecode = $e->getCode();
	if ($ecode == 0) {
		exit(0);
	} else {
		exit(1);
	}
}
