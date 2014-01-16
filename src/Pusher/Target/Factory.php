<?php

namespace Pusher\Target;

class Factory
{
	public static function create($type, $host, $port_number = false)
	{
		switch($type) {
			case 'ftp':
				$target = new Implementation\Ftp($host, $port_number);
				break;
			case 'sftp':
				$target = new Implementation\Sftp($host, $port_number);
				break;
			default:
				throw new \Exception('No connection type set cannot choose a method to connect');
		}

		return $target;
	}
}

?>
