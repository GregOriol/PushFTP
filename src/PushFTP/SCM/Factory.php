<?php

namespace PushFTP\SCM;

class Factory
{
	public static function create(string $root_path): SCMInterface
	{
		$type = Factory::detectSCMType($root_path);
		switch($type) {
			case 'git':
				$scm = new Implementation\Git($root_path);
				break;
			default:
				throw new \Exception('Unknown scm type: '.$type);
		}
		
		return $scm;
	}

	protected static function detectSCMType(string $root_path): string
	{
		if (Implementation\Git::detect($root_path)) {
			return 'git';
		}
		
		return 'not detected';
	}
}
