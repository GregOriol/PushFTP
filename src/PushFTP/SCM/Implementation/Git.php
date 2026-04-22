<?php

namespace PushFTP\SCM\Implementation;

class Git extends \PushFTP\SCM\AbstractSCM
{
	static protected function _detect($root_path)
	{
		exec('cd '.escapeshellarg($root_path).' && git rev-parse --show-toplevel 2>&1', $output, $return_var);
		return ($return_var == 0);
	}

	protected function ___construct($root_path)
	{
		$this->repo_root = '';
		$this->repo_url = '';
		$this->repo_lpath = exec('cd '.escapeshellarg($this->root_path).' && git rev-parse --abbrev-ref HEAD');
	}

	protected function _getInitialVersion()
	{
		$version = exec('cd '.escapeshellarg($this->root_path).' && git rev-list --max-parents=0 HEAD');

		// Checking if version is a hash
		$return_var = preg_match('/^[0-9a-f]{40}$/i', $version);
		if ($return_var !== 1) {
			$error = 'No initial commit found for this git repository';
			throw new \Exception($error, 1);
		}

		return $this->getTagOrBranchForCommit($version).'@'.$version;
	}

	protected function _getCurrentVersion()
	{
		$version = exec('cd '.escapeshellarg($this->root_path).' && git rev-parse HEAD');

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

	protected function _getChanges($rev, $newrev)
	{
		$rev = substr($rev, strpos($rev, '@')+1);
		$newrev = substr($newrev, strpos($newrev, '@')+1);

		exec('cd '.escapeshellarg($this->root_path).' && git diff-tree --name-status -r --relative '.escapeshellarg($rev).'..'.escapeshellarg($newrev), $output, $return_var);
		if ($return_var != 0) {
			return false;
		}

		return $output;
	}

	protected function _parseChanges($v)
	{
		$arr = explode("\t", $v);

		if (count($arr) != 2) {
			$arr = array();
			$arr[0] = '???';
			$arr[1] = '';
		}

		// TODO: check how Git handles files with spaces or special characters
		return array(
			'status' => $arr[0],
			'file' => str_replace($this->repo_root.'/'.$this->repo_rpath.'/', '', $arr[1])
		);
	}

	protected function _dumpDiff($rev, $newrev, $difffile)
	{
		$rev = substr($rev, strpos($rev, '@')+1);
		$newrev = substr($newrev, strpos($newrev, '@')+1);

		exec('cd '.escapeshellarg($this->root_path).' && git diff '.escapeshellarg($rev).'..'.escapeshellarg($newrev).' > '.escapeshellarg($difffile), $output, $return_var);

		return ($return_var == 0);
	}

	protected function _dumpLog($rev, $newrev, $logfile)
	{
		$rev = substr($rev, strpos($rev, '@')+1);
		$newrev = substr($newrev, strpos($newrev, '@')+1);

		exec('cd '.escapeshellarg($this->root_path).' && git log '.escapeshellarg($rev).'..'.escapeshellarg($newrev).' --graph --pretty=format:"%h -%d %s (%cr) <%an>" --stat > '.escapeshellarg($logfile), $output, $return_var);

		return ($return_var == 0);
	}


	private function getTagOrBranchForCommit($commithash)
	{
		// Checking if the commit belongs to a tag
		$tag = exec('cd '.escapeshellarg($this->root_path).' && git tag --points-at '.escapeshellarg($commithash).' --no-column');
		$tag = trim($tag);

		if (!empty($tag)) {
			return $tag;
		}

		// Checking if the commit belongs to a remote branch
		$branch = exec('cd '.escapeshellarg($this->root_path).' && git branch --remotes --contains '.escapeshellarg($commithash).' --no-color --no-column');
		$branch = trim($branch);

		if (!empty($branch)) {
			return $branch;
		}

		// Checking if the commit belongs to a local branch
		$branch = exec('cd '.escapeshellarg($this->root_path).' && git branch --contains '.escapeshellarg($commithash).' --no-color --no-column');
		$branch = str_replace('* ', '', $branch);
		$branch = trim($branch);

		if (!empty($branch)) {
			return $branch;
		}

		return 'HEAD';
	}
}
