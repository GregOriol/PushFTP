<?php

namespace Puscha\Helper\Symfony\Console;

use Symfony\Component\Console\Formatter\OutputFormatter;

class IndentedOutputFormatter extends OutputFormatter
{
    protected $char = '  ';
    protected $level = 0;

    /**
     * {@inheritdoc}
     */
    public function format($message)
    {
        $indentation = str_repeat($this->char, $this->level);

        // Adding indentation to all lignes
        $messageLines = explode(PHP_EOL, $message);

        $messageLines = array_map(function ($value) use ($indentation) {
            return (!empty(trim($value))) ? $indentation.$value : $value;
        }, $messageLines);

        return implode(PHP_EOL, $messageLines);
    }

    /**
     * @return string
     */
    public function getChar(): string
    {
        return $this->char;
    }

    /**
     * @param string $char
     */
    public function setChar(string $char)
    {
        $this->char = $char;
    }

    /**
     * @return int
     */
    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * @param int $level
     */
    public function setLevel(int $level)
    {
        $this->level = $level;
    }

    /**
     *
     */
    public function incrementLevel()
    {
        $this->level += 1;
    }

    /**
     *
     */
    public function decrementLevel()
    {
        $this->level -= 1;

        if ($this->level < 0) {
            $this->level = 0;
        }
    }
}
