<?php

namespace PushFTP\SCM\Implementation;

class SVN extends \PushFTP\SCM\AbstractSCM
{
	static protected function _detect($root_path)
	{
		exec('cd '.escapeshellarg($root_path).' && svn info 2>/dev/null', $output, $return_var);
		return ($return_var == 0);
	}

	protected function ___construct($root_path)
	{
		$this->repo_root = exec('cd '.escapeshellarg($this->root_path).' && svn info | grep \'Repository Root\' | awk \'{print $NF}\'');
		$this->repo_url = exec('cd '.escapeshellarg($this->root_path).' && svn info | grep \'URL\' | awk \'{print $NF}\'');
		$this->repo_lpath = str_replace($this->repo_root.'/', '', $this->repo_url);
	}

	protected function _getInitialVersion()
	{
		// TODO: enhance search for initial commit (might not be trunk)
		return 'trunk@1';
	}

	protected function _getCurrentVersion()
	{
		$version = exec('cd '.escapeshellarg($this->root_path).' && svnversion');

		if (!is_numeric($version)) {
			$error = 'Local SVN revision error, value "'.$version.'" is not a valid revision';
			if (strpos($version, ':') !== false) {
				$error = 'Local SVN revision has multiple states and is not valid for push, try `svn update` to get a uniform state';
			} elseif (strpos($version, 'M') !== false) {
				$error = 'Local SVN revision has local modifications and is not valid for push, try commiting them or use svn diff/patch';
			}
			throw new \Exception($error, 1);
		}

		return $this->repo_lpath.'@'.$version;
	}

	protected function _getChanges($rev, $newrev)
	{
		exec('cd '.escapeshellarg($this->root_path).' && svn diff --summarize '.escapeshellarg($this->repo_root.'/'.$rev).' '.escapeshellarg($this->repo_root.'/'.$newrev), $output, $return_var);
		if ($return_var != 0) {
			return false;
		}

		return $output;
	}

	protected function _parseChanges($v)
	{
		$arr = explode('       ', $v);

		if (count($arr) != 2) {
			$arr = array();
			$arr[0] = '???';
			$arr[1] = '';
		}

		return array(
			'status' => $arr[0],
			'file' => str_replace($this->repo_root.'/'.$this->repo_rpath.'/', '', urldecode($arr[1]))
		);
	}

	protected function _dumpDiff($rev, $newrev, $difffile)
	{
		$diff = exec('cd '.escapeshellarg($this->root_path).' && svn diff '.escapeshellarg($this->repo_root.'/'.$rev).' '.escapeshellarg($this->repo_root.'/'.$newrev).' > '.escapeshellarg($difffile), $output, $return_var);

		return ($return_var == 0);
	}

	protected function _dumpLog($rev, $newrev, $logfile)
	{
		$rev = substr($rev, strpos($rev, '@')+1);
		$newrev = substr($newrev, strpos($newrev, '@')+1);

		$diff = exec('cd '.escapeshellarg($this->root_path).' && svn log --revision '.escapeshellarg($rev).':'.escapeshellarg($newrev).' --verbose '.escapeshellarg($this->repo_root).' > '.escapeshellarg($logfile), $output, $return_var);

		return ($return_var == 0);
	}
}
