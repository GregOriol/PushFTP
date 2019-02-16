<?php

namespace Puscha\Helper;

use Symfony\Component\Console\Helper\ProgressBar;

class ProgressBarWrapper
{
    /** @var ProgressBar|null */
    protected $progressBar;

    public function __construct(?ProgressBar $progressBar)
    {
        $this->progressBar = $progressBar;
    }

    /**
     * @param int $max
     */
    public function setMaxSteps(int $max)
    {
        if ($this->progressBar !== null) {
            $this->progressBar->setMaxSteps($max);
        }
    }

    /**
     * @param int $step
     */
    public function setProgress(int $step)
    {
        if ($this->progressBar !== null) {
            $this->progressBar->setProgress($step);
        }
    }

    public function finish()
    {
        if ($this->progressBar !== null) {
            $this->progressBar->finish();
        }
    }
}
