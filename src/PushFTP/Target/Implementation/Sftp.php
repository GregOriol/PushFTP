<?php

// Doc : http://phpseclib.sourceforge.net/sftp/intro.html

namespace PushFTP\Target\Implementation;

class Sftp extends \PushFTP\Target\AbstractTarget
{
	protected function ___construct($host, $port = false)
	{
		if (!$port) {
			$port = 22;
		}
		
		$this->host = $host;
		$this->port = $port;
	}

	protected function _connect()
	{
		$this->handle = new \phpseclib\Net\SFTP($this->host, $this->port, 30);
	}

	protected function _login($username, $password)
	{
		return $this->handle->login($username, $password);
	}

	protected function _isError($response)
	{
		return ($response === false);
	}

	protected function _setPassive()
	{
		return null;
	}

	protected function _get($remote_path, $local_path)
	{
		return $this->handle->get($remote_path, $local_path);
	}

	protected function _put($local_path, $remote_path, $overwrite)
	{
		// $overwrite is not used as files are Net_SFTP doesn't fail on existing files and always overwrites
		return $this->handle->put($remote_path, $local_path, NET_SFTP_LOCAL_FILE, -1);
	}

	protected function _mkdir($remote_path, $recursive)
	{
		return $this->handle->mkdir($remote_path, -1, $recursive);
	}

	protected function _pwd()
	{
		return $this->handle->pwd();
	}

	protected function _cd($remote_path)
	{
		return $this->handle->chdir($remote_path);
	}

	protected function _rename($remote_path_from, $remote_path_to)
	{
		// first trying to delete the target, since Net_SFTP doesn't seem to perform overwriting
		$this->handle->delete($remote_path_to, true);
		return $this->handle->rename($remote_path_from, $remote_path_to);
	}

	protected function _rm($remote_path, $recursive)
	{
		return $this->handle->delete($remote_path, $recursive);
	}

	protected function _chmod($remote_path, $permissions, $recursive)
	{
		return $this->handle->chmod($permissions, $remote_path, $recursive);
	}
}
