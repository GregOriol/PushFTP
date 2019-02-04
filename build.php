<?php

@unlink(__DIR__ . '/build/puscha.phar');
@mkdir(__DIR__ . '/build/');

$phar = new Phar(__DIR__ . '/build/puscha.phar', FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME, 'puscha.phar');
$phar->startBuffering();

// Adding folders
$phar->buildFromDirectory(__DIR__, '/(src|vendor)\/.*$/');

// Adding main file
$phar->addFile('puscha.php');
$phar->setStub($phar->createDefaultStub('puscha.php'));

$phar->stopBuffering();

$phar->compressFiles(Phar::GZ);
