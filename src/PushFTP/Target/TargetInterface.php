<?php

namespace PushFTP\Target;

use phpseclib3\Crypt\Common\PrivateKey;

interface TargetInterface
{
	public function __construct(string $host, ?int $port = null);
	public function connect(): void;
	public function login(string $username, string|PrivateKey $password): bool;

	public function isError(mixed $response): bool;

	public function get(string $remote_path, string $local_path): bool;
	public function put(string $local_path, string $remote_path, bool $overwrite): bool;

	public function mkdir(string $remote_path, bool $recursive = false): bool;
	public function pwd(): string|false;
	public function cd(string $remote_path): bool;

	public function rename(string $remote_path_from, string $remote_path_to): bool;
	public function rm(string $remote_path, bool $recursive): bool;

	public function chmod(string $remote_path, int $permissions, bool $recursive = false): bool;
}
