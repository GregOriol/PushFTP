<?php

namespace PushFTP\SCM;

interface SCMInterface
{
	static public function detect($root_path);
	
	public function __construct($root_path);

	public function getInitialVersion();
	public function getCurrentVersion();

	public function getChanges($rev, $newrev);
	public function parseChanges($v);
	
	public function dumpDiff($rev, $newrev, $difffile);
	public function dumpLog($rev, $newrev, $logfile);
}
