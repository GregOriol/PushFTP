<?php

namespace Puscha\Command;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Puscha\Exception\ConfigValidationException;
use Puscha\Exception\PuschaException;
use Puscha\Helper\ConfigHelper;
use Puscha\Helper\ConsoleStyle;
use Puscha\Helper\Symfony\Console\PrefixedConsoleLogger;
use Puscha\RunHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends Command
{
    /** @var ConsoleStyle */
    protected $io;
    /** @var LoggerInterface */
    protected $logger;

    protected function configure()
    {
        $this->setName('run')
            ->setDescription('Runs a push.')
            ->setHelp('This command allows you to run the push.')
            ->addArgument('file', InputArgument::OPTIONAL, 'The path to the configuration file. If not provided, assuming ./puscha.json.', './puscha.json')
            ->addOption('profile', null, InputOption::VALUE_REQUIRED, 'Profile to use (one or multiple comma-separated names)', 'default')
            ->addOption('go', null, InputOption::VALUE_NONE, 'Run for real, otherwise dry run')
            ->addOption('lenient', null, InputOption::VALUE_NONE, 'Lenient mode, doesn\'t stop on files already existing/deleted when adding or deleting')
            ->addOption('nfonc', null, InputOption::VALUE_NONE, 'No failure on no changes: exits with OK status if no changes found (useful with scripting or CI)')
            ->addOption('key', null, InputOption::VALUE_REQUIRED, 'Key to decrypt AES encrypted passwords')
            ->addOption('base', null, InputOption::VALUE_REQUIRED, 'Base path. If not provided, assuming .', '.');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new ConsoleStyle($input, $output);

        // Preparing a special logger to log with our level of details
        $this->logger = new PrefixedConsoleLogger($output, array(
            LogLevel::EMERGENCY => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::ALERT => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::CRITICAL => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::ERROR => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::WARNING => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO => OutputInterface::VERBOSITY_VERBOSE,
            LogLevel::DEBUG => OutputInterface::VERBOSITY_DEBUG,
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

        try {
            $output->writeln($this->getApplication()->getName().' <info>v'.$this->getApplication()->getVersion().'</info> ');
            $output->writeln('');

            // Loading config
            $file = $input->getArgument('file');
            $realfile = realpath($file);

            $output->writeln('<info>Loading config file:</info> '.$file);
            $output->writeln('');

            $config = ConfigHelper::load($realfile, new NullLogger());

            if ($config === null) {
                throw new PuschaException('Configuration could not be loaded. Check that the file exists and that it is valid (use tools:test-config to validate).');
            }

            // Getting the profile(s)
            $profile = $input->getOption('profile');

            $profileNames = explode(',', $profile);
            $profileNames = array_filter($profileNames, function ($value) {
                return !empty($value);
            });
            $profileNames = array_unique($profileNames);

            // Base
            $base = $input->getOption('base');
            $realbase = realpath($base);
            if (!file_exists($realbase)) {
                throw new PuschaException('Base path '.$base.' doesn\'t exist');
            } else {
                // Removing last slash from base path
                if (substr($realbase, -1, 1) === '/') {
                    $realbase = substr($realbase, 0, -1);
                }
                $this->logger->debug('Using base path: '.$realbase);
            }

            // Go
            $go = $input->getOption('go');
            if (!$go) {
                $this->logger->warning('!!! DRY RUN !!!');
            } else {
                $this->logger->debug('Running for real');
            }

            // Lenient
            $lenient = $input->getOption('lenient');
            if ($lenient) {
                $this->logger->debug('Running in lenient mode');
            } else {
                $this->logger->debug('Running in non-lenient mode');
            }

            // Nfonc
            $nfonc = $input->getOption('nfonc');
            if ($nfonc) {
                $this->logger->debug('Running in no-failure-on-no-changes mode');
            } else {
                $this->logger->debug('Running in failure-on-no-changes mode');
            }

            // Key
            $key = $input->getOption('key');
            if ($key === null) {
                $this->logger->info('No key has been provided, will try to read plaintext passwords');
            } else {
                $this->logger->info('A key has been provided, will try to read AES encrypted passwords');
            }

            $profiles = array();
            $availableProfiles = $config->getProfiles();
            foreach ($profileNames as $profileName) {
                if (!array_key_exists($profileName, $availableProfiles)) {
                    throw new ConfigValidationException('Profile '.$profileName.' not found in the configuration.');
                }

                $profiles[] = $availableProfiles[$profileName];
            }

            $this->logger->debug('Loaded '.count($profiles).' profile(s): '.implode(', ', $profileNames));

            $handler = new RunHandler($profiles, $realbase, $go, $lenient, $nfonc, $key, $this->logger, $this->io);
            $handler->main();
        } catch (PuschaException $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return $e->getCode();
        }

        return 0;
    }
}
