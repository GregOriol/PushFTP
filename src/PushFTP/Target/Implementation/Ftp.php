<?php

// Doc : http://pear.php.net/package/Net_FTP/docs

namespace PushFTP\Target\Implementation;

class Ftp extends \PushFTP\Target\AbstractTarget
{
	protected function ___construct($host, $port = false)
	{
		if (!$port) {
			$port = 21;
		}
		
		$this->host = $host;
		$this->port = $port;
		
		$this->handle = new \Net_FTP($host, $port, 30);
	}

	protected function _connect()
	{
		return $this->handle->connect();
	}

	protected function _login($username, $password)
	{
		return $this->handle->login($username, $password);
	}

	protected function _isError($response)
	{
		return \PEAR::isError($response);
	}

	protected function _setPassive()
	{
		return $this->handle->setPassive();
	}

	protected function _get($remote_path, $local_path)
	{
		return $this->handle->get($remote_path, $local_path);
	}

	protected function _put($local_path, $remote_path, $overwrite)
	{
		return $this->handle->put($local_path, $remote_path, $overwrite);
	}

	protected function _mkdir($remote_path, $recursive)
	{
		return $this->handle->mkdir($remote_path, $recursive);
	}

	protected function _pwd()
	{
		return $this->handle->pwd();
	}

	protected function _cd($remote_path)
	{
		return $this->handle->cd($remote_path);
	}

	protected function _rename($remote_path_from, $remote_path_to)
	{
		return $this->handle->rename($remote_path_from, $remote_path_to);
	}

	protected function _rm($remote_path, $recursive)
	{
		return $this->handle->rm($remote_path, $recursive);
	}

	protected function _chmod($remote_path, $permissions, $recursive)
	{
		$permissions = decoct($permissions);
		
		if (!$recursive) {
			return $this->handle->chmod($remote_path, $permissions);
		} else {
			return $this->handle->chmodRecursive($remote_path, $permissions);
		}
	}
}
