<?php

namespace PushFTP\SCM;

abstract class AbstractSCM implements SCMInterface
{
	var $root_path;

	var $repo_root;
	var $repo_url;
	var $repo_lpath;

	var $repo_rpath;

	static public function detect($root_path)
	{
		return static::_detect($root_path);
	}
	abstract protected function _detect($root_path);

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


	public function getInitialVersion()
	{
		return $this->_getInitialVersion();
	}
	abstract protected function _getInitialVersion();


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


	public function dumpDiff($rev, $newrev, $difffile)
	{
		return $this->_dumpDiff($rev, $newrev, $difffile);
	}
	abstract protected function _dumpDiff($rev, $newrev, $difffile);


	public function dumpLog($rev, $newrev, $logfile)
	{
		return $this->_dumpLog($rev, $newrev, $logfile);
	}
	abstract protected function _dumpLog($rev, $newrev, $logfile);
}
