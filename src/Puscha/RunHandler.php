<?php

namespace Puscha;

use Psr\Log\LoggerInterface;
use Puscha\Exception\NoChangesException;
use Puscha\Exception\PuschaException;
use Puscha\Helper\ConsoleStyle;
use Puscha\Helper\DebugHelper;
use Puscha\Helper\PasswordHelper;
use Puscha\Helper\Symfony\Console\PrefixedConsoleLogger;
use Puscha\Helper\Runner;
use Puscha\Model\Profile;
use Puscha\Scm\ScmInterface;
use Puscha\Scm\ScmVersion;

class RunHandler
{
    /** @var Profile[] */
    protected $profiles = array();
    /** @var string */
    protected $base;
    /** @var bool */
    protected $go;
    /** @var bool */
    protected $lenient;
    /** @var bool */
    protected $nfonc;
    /** @var string|null */
    protected $key;

    /** @var LoggerInterface */
    protected $logger;
    /** @var ConsoleStyle */
    protected $io;

    /** @var ScmInterface */
    protected $scm;
    /** @var ScmVersion */
    protected $scmVersion;

    /** @var Runner[] */
    protected $runners;

    /**
     * Run handler constructor.
     *
     * @param Profile[]       $profiles
     * @param string          $base
     * @param bool            $go
     * @param bool            $lenient
     * @param bool            $nfonc
     * @param string|null     $key
     * @param LoggerInterface $logger
     * @param ConsoleStyle    $io
     */
    public function __construct($profiles, $base, $go, $lenient, $nfonc, $key, $logger, $io)
    {
        $this->profiles = $profiles;
        $this->base     = $base;
        $this->go       = $go;
        $this->lenient  = $lenient;
        $this->nfonc    = $nfonc;
        $this->key      = $key;

        $this->logger   = $logger;
        $this->io       = $io;
    }

    public function __debugInfo()
    {
        $debugInfo = get_object_vars($this);
        DebugHelper::simplifyDebugInfo($debugInfo);

        return $debugInfo;
    }

    /**
     * @throws PuschaException
     */
    public function main()
    {
        $this->logger->info('Running...');
        $startTime = microtime(true);

        $this->prepareScm();
        $this->prepareRunners();
        $this->checkTargetVersions();
        $this->prepareChanges();
        $r = $this->checkChanges();

        if ($r) {
            try {
                $this->push();
                $this->commit();
                $this->updateTargetVersions();
            } catch (PuschaException $e) {
                $this->logger->error($e->getMessage());

                $this->revert();
                $this->cleanup();

                throw new PuschaException('Push aborted');
            }

            $this->permissions();
            $this->cleanup();
        }

        $endTime = microtime(true);
        $this->logger->info('Completed in '.round($endTime - $startTime, 2).'s');
    }

    /**
     * Starting the Scm and getting the current version
     *
     * @throws PuschaException
     */
    protected function prepareScm()
    {
        $scm = Scm\Factory::create($this->base, $this->logger);

        $scmVersion = $scm->getCurrentVersion();
        $this->logger->info('Local version is '.$scmVersion->getShortString());

        $this->scm = $scm;
        $this->scmVersion = $scmVersion;
    }

    /**
     * Starting runners for each profile
     *
     * @throws PuschaException
     */
    protected function prepareRunners()
    {
        $runners = array();

        foreach ($this->profiles as $profile) {
            // Preparing password
            if ($this->key !== null) {
                $password = PasswordHelper::decrypt($profile->getTarget()->getPassword(), $this->key);
                $profile->getTarget()->setPassword($password);
            }

            // Creating the runner
            $runner = new Runner($profile, $this->base, $this->go, $this->lenient, $this->nfonc, $this->key, $this->logger, $this->io);

            // Getting current version
            $this->setLoggerPrefix($runner->getName());
            $runner->getCurrentVersion();
            $this->setLoggerPrefix(null);

            $runners[] = $runner;
        }

        $this->runners = $runners;
    }

    /**
     * @throws PuschaException
     */
    protected function checkTargetVersions()
    {
        $this->logger->info('Target versions are:');

        // Displaying current versions of the targets
        foreach ($this->runners as $runner) {
            $targetVersion = $runner->getCurrentVersion();

            $this->logger->info('  - '.$runner->getName().': '.($targetVersion ? $targetVersion->getShortString() : 'none'));
        }

        // Checking versions
        foreach ($this->runners as $runner) {
            $this->setLoggerPrefix($runner->getName());

            $targetVersion = $runner->getCurrentVersion();

            if ($targetVersion === null) {
                $initialVersion = $this->scm->getInitialVersion();

                $result = $this->io->confirm('No rev file found on the target '.$runner->getName().'. Use initial commit of the repository "'.$initialVersion->getFullString().'" as reference for the push?', false);

                if ($result === false) {
                    throw new PuschaException('Could not get a current version for the target '.$runner->getName().'. Cancelling the push.');
                }

                $runner->setCurrentVersion($initialVersion);
            } elseif ($targetVersion->getRepository() !== null) {
                if ($targetVersion->getRepository() != $this->scmVersion->getRepository()) {
                    $result = $this->io->confirm('Local and target '.$runner->getName().' don\'t seem to have been pulled from the same repository! Continue anyway?', true);

                    if ($result === false) {
                        throw new PuschaException('Cancelling the push.');
                    }
                }
            } elseif ($targetVersion->getRepository() === null) {
                $this->logger->info('Target has a rev file in the old v0 format. It is therefore not possible to know if it was pulled from the same repository as the local one.');

                $targetVersion->setRepository($this->scmVersion->getRepository());
                $result = $this->io->confirm('Assume they have been pulled from the same repository? (rev file will be updated to v1 format at the end of the push and you will not be asked this again)', true);

                if ($result === false) {
                    throw new PuschaException('Cancelling the push.');
                }
            }

            if ($targetVersion === $this->scmVersion) {
                $this->logger->warning('Local and target versions are the same.');
            }

            $this->setLoggerPrefix(null);
        }
    }

    /**
     * @throws PuschaException
     */
    protected function prepareChanges()
    {
        $this->logger->info('Getting changes...');

        foreach ($this->runners as $runner) {
            $this->setLoggerPrefix($runner->getName());

            try {
                $targetVersion = $runner->getCurrentVersion();

                $this->logger->info('Getting SCM changes between target version '.$targetVersion->getShortString().' and local version '.$this->scmVersion->getShortString().'');

                $changes = $this->scm->getChanges($targetVersion, $this->scmVersion);
                if (empty($changes)) {
                    throw new NoChangesException('No changes found on SCM between target and local versions, nothing to push');
                }
            } catch (NoChangesException $e) {
                if ($this->nfonc === true) {
                    // Not failing on no changes
                    $this->logger->warning($e->getMessage());
                    continue;
                } else {
                    throw $e;
                }
            }

            $this->logger->info('Found '.count($changes).' change(s) on SCM between target and local versions');

            $runner->setChanges($changes);

            // Dumping SCM changes/diff/log
            $this->scm->dumpChanges($targetVersion, $this->scmVersion, getcwd().'/'.'puscha.'.$runner->getName().'.scm_changes.log');
            $this->scm->dumpDiff($targetVersion, $this->scmVersion, getcwd().'/'.'puscha.'.$runner->getName().'.scm_diff.log');
            $this->scm->dumpLog($targetVersion, $this->scmVersion, getcwd().'/'.'puscha.'.$runner->getName().'.scm_log.log');

            // Dumping changes to push
            $changesFile = getcwd().'/'.'puscha.'.$runner->getName().'.changes.log';
            file_put_contents($changesFile, '');
            foreach ($runner->getChanges() as $change) {
                file_put_contents($changesFile, $change->getType()."\t".$change->getFile()."\n", FILE_APPEND);
            }

            $this->setLoggerPrefix(null);
        }
    }

    /**
     * Return true if current run has valid changes to push.
     *
     * @return bool
     */
    protected function checkChanges()
    {
        $this->logger->info('Checking changes...');

        $hasChanges = false;

        foreach ($this->runners as $runner) {
            $this->setLoggerPrefix($runner->getName());

            if (!empty($runner->getChanges())) {
                $hasChanges = true;
            }

            $this->setLoggerPrefix(null);
        }

        return ($hasChanges);
    }

    /**
     * @throws PuschaException
     */
    protected function push()
    {
        $this->logger->info('Pushing new files to a temporary directory on the targets...');

        foreach ($this->runners as $runner) {
            $this->setLoggerPrefix($runner->getName());

            $runner->makeTemporaryDirectory();

            $progressBar = $this->io->createProgressBar();
            $warnings = $runner->push($this->io->progressCallback($progressBar));
            $progressBar->finish();

            if ($warnings != 0) {
                $result = $this->io->confirm($warnings.' warnings were encountered while preparing the changes on target '.$runner->getName().'. Continue anyway?', false);

                if ($result === false) {
                    throw new PuschaException('Cancelling the push.');
                }
            }

            $this->setLoggerPrefix(null);
        }
    }

    protected function commit()
    {
        $this->logger->info('Moving files to their destination on the targets...');

        foreach ($this->runners as $runner) {
            $this->setLoggerPrefix($runner->getName());

            $progressBar = $this->io->createProgressBar();
            $runner->commit($this->io->progressCallback($progressBar));
            $progressBar->finish();

            $this->setLoggerPrefix(null);
        }
    }

    protected function permissions()
    {
        $this->logger->info('Updating permissions of the files on the targets...');

        foreach ($this->runners as $runner) {
            $this->setLoggerPrefix($runner->getName());

            $progressBar = $this->io->createProgressBar();
            $runner->permissions($this->io->progressCallback($progressBar));
            $progressBar->finish();

            $this->setLoggerPrefix(null);
        }
    }

    protected function revert()
    {
        $this->logger->info('Reverting changes on the targets...');

        foreach ($this->runners as $runner) {
            $this->setLoggerPrefix($runner->getName());

            $progressBar = $this->io->createProgressBar();
            $runner->revert($this->io->progressCallback($progressBar));
            $progressBar->finish();

            $this->setLoggerPrefix(null);
        }
    }

    protected function cleanup()
    {
        $this->logger->info('Cleaning up targets...');

        foreach ($this->runners as $runner) {
            $this->setLoggerPrefix($runner->getName());

            $runner->deleteTemporaryDirectory();

            $this->setLoggerPrefix(null);
        }
    }

    protected function updateTargetVersions()
    {
        $this->logger->info('Updating rev file with new version...');

        foreach ($this->runners as $runner) {
            $this->setLoggerPrefix($runner->getName());

            $runner->updateVersion($this->scmVersion);

            $this->setLoggerPrefix(null);
        }
    }

    /**
     * @param string $prefix
     */
    protected function setLoggerPrefix($prefix)
    {
        if ($this->logger instanceof PrefixedConsoleLogger) {
            $this->logger->setPrefix($prefix);
        }
    }
}
