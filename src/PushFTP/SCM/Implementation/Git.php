<?php

namespace PushFTP\SCM\Implementation;

class Git extends \PushFTP\SCM\AbstractSCM
{
	static protected function _detect(string $root_path): bool
	{
		exec('cd '.escapeshellarg($root_path).' && git rev-parse --show-toplevel 2>&1', $output, $return_var);
		return ($return_var == 0);
	}

	protected function ___construct(string $root_path): void
	{
	}

	protected function _getInitialVersion(): string
	{
		$version = $this->_execLine('cd '.escapeshellarg($this->root_path).' && git rev-list --max-parents=0 HEAD');

		// Checking if version is a hash
		$return_var = preg_match('/^[0-9a-f]{40}$/i', $version);
		if ($return_var !== 1) {
			$error = 'No initial commit found for this git repository';
			throw new \Exception($error, 1);
		}

		return $this->getTagOrBranchForCommit($version).'@'.$version;
	}

	protected function _getCurrentVersion(): string
	{
		$version = $this->_execLine('cd '.escapeshellarg($this->root_path).' && git rev-parse HEAD');

		// Checking if version is a hash
		$return_var = preg_match('/^[0-9a-f]{40}$/i', $version);
		if ($return_var !== 1) {
			$error = 'Local Git revision error, value "'.$version.'" is not a valid revision';
			throw new \Exception($error, 1);
		}

		// Checking for local changes
		exec('cd '.escapeshellarg($this->root_path).' && git diff --quiet', $output, $return_var);
		if ($return_var != 0) {
			$error = 'Local Git checkout has local modifications and is not valid for push, try commiting them or use a stash';
			throw new \Exception($error, 1);
		}

		return $this->getTagOrBranchForCommit($version).'@'.$version;
	}

	/**
	 * @return list<string>|false
	 */
	protected function _getChanges(string $rev, string $newrev): array|false
	{
		$rev = substr($rev, strpos($rev, '@')+1);
		$newrev = substr($newrev, strpos($newrev, '@')+1);

		exec('cd '.escapeshellarg($this->root_path).' && git -c core.quotePath=false diff-tree --name-status -r --relative '.escapeshellarg($rev).'..'.escapeshellarg($newrev), $output, $return_var);
		if ($return_var != 0) {
			return false;
		}

		return $output;
	}

	/**
	 * @return array{status: string, file: string}
	 */
	protected function _parseChanges(string $v): array
	{
		$arr = explode("\t", $v, 2);

		if (count($arr) != 2) {
			$arr = ['???', ''];
		}

		return [
			'status' => $arr[0],
			'file' => $arr[1]
		];
	}

	protected function _dumpDiff(string $rev, string $newrev, string $difffile): bool
	{
		$rev = substr($rev, strpos($rev, '@')+1);
		$newrev = substr($newrev, strpos($newrev, '@')+1);

		exec('cd '.escapeshellarg($this->root_path).' && git diff '.escapeshellarg($rev).'..'.escapeshellarg($newrev).' > '.escapeshellarg($difffile), $output, $return_var);

		return ($return_var == 0);
	}

	protected function _dumpLog(string $rev, string $newrev, string $logfile): bool
	{
		$rev = substr($rev, strpos($rev, '@')+1);
		$newrev = substr($newrev, strpos($newrev, '@')+1);

		exec('cd '.escapeshellarg($this->root_path).' && git log '.escapeshellarg($rev).'..'.escapeshellarg($newrev).' --graph --pretty=format:"%h -%d %s (%cr) <%an>" --stat > '.escapeshellarg($logfile), $output, $return_var);

		return ($return_var == 0);
	}


	private function getTagOrBranchForCommit(string $commithash): string
	{
		// Checking if the commit belongs to a tag
		$tag = $this->_execLine('cd '.escapeshellarg($this->root_path).' && git tag --points-at '.escapeshellarg($commithash).' --no-column');
		$tag = trim($tag);

		if (!empty($tag)) {
			return $tag;
		}

		// Checking if the commit belongs to a remote branch
		$branch = $this->_execLine('cd '.escapeshellarg($this->root_path).' && git branch --remotes --contains '.escapeshellarg($commithash).' --no-color --no-column');
		$branch = trim($branch);

		if (!empty($branch)) {
			return $branch;
		}

		// Checking if the commit belongs to a local branch
		$branch = $this->_execLine('cd '.escapeshellarg($this->root_path).' && git branch --contains '.escapeshellarg($commithash).' --no-color --no-column');
		$branch = str_replace('* ', '', $branch);
		$branch = trim($branch);

		if (!empty($branch)) {
			return $branch;
		}

		return 'HEAD';
	}

	/**
	 * Executes a command and returns its last output line
	 *
	 * @param string $command
	 * @return string empty on failure
	 */
	private function _execLine(string $command): string
	{
		$line = exec($command);

		return ($line === false) ? '' : $line;
	}
}
