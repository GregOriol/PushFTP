<?php

namespace Puscha\Scm;

use Psr\Log\LoggerInterface;
use Puscha\Exception\PuschaException;
use Puscha\Helper\DebugHelper;

class Executor
{
    /** @var string */
    protected $path;
    /** @var LoggerInterface */
    protected $logger;

    /**
     * Executor constructor.
     *
     * @param string $path
     * @param LoggerInterface $logger
     *
     * @throws PuschaException
     */
    public function __construct($path, $logger)
    {
        if (!$path || empty($path)) {
            throw new PuschaException('No path defined for Executor');
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
        $fullCmd = 'cd "'.$this->path.'" && '.$cmd;
        $this->logger->debug('executing: '.$fullCmd);

        $result = exec($fullCmd, $output, $return_var);
        //$this->logger->debug('  output: '.print_r($output, true));
        //$this->logger->debug('  result: '.$result);
        //$this->logger->debug('  return_var: '.$return_var);

        return $result;
    }
}