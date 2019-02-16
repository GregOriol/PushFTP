<?php

namespace Puscha\Scm;

use Puscha\Exception\PuschaException;
use Puscha\Exception\ScmPathException;
use Puscha\Exception\ScmVersionException;

class GitScm extends AbstractScm
{
    /**
     * {@inheritdoc}
     */
    public function __construct($path, $logger)
    {
        parent::__construct($path, $logger);

        $this->exec('git rev-parse --show-toplevel 2>/dev/null', $output, $return_var);
        if ($return_var !== 0) {
            throw new ScmPathException('No Git SCM repository found at path: '.$path);
        }

        $this->repositoryRoot = '';
        $this->repositoryId = $this->getInitialVersion()->getRevision();
        //$this->repositoryUrl  = '';
        $this->repositoryTree = $this->exec('git rev-parse --abbrev-ref HEAD');
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentVersion()
    {
        $revision = $this->exec('git rev-parse HEAD');

        // Checking if revision is a hash
        if (preg_match('/^[0-9a-f]{40,}$/i', $revision) !== 1) {
            $error = 'Git revision error, detected value "'.$revision.'" is not a valid revision';
            throw new ScmVersionException($error);
        }

        // Checking for local changes
        $localChanges = $this->getChanges();
        if (!empty($localChanges)) {
            $this->logger->warning('Git repository has local modifications:');
            foreach ($localChanges as $change) {
                $this->logger->warning('  '.$change);
            }

            $error = 'Git repository has local modifications and is not valid for push, try commiting them or use a stash';
            throw new ScmVersionException($error);
        }

        return new ScmVersion($this->repositoryTree, $revision, $this->repositoryId);
    }

    /**
     * {@inheritdoc}
     */
    public function getInitialVersion()
    {
        $revision = $this->exec('git rev-list --max-parents=0 HEAD');

        // Checking if revision is a hash
        if (preg_match('/^[0-9a-f]{40,}$/i', $revision) !== 1) {
            $error = 'Could not find initial commit for this Git repository';
            throw new ScmVersionException($error);
        }

        return new ScmVersion($this->getTagOrBranchForCommit($revision), $revision, $this->repositoryId);
    }

    /**
     * {@inheritdoc}
     */
    public function getChanges($fromVersion = null, $toVersion = null)
    {
        if (($fromVersion === null && $toVersion !== null) || ($fromVersion !== null && $toVersion === null)) {
            throw new PuschaException('Invalid call to getChanges');
        }

        $cmd = 'git diff --name-status -r -t --relative';

        if ($fromVersion !== null && $toVersion !== null) {
            $fromRevision = $fromVersion->getRevision();
            $toRevision   = $toVersion->getRevision();

            $cmd = 'git diff-tree --name-status -r -t --relative '.$fromRevision.'..'.$toRevision.'';
        }

        $this->exec($cmd, $output, $return_var);
        if ($return_var !== 0) {
            throw new PuschaException('An error occurred while getting Git changes');
        }

        $changes = array_map(array($this, 'parseChange'), $output);
        $changes = array_filter($changes); // removing invalid changes

        return $changes;
    }

    /**
     * @param string $change A Git change row
     *
     * @return ScmChange|bool Returns an scm change object or false if could not parse
     */
    protected function parseChange($change)
    {
        // Git change format described here:
        // https://www.kernel.org/pub/software/scm/git/docs/git-diff-tree.html

        //$changeParts = explode("\t", $change);
        $r = preg_match('/^([A-Z])	(.*)$/', $change, $matches);

        if ($r !== 1 || count($matches) !== 3) {
            $this->logger->warning('Unable to parse Git change: "'.$change.'"');
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
        $changeFile = trim($matches[2], "\""); // removing quotes on file names with spaces
        $changeFile = stripcslashes($changeFile); // decoding file names with special characters (encoded in octal by git)

        return new ScmChange($changeType, $changeFile);
    }

    /**
     * Returns the name of the tag or branch to which a commit belongs.
     *
     * @param string $commithash
     *
     * @return string
     */
    protected function getTagOrBranchForCommit($commithash)
    {
        // Checking if the commit belongs to a tag
        $tag = $this->exec('git tag --points-at '.$commithash.' --no-column');
        $tag = trim($tag);

        if (!empty($tag)) {
            return $tag;
        }

        // Checking if the commit belongs to a remote branch
        $branch = $this->exec('git branch --remotes --contains '.$commithash.' --no-color --no-column');
        $branch = trim($branch);

        if (!empty($branch)) {
            return $branch;
        }

        // Checking if the commit belongs to a local branch
        $branch = $this->exec('git branch --contains '.$commithash.' --no-color --no-column');
        $branch = str_replace('* ', '', $branch);
        $branch = trim($branch);

        if (!empty($branch)) {
            return $branch;
        }

        return 'HEAD';
    }

    /**
     * {@inheritdoc}
     */
    public function dumpChanges($fromVersion, $toVersion, $file)
    {
        if ($fromVersion === null || $toVersion === null) {
            throw new PuschaException('Invalid call to dumpChanges');
        }

        $fromRevision = $fromVersion->getRevision();
        $toRevision   = $toVersion->getRevision();

        $this->exec('git diff-tree --name-status -r -t --relative '.$fromRevision.'..'.$toRevision.' > '.$file, $output, $return_var);

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

        $fromRevision = $fromVersion->getRevision();
        $toRevision   = $toVersion->getRevision();

        $this->exec('git diff '.$fromRevision.'..'.$toRevision.' > '.$file, $output, $return_var);

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

        $this->exec('git log '.$fromRevision.'..'.$toRevision.' --graph --pretty=format:"%h -%d %s (%cr) <%an>" --stat > '.$file, $output, $return_var);

        return ($return_var == 0);
    }
}
