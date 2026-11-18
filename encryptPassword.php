<?php

/**
 * This script will encrypt passwords to use in pushftp.json
 */

/**
 * Configuration
 */
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
// Setting up command line parser
$parser = new \Console_CommandLine();
$parser->description = 'Encrypt passwords for pushftp.json';
$parser->version = '1.0';
$parser->addOption('password', array(
	'long_name'			=> '--password',
	'description'		=> "the password to encrypt",
	'action'			=> 'Password'
));
$parser->addOption('key', array(
	'long_name'			=> '--key',
	'description'		=> "the key to use, prompted if no value given, defaults to PUSHFTP_KEY environment variable",
	'action'			=> 'Password'
));

// Parsing command line
try {
	$cli = $parser->parse();
	// print_r($cli->options);
	// print_r($cli->args);
} catch (\Exception $exc) {
	$parser->displayError($exc->getMessage());
	throw new \Exception("Error parsing command line", 1, $exc);
}

// Performing some checks
$pass = $cli->options['password'];
$key = \PushFTP\Crypt::getKey($cli->options['key']);
if ($pass === NULL || $pass === '' || $key === NULL) {
	echo 'Missing arguments'."\n";
	exit(1);
}

echo "\n";

echo \PushFTP\Crypt::encrypt($pass, $key)."\n";
