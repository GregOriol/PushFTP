<?php

namespace PushFTP\Command;

use PushFTP\Crypt;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'encrypt-password', description: 'Encrypt a password for pushftp.json')]
class EncryptPasswordCommand extends AbstractCommand
{
	protected function configure(): void
	{
		$this
			->addOption('password', null, InputOption::VALUE_REQUIRED, 'the password to encrypt, prompted if not given')
			->addOption('key', null, InputOption::VALUE_OPTIONAL, 'the key to use, prompted if no value given, defaults to PUSHFTP_KEY environment variable', false);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$password = $input->getOption('password');
		if ($password === null || $password === '') {
			$password = $this->askHidden($input, $output, 'Password to encrypt: ');
		}

		$key = $this->getKey($input, $output);
		if ($key === null && $input->getOption('key') === false) {
			$key = $this->askHidden($input, $output, 'Key: ');
		}

		if ($password === null || $key === null) {
			$output->writeln('<error>Missing password or key</error>');
			return self::FAILURE;
		}

		$output->writeln(Crypt::encrypt($password, $key));

		return self::SUCCESS;
	}
}
