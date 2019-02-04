<?php

namespace Puscha\Scm;

use Psr\Log\LoggerInterface;
use Puscha\Exception\PuschaException;
use Puscha\Helper\DebugHelper;

abstract class AbstractScm implements ScmInterface
{
    /** @var string Path of the working directory */
    protected $path;

    /** @var LoggerInterface */
    protected $logger;

    /** @var string Class name of an Executor */
    protected static $executorClass = Executor::class;

    /** @var Executor */
    protected $executor;

    /** @var string Root url of the repository */
    protected $repositoryRoot;
    /** @var string */
    protected $repositoryId;
    /** @var string The tree (branch, ...) on which the repository is */
    protected $repositoryTree;

    /**
     * {@inheritdoc}
     */
    public function __construct($path, $logger)
    {
        if ($path === null || empty($path)) {
            throw new PuschaException('No path defined for SCM');
        }

        $this->path = $path;

        $this->logger = $logger;
    }

    public function __debugInfo()
    {
        $debugInfo = get_object_vars($this);
        DebugHelper::simplifyDebugInfo($debugInfo);

        return $debugInfo;
    }

    public function exec($cmd, &$output = null, &$return_var = null)
    {
        if ($this->executor === null) {
            $this->executor = new self::$executorClass($this->path, $this->logger);
        }

        return $this->executor->exec($cmd, $output, $return_var);
    }
}
