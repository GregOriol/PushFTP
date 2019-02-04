<?php

namespace Puscha\Scm;

use Psr\Log\LoggerInterface;
use Puscha\Exception\PuschaException;
use Puscha\Exception\ScmPathException;
use Puscha\Exception\ScmVersionException;

interface ScmInterface
{
    /**
     * Constructs an SCM repository handler.
     *
     * @param string $path
     * @param LoggerInterface $logger
     *
     * @throws ScmPathException
     */
    public function __construct($path, $logger);

    /**
     * Returns the current version of the repository.
     *
     * @return ScmVersion
     *
     * @throws ScmVersionException
     */
    public function getCurrentVersion();

    /**
     * Returns the initial version of the repository.
     *
     * @return ScmVersion
     *
     * @throws ScmVersionException
     */
    public function getInitialVersion();

    /**
     * Returns changes between two versions.
     *
     * NB: both version have to be provided; or none to get current modification state.
     *
     * @param ScmVersion|null $fromVersion
     * @param ScmVersion|null $toVersion
     *
     * @return ScmChange[]
     * @throws PuschaException
     */
    public function getChanges($fromVersion = null, $toVersion = null);

    /**
     * Dumps the raw changes list between two versions.
     *
     * @param ScmVersion $fromVersion
     * @param ScmVersion $toVersion
     * @param string     $file
     *
     * @return boolean
     * @throws PuschaException
     */
    public function dumpChanges($fromVersion, $toVersion, $file);

    /**
     * Dumps the diff between two versions.
     *
     * @param ScmVersion $fromVersion
     * @param ScmVersion $toVersion
     * @param string     $file
     *
     * @return boolean
     * @throws PuschaException
     */
    public function dumpDiff($fromVersion, $toVersion, $file);

    /**
     * Dumps the log between two versions.
     *
     * @param ScmVersion $fromVersion
     * @param ScmVersion $toVersion
     * @param string     $file
     *
     * @return boolean
     * @throws PuschaException
     */
    public function dumpLog($fromVersion, $toVersion, $file);
}
