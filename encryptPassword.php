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
	'description'		=> "the key to use",
	'action'			=> 'StoreString'
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
if ($cli->options['password'] === NULL || empty($cli->options['password']) ||
    $cli->options['key'] === NULL || empty($cli->options['key'])) {
	echo 'Missing arguments'."\n";
	exit(1);
}

$pass = $cli->options['password'];
$key = $cli->options['key'];

echo "\n";

$encrypter = new \phpseclib\Crypt\AES();
$encrypter->setKey($key);
$pass_encrypt = $encrypter->encrypt($pass);
echo base64_encode($pass_encrypt)."\n";
