<?php

namespace Puscha\Helper\Symfony\Console;

use Symfony\Component\Console\Logger\ConsoleLogger;

class PrefixedConsoleLogger extends ConsoleLogger
{
    /** @var string|null A prefix to add to all logged messages */
    protected $prefix = null;

    public function log($level, $message, array $context = array())
    {
        if ($this->prefix !== null) {
            $message = '['.$this->prefix.']'.' '.$message;
        }
        
        parent::log($level, $message, $context);
    }

    /**
     * @return string|null
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * @param string|null $prefix
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }
}
