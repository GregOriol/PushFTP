<?php

namespace PushFTP\Target;

class Factory
{
	public static function create(string $type, string $host, ?int $port_number = null): TargetInterface
	{
		switch($type) {
			case 'sftp':
				$target = new Implementation\Sftp($host, $port_number);
				break;
			default:
				throw new \Exception('Unknown target type: '.$type);
		}

		return $target;
	}
}
