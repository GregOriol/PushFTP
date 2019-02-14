<?php

//
// Pre-flight checks
//

if (version_compare('7.1.3', PHP_VERSION, '>')) {
    fwrite(
        STDERR,
        sprintf(
            'This version of Puscha is supported on PHP >7.1.3.' . PHP_EOL .
            'You are using PHP %s (%s).' . PHP_EOL,
            PHP_VERSION,
            PHP_BINARY
        )
    );
    die(1);
}

if (!ini_get('date.timezone')) {
    ini_set('date.timezone', 'UTC');
}

//
// Settings things up
//

$loader = require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Application;

//\Doctrine\Common\Annotations\AnnotationRegistry::registerLoader([$loader, 'loadClass']);

//
// Starting the console application
//

$application = new Application('Puscha', '1.0.0-beta.2');

$application->add(new Puscha\Command\Tools\EncryptPasswordCommand());
$application->add(new Puscha\Command\Tools\DecryptPasswordCommand());
$application->add(new Puscha\Command\Tools\TestConfigCommand());
$application->add(new Puscha\Command\RunCommand());

$application->run();
