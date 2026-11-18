<?php

// Doc : http://phpseclib.sourceforge.net/sftp/intro.html

namespace PushFTP\Target\Implementation;

class Sftp extends \PushFTP\Target\AbstractTarget
{
	protected $jump = null;
	protected $jumpProcess = null;
	protected $jumpAskpass = null;

	protected function ___construct($host, $port = false)
	{
		if (!$port) {
			$port = 22;
		}

		$this->host = $host;
		$this->port = $port;
	}

	public function __destruct()
	{
		if ($this->handle) {
			$this->handle->disconnect();
		}

		// proc_close() waits for ssh, which could hang if the jump connection is stuck
		if ($this->jumpProcess) {
			proc_terminate($this->jumpProcess);
			proc_close($this->jumpProcess);
		}

		if ($this->jumpAskpass) {
			unlink($this->jumpAskpass);
		}
	}

	/**
	 * Connects through a jump host (like ssh's ProxyJump)
	 *
	 * @param array $jump host, and optional port, login, privatekey (path), password (privatekey's passphrase), known_hosts (path)
	 * @return void
	 */
	public function setJump($jump)
	{
		$this->jump = $jump;
	}

	protected function _connect()
	{
		$host = $this->host;
		if ($this->jump !== null) {
			$host = $this->_openJump();
		}

		$this->handle = new \phpseclib3\Net\SFTP($host, $this->port, 30);
	}

	/**
	 * Opens a tunnel to the target through the jump host with the system ssh (ssh -W): phpseclib talks to the target
	 * over it, so the target's authentication stays end-to-end
	 *
	 * @return resource
	 */
	protected function _openJump()
	{
		$pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
		if ($pair === false) {
			throw new \Exception('Could not create a socket pair for the jump');
		}

		// No BatchMode: it would also prevent the passphrase from being read through askpass
		$command = array(
			'ssh',
			'-W', $this->host.':'.$this->port,
			'-o', 'StrictHostKeyChecking=yes',
			'-o', 'PasswordAuthentication=no',
			'-o', 'KbdInteractiveAuthentication=no',
			'-o', 'ConnectTimeout=30',
			'-o', 'ServerAliveInterval=30',
		);
		if (!empty($this->jump['port'])) {
			array_push($command, '-p', (string) $this->jump['port']);
		}
		if (!empty($this->jump['login'])) {
			array_push($command, '-l', $this->jump['login']);
		}
		if (!empty($this->jump['privatekey'])) {
			array_push($command, '-i', $this->jump['privatekey'], '-o', 'IdentitiesOnly=yes');
		}
		if (!empty($this->jump['known_hosts'])) {
			array_push($command, '-o', 'UserKnownHostsFile='.$this->jump['known_hosts']);
		}
		$command[] = $this->jump['host'];

		// The passphrase only goes through ssh's environment, the askpass script holds no secret
		$env = null;
		if (!empty($this->jump['password'])) {
			$this->jumpAskpass = tempnam(sys_get_temp_dir(), 'pushftp-askpass-');
			file_put_contents($this->jumpAskpass, "#!/bin/sh\nprintf '%s\\n' \"\$PUSHFTP_JUMP_PASSPHRASE\"\n");
			chmod($this->jumpAskpass, 0700);

			$env = getenv();
			$env['SSH_ASKPASS'] = $this->jumpAskpass;
			$env['SSH_ASKPASS_REQUIRE'] = 'force';
			$env['PUSHFTP_JUMP_PASSPHRASE'] = $this->jump['password'];
		}

		// The same socket is ssh's stdin and stdout, so the other end is a single bidirectional stream, as phpseclib expects
		$process = proc_open($command, array(0 => $pair[1], 1 => $pair[1], 2 => STDERR), $pipes, null, $env);
		fclose($pair[1]);

		if ($process === false) {
			fclose($pair[0]);
			throw new \Exception('Could not start ssh for the jump');
		}

		$this->jumpProcess = $process;

		return $pair[0];
	}

	protected function _login($username, $password)
	{
		return $this->handle->login($username, $password);
	}

	protected function _isError($response)
	{
		return ($response === false);
	}

	protected function _get($remote_path, $local_path)
	{
		return $this->handle->get($remote_path, $local_path);
	}

	protected function _put($local_path, $remote_path, $overwrite)
	{
		// $overwrite is not used as files are Net_SFTP doesn't fail on existing files and always overwrites
		return $this->handle->put($remote_path, $local_path, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE, -1);
	}

	protected function _mkdir($remote_path, $recursive)
	{
		return $this->handle->mkdir($remote_path, -1, $recursive);
	}

	protected function _pwd()
	{
		return $this->handle->pwd();
	}

	protected function _cd($remote_path)
	{
		return $this->handle->chdir($remote_path);
	}

	protected function _rename($remote_path_from, $remote_path_to)
	{
		// first trying to delete the target, since Net_SFTP doesn't seem to perform overwriting
		$this->handle->delete($remote_path_to, true);
		return $this->handle->rename($remote_path_from, $remote_path_to);
	}

	protected function _rm($remote_path, $recursive)
	{
		return $this->handle->delete($remote_path, $recursive);
	}

	protected function _chmod($remote_path, $permissions, $recursive)
	{
		return $this->handle->chmod($permissions, $remote_path, $recursive);
	}
}
