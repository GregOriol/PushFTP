<?php

namespace PushFTP\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

abstract class AbstractCommand extends Command
{
	/**
	 * Asks a value without echoing it
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @param string $label
	 * @return string|null null if not interactive or nothing typed
	 */
	protected function askHidden(InputInterface $input, OutputInterface $output, $label)
	{
		$question = new Question($label);
		$question->setHidden(true);
		$question->setHiddenFallback(false);

		$value = $this->getHelper('question')->ask($input, $output, $question);

		return ($value === null || $value === '') ? null : $value;
	}

	/**
	 * Gets the key from the --key option, or PUSHFTP_KEY environment variable, or prompted if --key has no value
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return string|null
	 */
	protected function getKey(InputInterface $input, OutputInterface $output)
	{
		$key = $input->getOption('key');
		if ($key === null) {
			return $this->askHidden($input, $output, 'Key: ');
		}
		if ($key === false || $key === '') {
			$key = getenv('PUSHFTP_KEY');
		}

		return ($key === false || $key === '') ? null : $key;
	}
}
