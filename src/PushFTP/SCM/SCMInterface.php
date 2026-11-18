<?php

namespace PushFTP\SCM;

interface SCMInterface
{
	static public function detect(string $root_path): bool;
	
	public function __construct(string $root_path);

	public function getInitialVersion(): string;
	public function getCurrentVersion(): string;

	/**
	 * @return list<string>|false
	 */
	public function getChanges(string $rev, string $newrev): array|false;

	/**
	 * @return array{status: string, file: string}
	 */
	public function parseChanges(string $v): array;
	
	public function dumpDiff(string $rev, string $newrev, string $difffile): bool;
	public function dumpLog(string $rev, string $newrev, string $logfile): bool;
}
