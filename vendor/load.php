<?php

/**
 * PEAR
 */
define ('PEAR_PATH', BASE_PATH.'/vendor/PEAR');
ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . PEAR_PATH);
set_include_path(get_include_path() . PATH_SEPARATOR . PEAR_PATH);

require_once('PEAR5.php');
require_once('Net/FTP.php');
require_once('Console/CommandLine.php');
