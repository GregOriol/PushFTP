<?php

namespace Pusher\SCM;

class Factory
{
	public static function create($root_path)
	{
		$type = Factory::detectSCMType($root_path);
		switch($type) {
			case 'svn':
				$scm = new Implementation\SVN($root_path);
				break;
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
		if (file_exists($root_path.'/.svn')) {
			return 'svn';
		}
		else if (file_exists($root_path.'/.git')) {
			return 'git';
		}
		
		return 'not detected';
	}
}

?>
