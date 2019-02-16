<?php

namespace Puscha\Helper;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\SymfonyQuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class ConsoleStyle
{
    protected $input;
    protected $output;

    public function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
    }

    /**
     * @return OutputInterface
     */
    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    /**
     * Asks for confirmation.
     *
     * @param string $question
     * @param bool   $default
     *
     * @return bool
     */
    public function confirm($question, $default)
    {
        $question = new ConfirmationQuestion($question, $default);
        $questionHelper = new SymfonyQuestionHelper();
        $result = $questionHelper->ask($this->input, $this->output, $question);

        return $result;
    }

    /**
     * Creates a progress bar.
     *
     * @param bool $section
     * @param int  $max
     *
     * @return ProgressBarWrapper
     */
    public function createProgressBar(bool $section = true, $max = 0)
    {
        if ($this->output->isVerbose()) {
            $output = ($section) ? $this->output->section() : $this->output;
            $progressBar = new ProgressBar($output, $max);

            $progressBar->setFormat('normal');

            return new ProgressBarWrapper($progressBar);
        } else {
            return new ProgressBarWrapper(null);
        }
    }

    /**
     * @param ProgressBarWrapper $progressBar
     *
     * @return \Closure
     */
    public function progressCallback(ProgressBarWrapper $progressBar)
    {
        return function ($step, $total = null) use ($progressBar) {
            if ($total !== null) {
                $progressBar->setMaxSteps($total);
            }

            $progressBar->setProgress($step);
        };
    }
}
