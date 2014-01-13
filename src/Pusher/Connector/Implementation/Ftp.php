<?php

// Doc : http://pear.php.net/package/Net_FTP/docs

namespace Pusher\Connector\Implementation;

class Ftp extends \Pusher\Connector\AbstractConnector
{
	public function ___construct($host, $port = false)
	{
		if (!$port) {
			$port = 21;
		}
		
		$this->host = $host;
		$this->port = $port;
		
		$this->handle = new \Net_FTP($host, $port);
	}

	public function _connect()
	{
		return $this->handle->connect();
	}

	public function _login($username, $password)
	{
		return $this->handle->login($username, $password);
	}

	public function _isError($response)
	{
		return \PEAR::isError($response);
	}

	public function _setPassive()
	{
		return $this->handle->setPassive();
	}

	public function _get($remote_path, $local_path)
	{
		return $this->handle->get($remote_path, $local_path);
	}

	public function _put($local_path, $remote_path, $overwrite)
	{
		return $this->handle->put($local_path, $remote_path, $overwrite);
	}

	public function _mkdir($remote_path, $recursive)
	{
		return $this->handle->mkdir($remote_path, $recursive);
	}

	public function _pwd()
	{
		return $this->handle->pwd();
	}

	public function _cd($remote_path)
	{
		return $this->handle->cd($remote_path);
	}

	public function _rename($remote_path_from, $remote_path_to)
	{
		return $this->handle->rename($remote_path_from, $remote_path_to);
	}

	public function _rm($remote_path, $recursive)
	{
		return $this->handle->rm($remote_path, $recursive);
	}

	public function _chmod($remote_path, $permissions, $recursive)
	{
		$permissions = decoct($permissions);
		
		if ($recursive) {
			return $this->handle->chmod($remote_path, $permissions);
		} else {
			return $this->handle->chmodRecursive($remote_path, $permissions);
		}
	}
}

?>
