<?php

namespace Puscha\Target;

use League\Flysystem\Adapter\Ftp;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\Sftp\SftpAdapter;
use Psr\Log\LoggerInterface;
use Puscha\Exception\PuschaException;
use Puscha\Helper\Flysystem\LoggableFilesystem;
use Puscha\Model\Target;

class Factory
{
    const TIMEOUT = 30;

    /**
     * @param Target          $targetConfiguration
     * @param LoggerInterface $logger
     *
     * @return FilesystemInterface
     * @throws PuschaException
     */
    public static function create($targetConfiguration, $logger)
    {
        $target = null;

        switch ($targetConfiguration->getType()) {
            case 'ftp':
                //$target = new FtpTarget($host, $port, $logger);
                $target = new LoggableFilesystem(new Ftp([
                    'host' => $targetConfiguration->getHost(),
                    'username' => $targetConfiguration->getLogin(),
                    'password' => $targetConfiguration->getPassword(),
                    'port' => $targetConfiguration->getPort(),
                    'root' => $targetConfiguration->getPath(),
                    'passive' => $targetConfiguration->isPassive(),
                    'ssl' => $targetConfiguration->isSsl(),
                    'timeout' => self::TIMEOUT,
                ]), null, $logger);
                break;
            case 'sftp':
                //$target = new SftpTarget($host, $port, $logger);
                $target = new LoggableFilesystem(new SftpAdapter([
                    'host' => $targetConfiguration->getHost(),
                    'port' => $targetConfiguration->getPort(),
                    'username' => $targetConfiguration->getLogin(),
                    'password' => $targetConfiguration->getPassword(),
                    'privateKey' => $targetConfiguration->getKey(),
                    'root' => $targetConfiguration->getPath(),
                    'timeout' => self::TIMEOUT,
                ]), null, $logger);
                break;
            default:
                throw new PuschaException('Unknown target type: '.$targetConfiguration->getType());
        }

        return $target;
    }
}
