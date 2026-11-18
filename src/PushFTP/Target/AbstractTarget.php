<?php

namespace PushFTP\Target;

use phpseclib3\Crypt\Common\PrivateKey;

abstract class AbstractTarget implements TargetInterface
{
	protected string $host;
	protected int $port;

	public function __construct(string $host, ?int $port = null)
	{
		if ($host === '') {
			throw new \Exception('No host defined');
		}

		$this->___construct($host, $port);
	}
	abstract protected function ___construct(string $host, ?int $port = null): void;


	public function connect(): void
	{
		$this->_connect();
	}
	abstract protected function _connect(): void;


	public function login(string $username, string|PrivateKey $password): bool
	{
		return $this->_login($username, $password);
	}
	abstract protected function _login(string $username, string|PrivateKey $password): bool;


	public function isError(mixed $result): bool
	{
		return $this->_isError($result);
	}
	abstract protected function _isError(mixed $result): bool;


	public function get(string $remote_path, string $local_path): bool
	{
		return $this->_get($remote_path, $local_path);
	}
	abstract protected function _get(string $remote_path, string $local_path): bool;


	public function put(string $local_path, string $remote_path, bool $overwrite): bool
	{
		return $this->_put($local_path, $remote_path, $overwrite);
	}
	abstract protected function _put(string $local_path, string $remote_path, bool $overwrite): bool;


	public function mkdir(string $remote_path, bool $recursive = false): bool
	{
		return $this->_mkdir($remote_path, $recursive);
	}
	abstract protected function _mkdir(string $remote_path, bool $recursive): bool;


	public function pwd(): string|false
	{
		return $this->_pwd();
	}
	abstract protected function _pwd(): string|false;


	public function cd(string $remote_path): bool
	{
		return $this->_cd($remote_path);
	}
	abstract protected function _cd(string $remote_path): bool;


	public function rename(string $remote_path_from, string $remote_path_to): bool
	{
		return $this->_rename($remote_path_from, $remote_path_to);
	}
	abstract protected function _rename(string $remote_path_from, string $remote_path_to): bool;


	public function rm(string $remote_path, bool $recursive): bool
	{
		return $this->_rm($remote_path, $recursive);
	}
	abstract protected function _rm(string $remote_path, bool $recursive): bool;


	public function chmod(string $remote_path, int $permissions, bool $recursive = false): bool
	{
		return $this->_chmod($remote_path, $permissions, $recursive);
	}
	abstract protected function _chmod(string $remote_path, int $permissions, bool $recursive): bool;
}
