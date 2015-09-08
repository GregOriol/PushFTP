<?php

namespace PushFTP\Target;

interface TargetInterface
{
	public function __construct($host, $port = false);
	public function connect();
	public function login($username, $password);

	public function isError($response);

	public function setPassive();

	public function get($remote_path, $local_path);
	public function put($local_path, $remote_path, $overwrite);

	public function mkdir($remote_path, $recursive = false);
	public function pwd();
	public function cd($remote_path);

	public function rename($remote_path_from, $remote_path_to);
	public function rm($remote_path, $recursive);

	public function chmod($remote_path, $permissions, $recursive = false);
}
