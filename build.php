<?php

@unlink(__DIR__ . '/build/pushftp.phar');
@mkdir(__DIR__ . '/build/');

$phar = new Phar(__DIR__ . '/build/pushftp.phar', FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, 'pushftp.phar');
$phar->startBuffering();

// Adding folders
$phar->buildFromDirectory(__DIR__, '/(src|vendor)\/.*$/');

// Adding main file
$phar->addFile('pushftp.php');
$phar->setStub($phar->createDefaultStub('pushftp.php'));

$phar->stopBuffering();

// $phar->compressFiles(Phar::GZ);
