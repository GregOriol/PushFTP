<?php

namespace Puscha\Scm;

use Puscha\Exception\PuschaException;
use Puscha\Exception\ScmPathException;
use Puscha\Exception\ScmVersionException;

class SvnScm extends AbstractScm
{
    /**
     * {@inheritdoc}
     */
    public function __construct($path, $logger)
    {
        parent::__construct($path, $logger);

        $this->exec('svn info 2>/dev/null', $output, $return_var);
        if ($return_var !== 0) {
            throw new ScmPathException('No Git SCM repository found at path: '.$path);
        }

        $this->repositoryRoot = $this->exec('svn info | grep -E \'^Repository Root\' | awk \'{print $NF}\'');
        $this->repositoryId = $this->exec('svn info | grep -E \'^Repository UUID\' | awk \'{print $NF}\'');
        $repositoryUrl  = $this->exec('svn info | grep -E \'^URL\' | awk \'{print $NF}\'');
        $this->repositoryTree = str_replace($this->repositoryRoot.'/', '', $repositoryUrl);
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentVersion()
    {
        $revision = $this->exec('svnversion');

        if (!is_numeric($revision)) {
            $error = 'SVN revision error, detected value "'.$revision.'" is not a valid revision';
            if (strpos($revision, ':') !== false) {
                $error = 'SVN revision has multiple states accross the local checkout and is not valid for push, try `svn update` to get a uniform state';
            } elseif (strpos($revision, 'M') !== false) {
                $localChanges = $this->getChanges();
                if (!empty($localChanges)) {
                    $this->logger->warning('SVN repository has local modifications:');
                    foreach ($localChanges as $change) {
                        $this->logger->warning('  '.$change);
                    }
                }

                $error = 'SVN repository has local modifications and is not valid for push, try commiting them or use a diff/patch';
            }
            throw new ScmVersionException($error);
        }

        return new ScmVersion($this->repositoryTree, $revision, $this->repositoryId);
    }

    /**
     * {@inheritdoc}
     */
    public function getInitialVersion()
    {
        return new ScmVersion($this->repositoryTree, '1', $this->repositoryId);
    }

    /**
     * {@inheritdoc}
     */
    public function getChanges($fromVersion = null, $toVersion = null)
    {
        if (($fromVersion === null && $toVersion !== null) || ($fromVersion !== null && $toVersion === null)) {
            throw new PuschaException('Invalid call to getChanges');
        }

        $revisions = '';
        if ($fromVersion !== null && $toVersion !== null) {
            $fromRevision = $fromVersion->getShortString();
            $toRevision   = $toVersion->getShortString();

            $revisions = $this->repositoryRoot.'/'.$fromRevision.' '.$this->repositoryRoot.'/'.$toRevision;
        }

        $this->exec('svn diff --summarize --show-copies-as-adds '.$revisions.'', $output, $return_var);
        if ($return_var !== 0) {
            throw new PuschaException('An error occurred while getting Git changes');
        }

        $changes = array_map(function($v) { return $this->parseChange($v); }, $output);
        $changes = array_filter($changes); // removing invalid changes

        return $changes;
    }

    /**
     * @param string $change An Svn change row
     *
     * @return ScmChange|bool Returns an scm change object or false if could not parse
     */
    protected function parseChange($change)
    {
        // SVN change format described here:
        // http://svnbook.red-bean.com/en/1.7/svn.ref.svn.c.status.html

        $r = preg_match('/^([A-Z])[A-Z? ]{7}(.*)$/', $change, $matches);

        if ($r !== 1 || count($matches) !== 3) {
            $this->logger->notice('Unable to parse SVN change: "'.$change.'"');
            return false;
        }

        // Type
        $changeType = ScmChange::TYPE_UNKNOWN;
        switch ($matches[1]) {
            case 'A':
                $changeType = ScmChange::TYPE_ADDED;
                break;
            case 'D':
                $changeType = ScmChange::TYPE_DELETED;
                break;
            case 'M':
                $changeType = ScmChange::TYPE_MODIFIED;
                break;
        }

        // File
        $changeFile = urldecode($matches[2]); // decoding file names with special characters (encoded in url by svn)
        $changeFile = str_replace($this->repositoryRoot.'/'.$this->repositoryTree.'/', '', $changeFile); // removing url of the repository to only keep file names

        return new ScmChange($changeType, $changeFile);
    }

    /**
     * {@inheritdoc}
     */
    public function dumpChanges($fromVersion, $toVersion, $file)
    {
        if ($fromVersion === null || $toVersion === null) {
            throw new PuschaException('Invalid call to dumpChanges');
        }

        $fromRevision = $fromVersion->getShortString();
        $toRevision   = $toVersion->getShortString();

        $this->exec('svn diff --summarize --show-copies-as-adds '.$this->repositoryRoot.'/'.$fromRevision.' '.$this->repositoryRoot.'/'.$toRevision.' > '.$file, $output, $return_var);

        return ($return_var == 0);
    }

    /**
     * {@inheritdoc}
     */
    public function dumpDiff($fromVersion, $toVersion, $file)
    {
        if ($fromVersion === null || $toVersion === null) {
            throw new PuschaException('Invalid call to dumpDiff');
        }

        $fromRevision = $fromVersion->getShortString();
        $toRevision   = $toVersion->getShortString();

        $this->exec('svn diff '.$this->repositoryRoot.'/'.$fromRevision.' '.$this->repositoryRoot.'/'.$toRevision.' > '.$file, $output, $return_var);

        return ($return_var == 0);
    }

    /**
     * {@inheritdoc}
     */
    public function dumpLog($fromVersion, $toVersion, $file)
    {
        if ($fromVersion === null || $toVersion === null) {
            throw new PuschaException('Invalid call to dumpLog');
        }

        $fromRevision = $fromVersion->getRevision();
        $toRevision   = $toVersion->getRevision();

        $this->exec('svn log --revision '.$fromRevision.':'.$toRevision.' --verbose '.$this->repositoryRoot.' > '.$file, $output, $return_var);

        return ($return_var == 0);
    }
}
