<?php

// Doc : http://phpseclib.sourceforge.net/sftp/intro.html

namespace PushFTP\Target\Implementation;

use phpseclib3\Crypt\Common\PrivateKey;

/**
 * @phpstan-type Jump array{host: string, port: ?int, login: ?string, privatekey: ?string, password: ?string, known_hosts: ?string}
 */
class Sftp extends \PushFTP\Target\AbstractTarget
{
	protected ?\phpseclib3\Net\SFTP $handle = null;

	/**
	 * @var Jump|null
	 */
	protected ?array $jump = null;

	/**
	 * @var resource|null
	 */
	protected mixed $jumpProcess = null;
	protected ?string $jumpAskpass = null;
	protected ?string $jumpPrivateKey = null;

	protected function ___construct(string $host, ?int $port = null): void
	{
		$this->host = $host;
		$this->port = $port ?? 22;
	}

	public function __destruct()
	{
		if ($this->handle !== null) {
			$this->handle->disconnect();
		}

		// proc_close() waits for ssh, which could hang if the jump connection is stuck
		if ($this->jumpProcess !== null) {
			proc_terminate($this->jumpProcess);
			proc_close($this->jumpProcess);
		}

		if ($this->jumpAskpass !== null) {
			unlink($this->jumpAskpass);
		}

		if ($this->jumpPrivateKey !== null) {
			unlink($this->jumpPrivateKey);
		}
	}

	/**
	 * Connects through a jump host (like ssh's ProxyJump)
	 *
	 * @param Jump $jump privatekey and known_hosts are paths, password is privatekey's passphrase
	 * @return void
	 */
	public function setJump(array $jump): void
	{
		$this->jump = $jump;
	}

	protected function _connect(): void
	{
		$host = $this->host;
		if ($this->jump !== null) {
			$host = $this->_openJump($this->jump);
		}

		$this->handle = new \phpseclib3\Net\SFTP($host, $this->port, 30);

		if ($this->jump !== null) {
			$this->_checkJump($this->jump);
		}
	}

	/**
	 * Forces the handshake, as phpseclib connects lazily and a failed tunnel would only show up at login as a broken pipe
	 *
	 * @param Jump $jump
	 * @return void
	 */
	protected function _checkJump(array $jump): void
	{
		try {
			// @: the broken pipe notice is reported below
			@$this->getHandle()->getServerIdentification();
		} catch (\Exception $e) {
			$exit = '';
			if ($this->jumpProcess !== null) {
				$status = proc_get_status($this->jumpProcess);
				if (!$status['running']) {
					$exit = ', ssh exited with code '.$status['exitcode'];
				}
			}

			throw new \Exception('Could not open the tunnel through jump host '.$jump['host'].$exit.': check that the target host/port is right and reachable from the jump host');
		}
	}

	/**
	 * @return \phpseclib3\Net\SFTP
	 */
	protected function getHandle(): \phpseclib3\Net\SFTP
	{
		if ($this->handle === null) {
			throw new \Exception('Not connected');
		}

		return $this->handle;
	}

	/**
	 * Opens a tunnel to the target through the jump host with the system ssh (ssh -W): phpseclib talks to the target
	 * over it, so the target's authentication stays end-to-end
	 *
	 * @param Jump $jump
	 * @return resource
	 */
	protected function _openJump(array $jump)
	{
		$pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
		if ($pair === false) {
			throw new \Exception('Could not create a socket pair for the jump');
		}

		// No BatchMode: it would also prevent the passphrase from being read through askpass
		$command = [
			'ssh',
			'-W', $this->host.':'.$this->port,
			'-o', 'StrictHostKeyChecking=yes',
			'-o', 'PasswordAuthentication=no',
			'-o', 'KbdInteractiveAuthentication=no',
			'-o', 'ConnectTimeout=30',
			'-o', 'ServerAliveInterval=30',
		];
		if (!empty($jump['port'])) {
			array_push($command, '-p', (string) $jump['port']);
		}
		if (!empty($jump['login'])) {
			array_push($command, '-l', $jump['login']);
		}
		// ssh refuses a key readable by others, which a checkout usually creates (git doesn't keep permissions):
		// tempnam() creates the copy as 0600
		if (!empty($jump['privatekey'])) {
			$this->jumpPrivateKey = $this->_tempFile('pushftp-key-');
			copy($jump['privatekey'], $this->jumpPrivateKey);
			array_push($command, '-i', $this->jumpPrivateKey, '-o', 'IdentitiesOnly=yes');
		}
		if (!empty($jump['known_hosts'])) {
			array_push($command, '-o', 'UserKnownHostsFile='.$jump['known_hosts']);
		}
		$command[] = $jump['host'];

		// The passphrase only goes through ssh's environment, the askpass script holds no secret
		$env = null;
		if (!empty($jump['password'])) {
			$this->jumpAskpass = $this->_tempFile('pushftp-askpass-');
			file_put_contents($this->jumpAskpass, "#!/bin/sh\nprintf '%s\\n' \"\$PUSHFTP_JUMP_PASSPHRASE\"\n");
			chmod($this->jumpAskpass, 0700);

			$env = getenv();
			$env['SSH_ASKPASS'] = $this->jumpAskpass;
			$env['SSH_ASKPASS_REQUIRE'] = 'force';
			$env['PUSHFTP_JUMP_PASSPHRASE'] = $jump['password'];
		}

		// The same socket is ssh's stdin and stdout, so the other end is a single bidirectional stream, as phpseclib expects
		$process = proc_open($command, [0 => $pair[1], 1 => $pair[1], 2 => STDERR], $pipes, null, $env);
		fclose($pair[1]);

		if ($process === false) {
			fclose($pair[0]);
			throw new \Exception('Could not start ssh for the jump');
		}

		$this->jumpProcess = $process;

		return $pair[0];
	}

	/**
	 * Creates a temporary file, only readable by the current user
	 *
	 * @param string $prefix
	 * @return string
	 */
	protected function _tempFile(string $prefix): string
	{
		$file = tempnam(sys_get_temp_dir(), $prefix);
		if ($file === false) {
			throw new \Exception('Could not create a temporary file');
		}

		return $file;
	}

	protected function _login(string $username, string|PrivateKey $password): bool
	{
		return $this->getHandle()->login($username, $password);
	}

	protected function _isError(mixed $response): bool
	{
		return ($response === false);
	}

	protected function _get(string $remote_path, string $local_path): bool
	{
		return $this->getHandle()->get($remote_path, $local_path) !== false;
	}

	protected function _put(string $local_path, string $remote_path, bool $overwrite): bool
	{
		// $overwrite is not used as files are Net_SFTP doesn't fail on existing files and always overwrites
		return $this->getHandle()->put($remote_path, $local_path, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE, -1);
	}

	protected function _mkdir(string $remote_path, bool $recursive): bool
	{
		return $this->getHandle()->mkdir($remote_path, -1, $recursive);
	}

	protected function _pwd(): string|false
	{
		$pwd = $this->getHandle()->pwd();

		return is_string($pwd) ? $pwd : false;
	}

	protected function _cd(string $remote_path): bool
	{
		return $this->getHandle()->chdir($remote_path);
	}

	protected function _rename(string $remote_path_from, string $remote_path_to): bool
	{
		// first trying to delete the target, since Net_SFTP doesn't seem to perform overwriting
		$this->getHandle()->delete($remote_path_to, true);
		return $this->getHandle()->rename($remote_path_from, $remote_path_to);
	}

	protected function _rm(string $remote_path, bool $recursive): bool
	{
		return $this->getHandle()->delete($remote_path, $recursive);
	}

	protected function _chmod(string $remote_path, int $permissions, bool $recursive): bool
	{
		return $this->getHandle()->chmod($permissions, $remote_path, $recursive) !== false;
	}
}
