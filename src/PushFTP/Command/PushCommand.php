<?php

namespace PushFTP\Command;

use PushFTP\PushFTP;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'push', description: 'Push SCM changes to a target')]
class PushCommand extends AbstractCommand
{
	protected function configure(): void
	{
		$this
			->addArgument('path', InputArgument::REQUIRED, 'local path where pushftp.json is located, or pushftp.json path')
			->addOption('profile', null, InputOption::VALUE_REQUIRED, 'profile to use from pushftp.json file')
			->addOption('go', null, InputOption::VALUE_NONE, 'real, otherwise dry run')
			->addOption('lenient', null, InputOption::VALUE_NONE, "lenient mode, doesn't stop on files already existing/deleted when adding or deleting")
			->addOption('nfonc', null, InputOption::VALUE_NONE, 'no failure on no changes : exits with OK status if no changes found (useful with scripting or CI)')
			->addOption('cdnflushlist', null, InputOption::VALUE_NONE, 'make CDN flush list')
			->addOption('key', null, InputOption::VALUE_OPTIONAL, 'key to decrypt encrypted passwords, prompted if no value given, defaults to PUSHFTP_KEY environment variable', false);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$options = array(
			'path'			=> $input->getArgument('path'),
			'profile'		=> $input->getOption('profile'),
			'go'			=> $input->getOption('go'),
			'lenient'		=> $input->getOption('lenient'),
			'nfonc'			=> $input->getOption('nfonc'),
			'cdnflushlist'	=> $input->getOption('cdnflushlist'),
			'key'			=> $this->getKey($input, $output),
		);

		try {
			$pushftp = new PushFTP();
			$pushftp->setOptions($options);
			$pushftp->run();
		} catch (\Exception $e) {
			// Code 1 with no message: error already logged, code 0 with no message: normal stop
			if ($e->getMessage() === '') {
				return ($e->getCode() == 0) ? self::SUCCESS : self::FAILURE;
			}

			$output->writeln('Unexpected error: '.$e->getMessage());
			return self::FAILURE;
		}

		return self::SUCCESS;
	}
}
