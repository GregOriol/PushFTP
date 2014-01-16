<?php

namespace Pusher\SCM\Implementation;

class Git extends \Pusher\SCM\AbstractSCM
{
	public function ___construct($root_path)
	{
		$this->repo_root = '';
		$this->repo_url = '';
		$this->repo_lpath = exec('cd '.$this->root_path.' && git rev-parse --abbrev-ref HEAD');
		// $this->repo_lpath = '';
	}

	public function _getInitialCommit()
	{
		$version = exec('cd '.$this->root_path.' && git rev-list --max-parents=0 HEAD');
		
		// Checking if version is a hash
		$return_var = preg_match('/^[0-9a-f]{40}$/i', $version);
		if ($return_var !== 1) {
			$error = 'No initial commit found for this git repository';
			throw new \Exception($error, 1);
		}

		// TODO: enhance search for initial commit (might not be master)
		return 'master'.'@'.$version;
	}

	public function _getCurrentVersion()
	{
		// $version = exec('cd '.$this->root_path.' && git log -1 --format="%H"'); // candidate for cleanup
		$version = exec('cd '.$this->root_path.' && git rev-parse HEAD');
		
		// Checking if version is a hash
		$return_var = preg_match('/^[0-9a-f]{40}$/i', $version);
		if ($return_var !== 1) {
			$error = 'Local Git revision error, value "'.$version.'" is not a valid revision';
			throw new \Exception($error, 1);
		}
		
		// Checking for local changes
		exec('cd '.$this->root_path.' && git diff --quiet', $output, $return_var);
		if ($return_var != 0) {
			$error = 'Local Git checkout has local modifications and is not valid for push, try commiting them or use a stash';
			throw new \Exception($error, 1);
		}
		
		return $this->repo_lpath.'@'.$version;
	}

	public function _getChanges($rev, $newrev)
	{
		$rev = substr($rev, strpos($rev, '@')+1);
		$newrev = substr($newrev, strpos($newrev, '@')+1);
		
		exec('cd '.$this->root_path.' && git diff --name-status '.$rev.'..'.$newrev.'', $output, $return_var);
		if ($return_var != 0) {
			return false;
		}
		
		return $output;
	}

	public function _parseChanges($v)
	{
		$arr = explode("\t", $v);

		if (count($arr) != 2) {
			$arr = array();
			$arr[0] = '???';
			$arr[1] = '';
		}

		return array(
			'status' => $arr[0],
			'file' => str_replace($this->repo_root.'/'.$this->repo_rpath.'/', '', $arr[1])
		);
	}
}

?>
