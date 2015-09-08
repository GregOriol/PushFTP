<?php

namespace PushFTP\Target;

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
				throw new \Exception('Unknown target type: '.$type);
		}

		return $target;
	}
}
