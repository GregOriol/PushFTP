<?php

namespace Pusher\SCM;

interface SCMInterface
{
	static public function detect($root_path);
	
	public function __construct($root_path);

	public function getInitialVersion();
	public function getCurrentVersion();

	public function getChanges($rev, $newrev);
	public function parseChanges($v);
	
	public function getDiff($rev, $newrev);
}

?>
