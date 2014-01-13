<?php

// Doc : http://phpseclib.sourceforge.net/sftp/intro.html

namespace Pusher\Connector\Implementation;

class Sftp extends \Pusher\Connector\AbstractConnector
{
	public function ___construct($host, $port = false)
	{
		if (!$port) {
			$port = 22;
		}
		
		$this->host = $host;
		$this->port = $port;
	}

	public function _connect()
	{
		$this->handle = new \Net_SFTP($this->host, $this->port);
	}

	public function _login($username, $password)
	{
		return $this->handle->login($username, $password);
	}

	public function _isError($response)
	{
		return ($response === false);
	}

	public function _setPassive()
	{
		return null;
	}

	public function _get($remote_path, $local_path)
	{
		return $this->handle->get($remote_path, $local_path);
	}

	public function _put($local_path, $remote_path, $overwrite)
	{
		// $overwrite is not used as files are Net_SFTP doesn't fail on existing files always overwrites
		return $this->handle->put($remote_path, $local_path, NET_SFTP_LOCAL_FILE, -1);
	}

	public function _mkdir($remote_path, $recursive)
	{
		return $this->handle->mkdir($remote_path, -1, $recursive);
	}

	public function _pwd()
	{
		return $this->handle->pwd();
	}

	public function _cd($remote_path)
	{
		return $this->handle->chdir($remote_path);
	}

	public function _rename($remote_path_from, $remote_path_to)
	{
		// first trying to delete the target, since Net_SFTP doesn't seem to perform overwriting
		$this->handle->delete($remote_path_to, true);
		return $this->handle->rename($remote_path_from, $remote_path_to);
	}

	public function _rm($remote_path, $recursive)
	{
		return $this->handle->delete($remote_path, $recursive);
	}

	public function _chmod($remote_path, $permissions, $recursive)
	{
		return $this->handle->chmod($permissions, $remote_path, $recursive);
	}
}

?>
