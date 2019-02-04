<?php

namespace Puscha\Command\Tools;

use Psr\Log\LogLevel;
use Puscha\Helper\ConfigHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class TestConfigCommand extends Command
{
    protected function configure()
    {
        $this->setName('tools:test-config')
            ->setDescription('Tests a configuration file.')
            ->setHelp('This command allows you to test and validate a configuration file (structure, mandatory keys, ...).')
            ->addArgument('file', InputArgument::OPTIONAL, 'The path to the file. If not provided, assuming ./puscha.json.', './puscha.json');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file = $input->getArgument('file');
        $output->writeln('<info>Testing config file:</info> '.$file);
        $output->writeln('');

        // Preparing a special logger to log everything to the console
        // since we want all details on the json mapping here
        $logger = new ConsoleLogger($output, array(
            LogLevel::EMERGENCY => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::ALERT     => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::CRITICAL  => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::ERROR     => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::WARNING   => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::NOTICE    => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO      => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::DEBUG     => OutputInterface::VERBOSITY_NORMAL,
        ), array(
            LogLevel::EMERGENCY => ConsoleLogger::ERROR,
            LogLevel::ALERT     => ConsoleLogger::ERROR,
            LogLevel::CRITICAL  => ConsoleLogger::ERROR,
            LogLevel::ERROR     => ConsoleLogger::ERROR,
            LogLevel::WARNING   => 'comment',
            LogLevel::NOTICE    => ConsoleLogger::INFO,
            LogLevel::INFO      => 'fg=default',
            LogLevel::DEBUG     => 'fg=white',
        ));

        $config = ConfigHelper::load($file, $logger);

        $output->writeln('');
        $output->write(ConfigHelper::print($config));

        if ($config === null) {
            $output->writeln('<error>Configuration validation failed!</error>');
        } else {
            $output->writeln('<info>Configuration seems valid!</info>');
        }
    }
}
