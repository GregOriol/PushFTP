<?php

namespace Pusher\SCM;

abstract class AbstractSCM implements SCMInterface
{
	var $root_path;

	var $repo_root;
	var $repo_url;
	var $repo_lpath;

	var $repo_rpath;

	public function __construct($root_path)
	{
		if (!$root_path || empty($root_path)) {
			throw new Excpetion('No root path defined');
		}

		$this->root_path = $root_path;

		$this->repo_root = '';
		$this->repo_url = '';
		$this->repo_lpath = '';

		return $this->___construct($root_path);
	}
	abstract protected function ___construct($root_path);


	public function getInitialCommit()
	{
		return $this->_getInitialCommit();
	}
	abstract protected function _getInitialCommit();


	public function getCurrentVersion()
	{
		return $this->_getCurrentVersion();
	}
	abstract protected function _getCurrentVersion();


	public function getChanges($rev, $newrev)
	{
		return $this->_getChanges($rev, $newrev);
	}
	abstract protected function _getChanges($rev, $newrev);


	public function parseChanges($v)
	{
		return $this->_parseChanges($v);
	}
	abstract protected function _parseChanges($v);
}

?>
