<?php

namespace PushFTP\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
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
	protected function askHidden(InputInterface $input, OutputInterface $output, string $label): ?string
	{
		$question = new Question($label);
		$question->setHidden(true);
		$question->setHiddenFallback(false);

		$helper = $this->getHelper('question');
		if (!$helper instanceof QuestionHelper) {
			throw new \Exception('Question helper not available');
		}

		$value = $helper->ask($input, $output, $question);

		return (is_string($value) && $value !== '') ? $value : null;
	}

	/**
	 * Gets the key from the --key option, or PUSHFTP_KEY environment variable, or prompted if --key has no value
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return string|null
	 */
	protected function getKey(InputInterface $input, OutputInterface $output): ?string
	{
		$key = $input->getOption('key');
		if ($key === null) {
			return $this->askHidden($input, $output, 'Key: ');
		}
		if ($key === false || $key === '') {
			$key = getenv('PUSHFTP_KEY');
		}

		return (is_string($key) && $key !== '') ? $key : null;
	}
}
