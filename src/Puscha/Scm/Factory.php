<?php

namespace Puscha\Scm;

use Psr\Log\LoggerInterface;
use Puscha\Exception\PuschaException;
use Puscha\Exception\ScmPathException;

class Factory
{
    const TYPE_SVN = 'Svn';
    const TYPE_GIT = 'Git';

    /**
     * Creates an instance of Scm for the given path.
     *
     * @param string          $path
     * @param LoggerInterface $logger
     *
     * @return ScmInterface
     * @throws PuschaException
     */
    public static function create($path, $logger)
    {
        $types = array(
            self::TYPE_SVN => SvnScm::class,
            self::TYPE_GIT => GitScm::class,
        );

        $logger->debug('Looking for an SCM repository at path: '.$path);

        $scm = null;
        foreach ($types as $type => $class) {
            try {
                /** @var ScmInterface $scm */
                $scm = new $class($path, $logger);

                $logger->info('Found '.$type.' repository');
                break;
            } catch (ScmPathException $e) {
                $logger->debug('Not '.$type.' repository');
                continue;
            }
        }

        if ($scm === null) {
            throw new PuschaException('Could not find any valid SCM repository at path: '.$path);
        }

        return $scm;
    }
}
