<?php

namespace Pusher\Connector;

class Factory
{
	public static function create($connection_type, $host, $port_number = false)
	{
		switch($connection_type) {
			case 'ftp':
				$ftp = new Implementation\Ftp($host, $port_number);
				break;
			case 'sftp':
				$ftp = new Implementation\Sftp($host, $port_number);
				break;
			default:
				throw new \Exception('No connection type set cannot choose a method to connect');
		}

		return $ftp;
	}
}

?>
