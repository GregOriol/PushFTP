<?php

namespace Puscha\Command\Tools;

use Puscha\Helper\PasswordHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Puscha\Helper\StringHelper;

class EncryptPasswordCommand extends Command
{
    protected function configure()
    {
        $this->setName('tools:encrypt-password')
            ->setDescription('Encrypts a password.')
            ->setHelp('This command allows you to encrypt a password using a key. The encrypted password can then be used in a puscha.json file to "safely" store the credential.')
            ->addArgument('password', InputArgument::REQUIRED, 'The password to encrypt.')
            ->addOption('key', null, InputOption::VALUE_OPTIONAL, 'The key to use. If not provided, a key will be generated.')
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');

        if ($input->getArgument('password') === null) {
            $question = new Question('Password to encrypt?');
            if ($output->getVerbosity() <= OutputInterface::VERBOSITY_NORMAL) {
                $question->setHidden(true);
                $question->setHiddenFallback(false);
            }
            $password = $helper->ask($input, $output, $question);

            if (!empty($password)) {
                $input->setArgument('password', $password);
            }
        }

        if ($input->getOption('key') === null) {
            $question = new Question('Key? (leave empty to generate one)');
            if ($output->getVerbosity() <= OutputInterface::VERBOSITY_NORMAL) {
                $question->setHidden(true);
                $question->setHiddenFallback(false);
            }
            $key = $helper->ask($input, $output, $question);

            if (!empty($key)) {
                $input->setOption('key', $key);
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $key = $input->getOption('key');
        $password = $input->getArgument('password');

        if (empty($key)) {
            $key = StringHelper::generateRandomString(StringHelper::POOL_ALPHANUM, 32);
            $output->writeln('<info>No key provided, generating one:</info>');
            $output->writeln($key);
        }

        $result = PasswordHelper::encrypt($password, $key);

        $output->writeln('<info>Encrypted password:</info>');
        $output->writeln($result);
    }
}
