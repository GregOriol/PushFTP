<?php

namespace Puscha\Helper;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Exception as FlysystemException;
use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException;
use LogicException;
use Psr\Log\LoggerInterface;
use Puscha\Exception\PuschaException;
use Puscha\Model\Profile;
use Puscha\Scm\ScmChange;
use Puscha\Scm\ScmVersion;
use Puscha\Target;
use Puscha\Target\TargetInterface;
use RuntimeException;

class Runner
{
    const REV_FILE       = 'rev';
    const TMP_DIR        = '_puscha_tmp';
    const TMP_PUSH_DIR   = 'push';
    const TMP_REVERT_DIR = 'revert';

    /** @var Profile */
    protected $profile;
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

    /** @var TargetInterface */
    protected $target;
    /** @var ScmVersion */
    protected $currentVersion;
    /** @var ScmChange[] */
    protected $changes = array();
    /** @var ScmChange[] */
    protected $commitedChanges = array();

    /**
     * Runner constructor.
     *
     * @param Profile         $profile
     * @param string          $base
     * @param bool            $go
     * @param bool            $lenient
     * @param bool            $nfonc
     * @param string|null     $key
     * @param LoggerInterface $logger
     *
     * @throws PuschaException
     */
    public function __construct($profile, $base, $go, $lenient, $nfonc, $key, $logger)
    {
        $this->profile = $profile;
        $this->base    = $base;
        $this->go      = $go;
        $this->lenient = $lenient;
        $this->nfonc   = $nfonc;
        $this->key     = $key;

        $this->logger  = $logger;
    }

    public function __debugInfo()
    {
        $debugInfo = get_object_vars($this);
        DebugHelper::simplifyDebugInfo($debugInfo);

        return $debugInfo;
    }

    //public function run()
    //{
    //    $this->logger->info('Running '.$this->profile->getName());
    //
    //    $this->prepareTarget();
    //}

    /**
     * @return string
     */
    public function getName()
    {
        return $this->profile->getName();
    }

    /**
     * @return ScmVersion|null
     * @throws PuschaException
     * @throws \Exception
     */
    public function getCurrentVersion()
    {
        if ($this->target === null) {
            $this->initTarget();
        }

        if ($this->currentVersion !== null) {
            return $this->currentVersion;
        }

        try {
            $contents = $this->target->read(self::REV_FILE);
            $contents = trim($contents);
        } catch (LogicException $e) {
            throw new PuschaException('Error while getting revision from target: '.$e->getMessage(), 1, $e);
        } catch (RuntimeException $e) {
            throw new PuschaException('Error while getting revision from target: '.$e->getMessage(), 1, $e);
        } catch (FileNotFoundException $e) {
            return null;
        }

        $this->currentVersion = ScmVersion::fromString($contents);

        return $this->currentVersion;
    }

    /**
     * Should only be used when the current version has not been detected (null) and is manually overridden
     *
     * @param ScmVersion $currentVersion
     */
    public function setCurrentVersion(ScmVersion $currentVersion)
    {
        $this->currentVersion = $currentVersion;
    }

    /**
     *
     */
    protected function initTarget()
    {
        $target = $this->profile->getTarget();

        $this->target = Target\Factory::create($target, $this->logger);
    }

    /**
     * @param ScmChange[] $changes
     */
    public function setChanges($changes)
    {
        $this->changes = array();

        foreach ($changes as $change) {
            // Checking types
            if (!in_array($change->getType(), array(ScmChange::TYPE_ADDED, ScmChange::TYPE_DELETED, ScmChange::TYPE_MODIFIED))) {
                $this->logger->warning('Skipping changed file with type: '.$change->getType().' ('.$change->getFile().')');
                continue;
            }

            // Checking excludes
            $excluded = false;
            foreach ($this->profile->getExcludes() as $exclude) {
                if (fnmatch($exclude, $change->getFile())) {
                    $excluded = true;
                    break;
                }
            }

            if ($excluded) {
                $this->logger->info('Skipping excluded file: '.$change->getFile());
                continue;
            }

            $this->changes[] = $change;
        }



        $this->logger->notice(count($this->changes).' change(s) to push');
    }

    /**
     * @return ScmChange[]
     */
    public function getChanges()
    {
        return $this->changes;
    }

    /**
     * Sorts a list of changes in the appropriate order for processing.
     *
     * @param ScmChange[] $changes
     *
     * @return ScmChange[]
     */
    protected static function sortChanges($changes)
    {
        $sortedChanges = $changes;
        // Sorting changes
        usort($sortedChanges, function ($a, $b) {
            /** @var ScmChange $a */
            /** @var ScmChange $b */

            if ($a->getType() == ScmChange::TYPE_ADDED && $b->getType() == ScmChange::TYPE_ADDED) {
                // sorting by "path deepness"
                return strcmp($a->getFile(), $b->getFile());
            } elseif ($a->getType() == ScmChange::TYPE_MODIFIED && $b->getType() == ScmChange::TYPE_MODIFIED) {
                // sorting by "path deepness"
                return strcmp($a->getFile(), $b->getFile());
            } elseif ($a->getType() == ScmChange::TYPE_DELETED && $b->getType() == ScmChange::TYPE_DELETED) {
                // sorting by reverse "path deepness"
                return -1 * strcmp($a->getFile(), $b->getFile());
            } else {
                // sorting by type: ADDED > MODIFIED > DELETED
                if ($a->getType() == ScmChange::TYPE_ADDED) {
                    return -1;
                } elseif ($a->getType() == ScmChange::TYPE_MODIFIED) {
                    if ($b->getType() == ScmChange::TYPE_ADDED) {
                        return 1;
                    } elseif ($b->getType() == ScmChange::TYPE_DELETED) {
                        return -1;
                    }
                } elseif ($a->getType() == ScmChange::TYPE_DELETED) {
                    return 1;
                }
            }

            return 0; // should not happen
        });

        //echo 'Changes'."\n";
        //foreach($changes as $change) {
        //    echo '  '.$change->getType().' - '.$change->getFile()."\n";;
        //}
        //echo 'Sorted changes'."\n";
        //foreach($sortedChanges as $change) {
        //    echo '  '.$change->getType().' - '.$change->getFile()."\n";;
        //}

        return $sortedChanges;
    }

    public function makeTemporaryDirectory()
    {
        if ($this->target->has(self::TMP_DIR)) {
            $this->logger->info('Found an existing temporary directory on the target, deleting it');

            $r = $this->target->deleteDir(self::TMP_DIR);
            if ($r === false) {
                throw new PuschaException('Could not delete existing temporary directory on the target: delete it manually before trying to push again');
            }
        }

        $this->target->createDir(self::TMP_DIR);
        $this->target->createDir(self::TMP_DIR.'/'.self::TMP_PUSH_DIR);
        $this->target->createDir(self::TMP_DIR.'/'.self::TMP_REVERT_DIR);
    }

    public function deleteTemporaryDirectory()
    {
        if ($this->target->has(self::TMP_DIR)) {
            $r = $this->target->deleteDir(self::TMP_DIR);

            if ($r === false) {
                $this->logger->warning('Could not cleanup temporary directory on the target: delete it manually before trying to push again');
            }
        }
    }

    public function push()
    {
        $warnings = 0;

        foreach ($this->changes as $change) {
            $file = $change->getFile();
            $filePath = $this->base.'/'.$file;
            $isDir = is_dir($filePath);

            switch ($change->getType()) {
                case ScmChange::TYPE_ADDED:
                    if ($this->target->has($file)) {
                        $this->logger->warning('File to be added '.$file.' already exists on the target');
                        $warnings += 1;
                    }

                    if ($isDir) {
                        $this->target->createDir(self::TMP_DIR.'/'.self::TMP_PUSH_DIR.'/'.$file);
                    } else {
                        $this->pushFile($file, self::TMP_DIR.'/'.self::TMP_PUSH_DIR.'/'.$file);
                    }

                    break;
                case ScmChange::TYPE_DELETED:
                    if (!$this->target->has($file)) {
                        $this->logger->warning('File to be deleted '.$file.' does not exist on the target');
                        $warnings += 1;
                    }

                    //if ($isDir) {
                    //
                    //} else {
                    //
                    //}

                    break;
                case ScmChange::TYPE_MODIFIED:
                    if (!$this->target->has($file)) {
                        $this->logger->warning('File to be modified '.$file.' does not exist on the target');
                        $warnings += 1;
                    }

                    if ($isDir) {
                        // should not happen
                    } else {
                        $this->pushFile($file, self::TMP_DIR.'/'.self::TMP_PUSH_DIR.'/'.$file);
                    }

                    break;
            }
        }

        return $warnings;
    }

    public function commit()
    {
        foreach ($this->changes as $change) {
            $file     = $change->getFile();
            $filePath = $this->base.'/'.$file;
            $isDir    = is_dir($filePath);

            try {
                switch ($change->getType()) {
                    case ScmChange::TYPE_ADDED:
                        //if ($this->target->has($file)) {
                        //    $this->target->rename($file, self::TMP_DIR.'/'.self::TMP_REVERT_DIR.'/'.$file);
                        //}

                        if ($isDir) {
                            $this->target->createDir($file);
                        } else {
                            $this->target->rename(self::TMP_DIR.'/'.self::TMP_PUSH_DIR.'/'.$file, $file);
                        }

                        $this->commitedChanges[] = $change;

                        break;
                    case ScmChange::TYPE_DELETED:
                        if ($this->target->has($file)) {
                            $this->target->copy($file, self::TMP_DIR.'/'.self::TMP_REVERT_DIR.'/'.$file);
                        }

                        // Can't know if a DELETED file is a directory or a file, so trying both
                        try {
                            $this->target->deleteDir($file);
                        } catch (FlysystemException $e) {
                            //$this->logger->debug()
                        }
                        try {
                            $this->target->delete($file);
                        } catch (FlysystemException $e) {
                            //$this->logger->debug()
                        }

                        $this->commitedChanges[] = $change;

                        break;
                    case ScmChange::TYPE_MODIFIED:
                        if ($this->target->has($file)) {
                            $this->target->rename($file, self::TMP_DIR.'/'.self::TMP_REVERT_DIR.'/'.$file);
                        }

                        if ($isDir) {
                            // should not happen
                        } else {
                            $this->target->rename(self::TMP_DIR.'/'.self::TMP_PUSH_DIR.'/'.$file, $file);
                        }

                        $this->commitedChanges[] = $change;

                        break;
                }
            } catch (FileExistsException $e) {
                $this->logger->warning('Could not commit file, already exists: '.$file);
                continue;
            } catch (FileNotFoundException $e) {
                $this->logger->warning('Could not commit file, not found: '.$file);
                continue;
            }
        }
    }

    public function permissions()
    {
        foreach ($this->changes as $change) {
            $file     = $change->getFile();
            $filePath = $this->base.'/'.$file;
            $isDir    = is_dir($filePath);

            $permission = $this->permissionForFile($file, $isDir);
            if (!$permission) {
                continue;
            }

            $adapter = $this->target->getAdapter();
            if (is_int($permission) && method_exists($adapter, 'getPermPublic') && method_exists($adapter, 'setPermPublic')) {
                // Setting the provided chmod permission as the public one
                $this->logger->debug('overriding public visibility with '.decoct($permission));
                $savedPerm = $adapter->getPermPublic();
                $adapter->setPermPublic($permission);
                $permission = 'public';
            }

            switch ($change->getType()) {
                case ScmChange::TYPE_ADDED:
                    $this->target->setVisibility($file, $permission);

                    break;
                case ScmChange::TYPE_DELETED:
                    // Nothing to change on deleted files

                    break;
                case ScmChange::TYPE_MODIFIED:
                    $this->target->setVisibility($file, $permission);

                    break;
            }

            if (isset($savedPerm)) {
                // Reverting the public perm to its previous value
                $this->logger->debug('setting back public visibility to '.decoct($savedPerm));
                $adapter->setPermPublic($savedPerm);
            }
        }
    }

    public function revert()
    {
        foreach ($this->commitedChanges as $change) {
            $file = $change->getFile();
            $filePath = $this->base.'/'.$file;
            $isDir = is_dir($filePath);

            switch ($change->getType()) {
                case ScmChange::TYPE_ADDED:
                    //if ($this->target->has(self::TMP_DIR.'/'.self::TMP_REVERT_DIR.'/'.$file)) {
                    //    $this->target->rename(self::TMP_DIR.'/'.self::TMP_REVERT_DIR.'/'.$file, $file);
                    //}

                    if ($isDir) {
                        $this->target->deleteDir($file);
                    } else {
                        $this->target->delete($file);
                    }

                    break;
                case ScmChange::TYPE_DELETED:
                    //if ($isDir) {
                    //    $this->target->createDir($file);
                    //} else {
                        $this->target->rename(self::TMP_DIR.'/'.self::TMP_REVERT_DIR.'/'.$file, $file);
                    //}

                    break;
                case ScmChange::TYPE_MODIFIED:
                    if ($isDir) {
                        // should not happen
                    } else {
                        $this->target->rename(self::TMP_DIR.'/'.self::TMP_REVERT_DIR.'/'.$file, $file);
                    }

                    break;
            }
        }
    }

    /**
     * Updated the rev file with the given version.
     *
     * @param ScmVersion $version
     */
    public function updateVersion($version)
    {
        if ($this->target->has(self::REV_FILE)) {
            $this->target->update(self::REV_FILE, $version->getFullString());
        } else {
            $this->target->write(self::REV_FILE, $version->getFullString());
        }
    }

    /**
     * Shortcut to push a file to a target
     * Might be moved somewhere else
     *
     * @param string $file
     * @param string $toPath
     *
     * @throws PuschaException
     */
    protected function pushFile($file, $toPath)
    {
        $filePath = $this->base.'/'.$file;

        if (!file_exists($filePath)) {
            throw new PuschaException('File not found: '.$file);
        }

        $stream = fopen($filePath, 'r+');
        $this->target->writeStream($toPath, $stream);
        fclose($stream);
    }

    /**
     * Finds permission to apply to a file
     *
     * @param string $file
     * @param bool   $isDir
     *
     * @return string|int|null
     */
    protected function permissionForFile($file, $isDir)
    {
        if (!$this->profile->getPermissions()) {
            return null;
        }

        foreach ($this->profile->getPermissions() as $pattern => $permissions) {
            if (!fnmatch($pattern, $file)) {
                continue;
            }

            $permission = $permissions;
            $permissions = explode('-', $permissions);
            if (count($permissions) == 2) {
                if ($isDir) {
                    $permission = $permissions[0];
                } else {
                    $permission = $permissions[1];
                }
            }

            if (method_exists($this->target->getAdapter(), 'setPermPublic')) {
                // On this target, we can set the permission value, so accepting either "public/private" or a chmod octal format
                if (!preg_match('/[0-9]{4}/', $permission) && !in_array($permission, [AdapterInterface::VISIBILITY_PUBLIC, AdapterInterface::VISIBILITY_PRIVATE])) {
                    $this->logger->error('Found permission "'.$permission.'" to apply to file '.$file.', but the value is not one of "public/private" nor a chmod octal format, skipping it');
                    continue;
                }

                $permission = octdec($permission);
            } else {
                // On this target, accepting only "public/private"
                if (!in_array($permission, [AdapterInterface::VISIBILITY_PUBLIC, AdapterInterface::VISIBILITY_PRIVATE])) {
                    $this->logger->error('Found permission "'.$permission.'" to apply to file '.$file.', but the value is not one of "public/private", skipping it');
                    continue;
                }
            }

            return $permission;
        }

        return null;
    }
}
