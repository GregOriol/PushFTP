<?php

namespace Pusher\SCM;

interface SCMInterface
{
	public function __construct($root_path);

	public function getInitialVersion();
	public function getCurrentVersion();

	public function getChanges($rev, $newrev);
	public function parseChanges($v);
}

?>
