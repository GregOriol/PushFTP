<?php

@unlink(__DIR__ . '/pushftp.phar');

$phar = new Phar(__DIR__ . '/pushftp.phar', FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, 'pushftp.phar');
$phar->startBuffering();

// Adding folders
$phar->buildFromDirectory(dirname(__FILE__), '/(src|vendor)\/.*$/');

// Adding main file
$phar->addFile('pushftp.php');
$phar->setStub($phar->createDefaultStub('pushftp.php'));

$phar->compressFiles(Phar::GZ);
$phar->stopBuffering();

?>
