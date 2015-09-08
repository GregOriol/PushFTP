<?php

namespace PushFTP\Target;

abstract class AbstractTarget implements TargetInterface
{
	var $handle = false;

	var $host;
	var $port;

	public function __construct($host, $port = false)
	{
		if (!$host || empty($host)) {
			throw new Excpetion('No host defined');
		}

		return $this->___construct($host, $port);
	}
	abstract protected function ___construct($host, $port = false);


	public function connect()
	{
		return $this->_connect();
	}
	abstract protected function _connect();


	public function login($username, $password)
	{
		return $this->_login($username, $password);
	}
	abstract protected function _login($username, $password);


	public function isError($result)
	{
		return $this->_isError($result);
	}
	abstract protected function _isError($result);


	public function setPassive()
	{
		return $this->_setPassive();
	}
	abstract protected function _setPassive();


	public function get($remote_path, $local_path)
	{
		return $this->_get($remote_path, $local_path);
	}
	abstract protected function _get($remote_path, $local_path);


	public function put($local_path, $remote_path, $overwrite)
	{
		return $this->_put($local_path, $remote_path, $overwrite);
	}
	abstract protected function _put($local_path, $remote_path, $overwrite);


	public function mkdir($remote_path, $recursive = false)
	{
		return $this->_mkdir($remote_path, $recursive);
	}
	abstract protected function _mkdir($remote_path, $recursive);


	public function pwd()
	{
		return $this->_pwd();
	}
	abstract protected function _pwd();


	public function cd($remote_path)
	{
		return $this->_cd($remote_path);
	}
	abstract protected function _cd($remote_path);


	public function rename($remote_path_from, $remote_path_to)
	{
		return $this->_rename($remote_path_from, $remote_path_to);
	}
	abstract protected function _rename($remote_path_from, $remote_path_to);


	public function rm($remote_path, $recursive)
	{
		return $this->_rm($remote_path, $recursive);
	}
	abstract protected function _rm($remote_path, $recursive);


	public function chmod($remote_path, $permissions, $recursive = false)
	{
		return $this->_chmod($remote_path, $permissions, $recursive);
	}
	abstract protected function _chmod($remote_path, $permissions, $recursive);
}
