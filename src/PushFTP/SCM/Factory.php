<?php

namespace PushFTP\SCM;

class Factory
{
	public static function create($root_path)
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

	protected static function detectSCMType($root_path)
	{
		if (Implementation\Git::detect($root_path)) {
			return 'git';
		}
		
		return 'not detected';
	}
}
