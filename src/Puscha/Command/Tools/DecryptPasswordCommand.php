<?php

namespace Puscha\Command\Tools;

use Puscha\Helper\PasswordHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class DecryptPasswordCommand extends Command
{
    protected function configure()
    {
        $this->setName('tools:decrypt-password')
            ->setDescription('Decrypts a password.')
            ->setHelp('This command allows you to decrypt a password using a key.')
            ->addArgument('password', InputArgument::REQUIRED, 'The password to decrypt.')
            ->addOption('key', null, InputOption::VALUE_REQUIRED, 'The key to use.')
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');

        if ($input->getArgument('password') === null) {
            $question = new Question('Password to decrypt?');
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
            $question = new Question('Key?');
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

        $result = PasswordHelper::decrypt($password, $key);

        $output->writeln('<info>Decrypted password:</info>');
        $output->writeln($result);
    }
}
