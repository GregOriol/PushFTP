<?php

namespace PushFTP\SCM;

abstract class AbstractSCM implements SCMInterface
{
	protected string $root_path;

	static public function detect(string $root_path): bool
	{
		return static::_detect($root_path);
	}
	abstract static protected function _detect(string $root_path): bool;

	public function __construct(string $root_path)
	{
		if ($root_path === '') {
			throw new \Exception('No root path defined');
		}

		$this->root_path = $root_path;

		$this->___construct($root_path);
	}
	abstract protected function ___construct(string $root_path): void;


	public function getInitialVersion(): string
	{
		return $this->_getInitialVersion();
	}
	abstract protected function _getInitialVersion(): string;


	public function getCurrentVersion(): string
	{
		return $this->_getCurrentVersion();
	}
	abstract protected function _getCurrentVersion(): string;


	/**
	 * @return list<string>|false
	 */
	public function getChanges(string $rev, string $newrev): array|false
	{
		return $this->_getChanges($rev, $newrev);
	}
	/**
	 * @return list<string>|false
	 */
	abstract protected function _getChanges(string $rev, string $newrev): array|false;


	/**
	 * @return array{status: string, file: string}
	 */
	public function parseChanges(string $v): array
	{
		return $this->_parseChanges($v);
	}
	/**
	 * @return array{status: string, file: string}
	 */
	abstract protected function _parseChanges(string $v): array;


	public function dumpDiff(string $rev, string $newrev, string $difffile): bool
	{
		return $this->_dumpDiff($rev, $newrev, $difffile);
	}
	abstract protected function _dumpDiff(string $rev, string $newrev, string $difffile): bool;


	public function dumpLog(string $rev, string $newrev, string $logfile): bool
	{
		return $this->_dumpLog($rev, $newrev, $logfile);
	}
	abstract protected function _dumpLog(string $rev, string $newrev, string $logfile): bool;
}
