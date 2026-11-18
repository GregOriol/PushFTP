<?php

namespace PushFTP;

use PushFTP\SCM\SCMInterface;
use PushFTP\Target\Implementation\Sftp;
use PushFTP\Target\TargetInterface;

/**
 * @phpstan-import-type Jump from Sftp
 * @phpstan-type Profile array{target: array{type: string, host: string, port: ?int, login: string, password: ?string, path: string, privatekey: ?string, local_path: ?string, jump: ?Jump}, excludes: list<string>, permissions: array<string, string>, cdn: array{flushlist: ?list<string>, pathreplace: array<string, string>}}
 * @phpstan-type Options array{path: string, profile: ?string, go: bool, lenient: bool, nfonc: bool, cdnflushlist: bool, key: ?string}
 * @phpstan-type Handler callable(string, string, string, string, string): void
 */
class PushFTP
{
	protected string $version = PUSHFTP_VERSION;

	protected string $path = '';
	protected string $profileName = '';
	protected bool $go = false;
	protected bool $lenient = false;
	protected bool $nfonc = false;
	protected bool $cdnflushlist = false;
	protected ?string $key = null;

	/**
	 * @var Profile
	 */
	protected array $profile;

	protected TargetInterface $target;
	protected SCMInterface $scm;

	protected string $lpath = '';
	protected string $lpathh = '';

	protected string $rrevfile = '';
	protected string $lrevfile = '';

	protected string $rev = '';
	protected string $newrev = '';
	
	/**
	 * @var list<array{status: string, file: string}>
	 */
	protected array $scm_changes = [];

	protected string $tmpDir = '_tmp';
	protected string $backupDir = 'backup';

	/**
	 * Operations done on the target, undone in reverse order on failure
	 * restore: moves the backup "from" back to "to", remove: deletes "to"
	 *
	 * @var list<array{action: string, from: string, to: string}>
	 */
	protected array $rollbackSteps = [];

	protected string $logfile 			= 'pushftp.log';
	protected string $scmchangesfile 	= 'pushftp.scm_changes.txt';
	protected string $scmdifffile 		= 'pushftp.scm_diff.txt';
	protected string $scmlogfile			= 'pushftp.scm_log.txt';
	protected string $flushlistfile 		= 'pushftp.flushlist.txt';

	/**
	 * 
	 */
	public function __construct() {
		// Cleaning up output files
		if (file_exists($this->logfile)) {
			unlink($this->logfile);
		}
		if (file_exists($this->scmchangesfile)) {
			unlink($this->scmchangesfile);
		}
		if (file_exists($this->scmdifffile)) {
			unlink($this->scmdifffile);
		}
		if (file_exists($this->scmlogfile)) {
			unlink($this->scmlogfile);
		}
		if (file_exists($this->flushlistfile)) {
			unlink($this->flushlistfile);
		}

		// Preparing log file
		file_put_contents($this->logfile, '');
		$this->e('New PushFTP v'.$this->version.' session '.date('Y-m-d H:i:s'));
	}

	public function run(): void {
		$this->parseConfigFile();
		$this->prepareTarget();

		$this->parseLocalRevision();
		$this->parseTargetRevision();

		$this->parseChanges();
		try {
			$this->pushChanges();
			$this->checkPermissions();

			if ($this->cdnflushlist) {
				$this->makeCdnFlushList();
			}

			$this->updateRemoteRevision();
		} catch (\Exception $e) {
			if ($e->getMessage() !== '') {
				$this->e($e->getMessage());
			}
			$this->rollbackChanges();
			throw new \Exception('', 1);
		}

		// Backups of replaced and deleted files are kept until everything succeeded
		$this->_cleanupTmpDir($this->_getTmpDirName($this->profile['target']['path']));
	}

	/**
	 * Setting options
	 *
	 * @param Options $options
	 * @return void
	 */
	public function setOptions(array $options): void {
		// Performing some checks
		if ($options['profile'] === null || $options['profile'] === '') {
			$this->e('No profile defined : a profile is mandatory to proceed');
			throw new \Exception('', 1);
		}

		$this->path = $options['path'];
		$this->profileName = $options['profile'];
		$this->go = $options['go'];
		$this->lenient = $options['lenient'];
		$this->nfonc = $options['nfonc'];
		$this->cdnflushlist = $options['cdnflushlist'];
		$this->key = $options['key'];

		if ($this->go !== true) {
			$this->e('/!\ DRY RUN /!\\');
		}

		if ($this->lenient === true) {
			$this->e('/!\ LENIENT MODE /!\\');
		}

		if ($this->key === null) {
			$this->e('No key set, will try to read plaintext passwords');
		} else {
			$this->e('Key set, will try to read encrypted passwords');
		}
	}

	/**
	 * Parsing configuration file
	 *
	 * @return void
	 */
	public function parseConfigFile(): void {
		$lpath = realpath($this->path);
		$this->lpathh = $this->path; // human readable local path
		if ($lpath === false) {
			$this->e('Invalid pushftp.json path provided '.$this->lpathh);
			throw new \Exception('', 1);
		}
		$this->lpath = $lpath; // real local path

		// Checking if config file exists
		if (is_file($this->lpath)) {
			$pushftpjson_path = $this->lpath;
			$this->lpath = dirname($this->lpath);
			$this->lpathh = dirname($this->lpathh);
		} else if (is_dir($this->lpath)) {
			$pushftpjson_path = $this->lpath.'/'.'pushftp.json';
		} else {
			$this->e('Invalid pushftp.json path provided '.$this->lpathh);
			throw new \Exception('', 1);
		}

		// Trying to read config file
		$pushftp = @file_get_contents($pushftpjson_path);
		if ($pushftp === false) {
			$this->e('Could not find pushftp.json file in '.$this->lpathh);
			throw new \Exception('', 1);
		}
		$config = json_decode($pushftp, true);
		if (!is_array($config)) {
			$this->e('Could not parse pushftp.json file in '.$this->lpathh);
			throw new \Exception('', 1);
		}
		if (!isset($config['profiles']) || !is_array($config['profiles']) || !isset($config['profiles'][$this->profileName])) {
			$this->e('No profile found matching '.$this->profileName);
			throw new \Exception('', 1);
		}

		$this->e('Using profile '.$this->profileName);

		// Saving values
		$this->profile = $this->_validateProfile($config['profiles'][$this->profileName]);

		// Updating local path
		if ($this->profile['target']['local_path'] !== null) {
			$this->e('Using a local path \''.$this->profile['target']['local_path'].'\' different from the pushftp base path');
			$this->lpath .= '/'.$this->profile['target']['local_path'];
			$this->lpathh .= '/'.$this->profile['target']['local_path'];
		}
	}

	/**
	 * Validating the profile configuration
	 *
	 * @param mixed $profile
	 * @return Profile
	 */
	protected function _validateProfile(mixed $profile): array {
		if (!is_array($profile) || empty($profile['target']) || !is_array($profile['target'])) {
			$this->_configError('Target configuration not found on the profile');
		}
		$target = $profile['target'];

		if (!isset($target['path']) || !is_string($target['path']) || $target['path'] === '') {
			$this->_configError('Target path must not be empty : use "." for root folder or specify a remote folder');
		}

		$jump = null;
		if (!empty($target['jump'])) {
			if (!is_array($target['jump'])) {
				$this->_configError('Invalid jump configuration in the profile');
			}
			$jump = [
				'host' => $this->_configString($target['jump'], 'host', 'jump host'),
				'port' => $this->_configOptionalInt($target['jump'], 'port', 'jump port'),
				'login' => $this->_configOptionalString($target['jump'], 'login', 'jump login'),
				'privatekey' => $this->_configOptionalString($target['jump'], 'privatekey', 'jump privatekey'),
				'password' => $this->_configOptionalString($target['jump'], 'password', 'jump password'),
				'known_hosts' => $this->_configOptionalString($target['jump'], 'known_hosts', 'jump known_hosts'),
			];
		}

		$cdn = $profile['cdn'] ?? [];
		if (!is_array($cdn)) {
			$this->_configError('Invalid cdn configuration in the profile');
		}

		return [
			'target' => [
				'type' => $this->_configString($target, 'type', 'target type'),
				'host' => $this->_configString($target, 'host', 'target host'),
				'port' => $this->_configOptionalInt($target, 'port', 'target port'),
				'login' => $this->_configString($target, 'login', 'target login'),
				'password' => $this->_configOptionalString($target, 'password', 'target password'),
				'path' => $target['path'],
				'privatekey' => $this->_configOptionalString($target, 'privatekey', 'target privatekey'),
				'local_path' => $this->_configOptionalString($target, 'local_path', 'target local_path'),
				'jump' => $jump,
			],
			'excludes' => $this->_configStringList($profile, 'excludes', 'excludes') ?? [],
			'permissions' => $this->_configStringMap($profile, 'permissions', 'permissions'),
			'cdn' => [
				'flushlist' => $this->_configStringList($cdn, 'flushlist', 'cdn flushlist'),
				'pathreplace' => $this->_configStringMap($cdn, 'pathreplace', 'cdn pathreplace'),
			],
		];
	}

	/**
	 * @param array<mixed> $config
	 * @param string $key
	 * @param string $name
	 * @return string
	 */
	protected function _configString(array $config, string $key, string $name): string {
		$value = $this->_configOptionalString($config, $key, $name);
		if ($value === null) {
			$this->_configError('Missing '.$name.' in the profile');
		}

		return $value;
	}

	/**
	 * @param array<mixed> $config
	 * @param string $key
	 * @param string $name
	 * @return string|null null if not set or empty
	 */
	protected function _configOptionalString(array $config, string $key, string $name): ?string {
		if (!isset($config[$key]) || $config[$key] === '') {
			return null;
		}
		if (!is_string($config[$key])) {
			$this->_configError('Invalid '.$name.' in the profile, a string is expected');
		}

		return $config[$key];
	}

	/**
	 * @param array<mixed> $config
	 * @param string $key
	 * @param string $name
	 * @return int|null null if not set or empty
	 */
	protected function _configOptionalInt(array $config, string $key, string $name): ?int {
		if (!isset($config[$key]) || $config[$key] === '') {
			return null;
		}
		if (is_int($config[$key])) {
			return $config[$key];
		}
		if (is_string($config[$key]) && ctype_digit($config[$key])) {
			return (int) $config[$key];
		}

		$this->_configError('Invalid '.$name.' in the profile, a number is expected');
	}

	/**
	 * @param array<mixed> $config
	 * @param string $key
	 * @param string $name
	 * @return list<string>|null null if not set
	 */
	protected function _configStringList(array $config, string $key, string $name): ?array {
		if (!isset($config[$key])) {
			return null;
		}

		$list = [];
		if (is_array($config[$key])) {
			foreach ($config[$key] as $value) {
				if (!is_string($value)) {
					$this->_configError('Invalid '.$name.' in the profile, a list of strings is expected');
				}
				$list[] = $value;
			}

			return $list;
		}

		$this->_configError('Invalid '.$name.' in the profile, a list of strings is expected');
	}

	/**
	 * @param array<mixed> $config
	 * @param string $key
	 * @param string $name
	 * @return array<string, string> empty if not set
	 */
	protected function _configStringMap(array $config, string $key, string $name): array {
		if (!isset($config[$key])) {
			return [];
		}

		$map = [];
		if (is_array($config[$key])) {
			foreach ($config[$key] as $mapKey => $value) {
				if (!is_string($value)) {
					$this->_configError('Invalid '.$name.' in the profile, string values are expected');
				}
				$map[(string) $mapKey] = $value;
			}

			return $map;
		}

		$this->_configError('Invalid '.$name.' in the profile, an object is expected');
	}

	/**
	 * Logs a configuration error and stops
	 *
	 * @param string $message
	 * @return never
	 */
	protected function _configError(string $message): never {
		$this->e($message);
		throw new \Exception('', 1);
	}

	/**
	 * Connecting to target
	 *
	 * @return void
	 */
	public function prepareTarget(): void {
		$this->rrevfile = $this->profile['target']['path'].'/'.'rev';
		$this->lrevfile = sys_get_temp_dir().'/pushftp-'.hash('sha256', $this->profileName.'-'.$this->profile['target']['host'].'-'.time()).'-rev';
		
		try {
			$this->target = \PushFTP\Target\Factory::create($this->profile['target']['type'], $this->profile['target']['host'], $this->profile['target']['port']);
		} catch (\Exception $e) {
			$this->e($e->getMessage());
			throw new \Exception('', 1);
		}

		if ($this->profile['target']['jump'] !== null) {
			$jump = $this->profile['target']['jump'];

			$target = $this->target;
			if (!$target instanceof Sftp) {
				$this->e('A jump host can only be used with an sftp target');
				throw new \Exception('', 1);
			}

			if (!empty($jump['privatekey'])) {
				$jump['privatekey'] = $this->lpath.'/'.$jump['privatekey'];
				if (!is_readable($jump['privatekey'])) {
					$this->e('Jump private key not found: '.$jump['privatekey']);
					throw new \Exception('', 1);
				}
			}
			// ssh would silently take a missing file as empty, and only report an unknown host key
			if (!empty($jump['known_hosts'])) {
				$jump['known_hosts'] = $this->lpath.'/'.$jump['known_hosts'];
				if (!is_readable($jump['known_hosts'])) {
					$this->e('Jump known_hosts not found: '.$jump['known_hosts']);
					throw new \Exception('', 1);
				}
			}
			if (!empty($jump['password']) && $this->key !== null) {
				$jump['password'] = $this->_decryptPassword($jump['password']);
			}

			$this->e('Using jump host '.(!empty($jump['login']) ? $jump['login'].'@' : '').$jump['host'].(!empty($jump['port']) ? ':'.$jump['port'] : ''));
			$target->setJump($jump);
		}

		$this->e('Connecting to target '.$this->profile['target']['type'].' '.$this->profile['target']['host'].':'.$this->profile['target']['port']);
		try {
			$this->target->connect();
		} catch (\Exception $e) {
			$this->e('Could not connect to target '.$this->profile['target']['type'].': '.$this->profile['target']['host'].':'.$this->profile['target']['port'].' ('.$e->getMessage().')');
			throw new \Exception('', 1);
		}

		$password = $this->profile['target']['password'] ?? '';
		if ($this->key !== null && $password !== '') {
			$password = $this->_decryptPassword($password);
		}

		if ($this->profile['target']['privatekey'] !== null) {
			$keyfile = $this->lpath.'/'.$this->profile['target']['privatekey'];
			$keydata = is_readable($keyfile) ? file_get_contents($keyfile) : false;
			if ($keydata === false) {
				$this->e('Could not read private key '.$keyfile);
				throw new \Exception('', 1);
			}

			try {
				$key = \phpseclib3\Crypt\PublicKeyLoader::load($keydata, $password);
			} catch (\Exception $e) {
				$this->e('Could not load private key: '.$e->getMessage());
				throw new \Exception('', 1);
			}
			if (!$key instanceof \phpseclib3\Crypt\Common\PrivateKey) {
				$this->e('Key '.$keyfile.' is not a private key');
				throw new \Exception('', 1);
			}
			$password = $key;
		}

		$this->e('Logging in as '.$this->profile['target']['login']);
		try {
			$r = $this->target->login($this->profile['target']['login'], $password);
		} catch (\Exception $e) {
			$this->e('Could not login on target with '.$this->profile['target']['login'].': '.$e->getMessage());
			throw new \Exception('', 1);
		}
		if ($this->target->isError($r)) {
			$this->e('Could not login on target with '.$this->profile['target']['login']);
			throw new \Exception('', 1);
		}
	}

	/**
	 * Parsing local revision
	 *
	 * @return void
	 **/
	public function parseLocalRevision(): void {
		$this->e('Getting local version');
		
		try {
			$this->scm = \PushFTP\SCM\Factory::create($this->lpath);
			$this->newrev = $this->scm->getCurrentVersion();
		} catch (\Exception $e) {
			$this->e($e->getMessage());
			throw new \Exception('', 1);
		}
	}

	/**
	 * Parsing remote revision
	 *
	 * @return void
	 **/
	public function parseTargetRevision(): void {
		$this->e('Getting target version');
		
		// Cleanup
		if (file_exists($this->lrevfile)) {
			unlink($this->lrevfile);
		}
		
		// Retrieving rev file from the target
		$r = $this->target->get($this->rrevfile, $this->lrevfile);
		if ($this->target->isError($r)) {
			// No rev file found, asking to use initial commit
			$initial_commit = $this->scm->getInitialVersion();
			$this->e('No rev file found on the target. Use initial commit '.$initial_commit.' as reference ? [Y/n]');

			$r = readline();
			if ($r === false) {
				throw new \Exception('', 1);
			} else {
				if ($r == '' || $r == 'Y') {
					$this->rev = $initial_commit;
				} else {
					$this->e('No. Stopping');
					throw new \Exception('', 1);
				}
			}
		} else {
			// Rev file found, getting the revision
			$revdata = file_get_contents($this->lrevfile);
			$this->rev = trim(($revdata === false) ? '' : $revdata);
			
			// Cleanup
			unlink($this->lrevfile);
		}

		// Checking the value
		if (strlen($this->rev) == 0) {
			$this->e('Target revision is empty');
			throw new \Exception('', 1);
		} elseif (strpos($this->rev, '@') === false) {
			$this->e('Target revision "'.$this->rev.'" doesn\'t match the expected format path@rev');
			throw new \Exception('', 1);
		}
	}

	/**
	 * Getting SCM changes
	 *
	 * @return void
	 **/
	public function parseChanges(): void {
		$this->e('Getting SCM changes between '.$this->rev.' and '.$this->newrev);
		
		// Getting changes from SCM
		$output = $this->scm->getChanges($this->rev, $this->newrev);
		if ($output === false) {
			$this->e('Could not get SCM changes between '.$this->rev.' and '.$this->newrev);
			throw new \Exception('', 1);
		}

		// Parsing changes
		$this->scm_changes = array_map([$this->scm, 'parseChanges'], $output);

		// Dumping changes list
		file_put_contents($this->scmchangesfile, '');
		foreach ($this->scm_changes as $change) {
			file_put_contents($this->scmchangesfile, implode("\t", $change)."\n", FILE_APPEND);
		}

		// Dumping diff
		$cwd = getcwd();
		$this->scm->dumpDiff($this->rev, $this->newrev, $cwd.'/'.$this->scmdifffile);

		// Dumping log
		$this->scm->dumpLog($this->rev, $this->newrev, $cwd.'/'.$this->scmlogfile);

		// Checking changes
		if (empty($this->scm_changes)) {
			$this->e('No changes found on SCM between target version '.$this->rev.' and local version '.$this->newrev);
			if ($this->nfonc === true) {
				throw new \Exception('', 0);
			} else {
				throw new \Exception('', 1);
			}
		}
		else {
			$this->e('Found '.count($this->scm_changes).' changes on SCM between target version '.$this->rev.' and local version '.$this->newrev);
		}
	}

	/**
	 * Pushing changes
	 *
	 * @return void
	 **/
	public function pushChanges(): void {
		$this->_prepareChanges();
		$this->_applyChanges();
	}

	/**
	 * Rolling back changes
	 *
	 * @return void
	 */
	public function rollbackChanges(): void {
		$this->e('Rolling back changes');

		$rpath = $this->profile['target']['path'];
		$rtmppath = $this->_getTmpDirName($rpath);

		$failed = false;
		foreach (array_reverse($this->rollbackSteps) as $step) {
			if ($step['action'] == 'restore') {
				$this->e('Restoring '.$step['to']);
				$r = $this->target->rename($step['from'], $step['to']);
			} else {
				$this->e('Removing '.$step['to']);
				$r = $this->target->rm($step['to'], true);
			}

			// Continuing, to restore as much as possible
			if ($this->target->isError($r)) {
				$this->e('Could not perform operation (!)');
				$failed = true;
			}
		}
		$this->rollbackSteps = [];

		if ($failed) {
			$this->e('Rollback incomplete: the target is partially updated and its rev is still '.$this->rev.', run the push again with --lenient to complete it');
		}

		$this->_cleanupTmpDir($rtmppath);
	}

	/**
	 * Moving an existing target file to the backup directory, so it can be restored on rollback
	 * note: must be public because it is used through $self
	 *
	 * @param string $rtmppath
	 * @param string $file
	 * @param string $rfile
	 * @return bool false if the file could not be moved, most likely because it doesn't exist
	 */
	public function _backupFile(string $rtmppath, string $file, string $rfile): bool {
		$rbackupfile = $rtmppath.'/'.$this->backupDir.'/'.hash('sha256', $file);

		$r = $this->target->rename($rfile, $rbackupfile);
		if ($this->target->isError($r)) {
			return false;
		}

		$this->e('Backed up '.$rfile.' to '.$rbackupfile);
		$this->_addRollbackStep('restore', $rbackupfile, $rfile);

		return true;
	}

	/**
	 * Highest missing directory of a directory about to be created recursively, so that rollback removes all created levels
	 * note: must be public because it is used through $self
	 *
	 * @param string $rpath target root, never returned
	 * @param string $rdir missing directory
	 * @return string
	 */
	public function _getTopMissingDirectory(string $rpath, string $rdir): string {
		$rtopdir = $rdir;
		$rparent = dirname($rtopdir);
		while (strlen($rparent) > strlen($rpath) && !$this->_directoryExists($rparent)) {
			$rtopdir = $rparent;
			$rparent = dirname($rtopdir);
		}

		return $rtopdir;
	}

	/**
	 * Adding a step to undo on rollback
	 * note: must be public because it is used through $self
	 *
	 * @param string $action restore or remove
	 * @param string $from
	 * @param string $to
	 * @return void
	 */
	public function _addRollbackStep(string $action, string $from, string $to): void {
		$this->rollbackSteps[] = [
			'action' => $action,
			'from' => $from,
			'to' => $to,
		];
	}
	
	/**
	 * Pushing changes to a temporary folder
	 *
	 * @return void
	 */
	protected function _prepareChanges(): void {
		$this->e('Preparing changes on the target');
		
		$rpath = $this->profile['target']['path'];
		$rpath = $this->_getTmpDirName($rpath);
		$this->_makeTmpDir($rpath);
		
		$self = $this;
		$this->_processChanges($rpath, [
			'M' => function(string $rpath, string $file, string $lfile, string $lfileh, string $rfile) use ($self): void {
				$rtmpfile = $self->_getTmpFileName($rpath, $file);
				$self->e('Preparing '.$lfileh.' at '.$rtmpfile);
				if ($self->go === true) {
					$r = $self->target->put($lfile, $rtmpfile, true);
					if ($self->target->isError($r)) {
						$self->e('Could not perform operation, stopping.');
						throw new \Exception('', 1);
					}
				}
			},
			'A' => function(string $rpath, string $file, string $lfile, string $lfileh, string $rfile) use ($self): void {
				// Directories are created when applying changes
				if (!is_dir($lfile)) {
					$rtmpfile = $self->_getTmpFileName($rpath, $file);
					$self->e('Preparing '.$lfileh.' at '.$rtmpfile);
					if ($self->go === true) {
						$r = $self->target->put($lfile, $rtmpfile, ($self->lenient) ? true : false);
						if ($self->target->isError($r)) {
							$self->e('Could not perform operation, stopping.');
							throw new \Exception('', 1);
						}
					}
				}
			},
			'D' => function(string $rpath, string $file, string $lfile, string $lfileh, string $rfile): void {
				// Nothing to do, deleted files are backed up when applying changes
			}
		]);
	}

	/**
	 * Applying changes
	 *
	 * @return void
	 */
	protected function _applyChanges(): void {
		$this->e('Applying changes on the target');
		
		$rpath = $this->profile['target']['path'];
		$rtmppath = $this->_getTmpDirName($rpath);
		
		$self = $this;
		$this->_processChanges($rpath, [
			'M' => function(string $rpath, string $file, string $lfile, string $lfileh, string $rfile) use ($rtmppath, $self): void {
				// TODO: check if directory exists ?
				
				$self->e('Commiting '.$lfileh.' to '.$rfile);
				if ($self->go === true) {
					$backedUp = $self->_backupFile($rtmppath, $file, $rfile);
					$r = $self->target->rename($self->_getTmpFileName($rtmppath, $file), $rfile);
					if ($self->target->isError($r)) {
						$self->e('Could not perform operation, stopping.');
						throw new \Exception('', 1);
					}
					if (!$backedUp) {
						$self->_addRollbackStep('remove', '', $rfile);
					}
				}
			},
			'A' => function(string $rpath, string $file, string $lfile, string $lfileh, string $rfile) use ($rtmppath, $self): void {
				if (is_dir($lfile)) {
					$r = $self->_directoryExists($rfile);
					if (!$r) {
						$self->e('Creating real directory '.$rfile);
						if ($self->go === true) {
							$rtopdir = $self->_getTopMissingDirectory($rpath, $rfile);
							$r = $self->target->mkdir($rfile, true);
							if ($self->target->isError($r)) {
								$self->e('Could not perform operation, stopping.');
								throw new \Exception('', 1);
							}
							$self->_addRollbackStep('remove', '', $rtopdir);
						}
					}
				}
				else {
					$dir = dirname($file);
					$r = $self->_directoryExists($rpath.'/'.$dir);
					if (!$r) {
						$self->e('Creating real directory '.$dir.' for new file');
						if ($self->go === true) {
							$rtopdir = $self->_getTopMissingDirectory($rpath, $rpath.'/'.$dir);
							$r = $self->target->mkdir($rpath.'/'.$dir, true);
							if ($self->target->isError($r)) {
								$self->e('Could not perform operation, stopping.');
								throw new \Exception('', 1);
							}
							$self->_addRollbackStep('remove', '', $rtopdir);
						}
					}
			
					$self->e('Commiting '.$lfileh.' to '.$rfile);
					if ($self->go === true) {
						// Only backed up if the file already exists on the target
						$backedUp = $self->_backupFile($rtmppath, $file, $rfile);
						$r = $self->target->rename($self->_getTmpFileName($rtmppath, $file), $rfile);
						if ($self->target->isError($r)) {
							$self->e('Could not perform operation, stopping.');
							throw new \Exception('', 1);
						}
						if (!$backedUp) {
							$self->_addRollbackStep('remove', '', $rfile);
						}
					}
				}
			},
			'D' => function(string $rpath, string $file, string $lfile, string $lfileh, string $rfile) use ($rtmppath, $self): void {
				$self->e('Deleting '.$rfile);
				if ($self->go === true) {
					// Moved to the backup directory instead of deleted, so it can be restored on rollback
					$r = $self->_backupFile($rtmppath, $file, $rfile);
					if (!$r) {
						if ($self->lenient) {
							$self->e('Could not perform operation, continuing (lenient mode).');
						} else {
							$self->e('Could not perform operation, stopping.');
							throw new \Exception('', 1);
						}
					}
				}
			}
		]);
	}

	/**
	 * Generic changes processor
	 *
	 * @param string $rpath 
	 * @param array<string, Handler> $handlers handlers for each type of change
	 * @return void
	 */
	protected function _processChanges(string $rpath, array $handlers = []): void {
		foreach ($this->scm_changes as $value) {
			$file = $value['file'];
			$lfile = $this->lpath.'/'.$file;
			$lfileh = $this->lpathh.'/'.$file;
			$rfile = $rpath.'/'.$file;

			// Check if the file should be excluded
			$shouldSkip = false;
			foreach ($this->profile['excludes'] as $exclude) {
				if (fnmatch($exclude, $file)) {
					$shouldSkip = true;
				}
			}
			if ($shouldSkip) {
				$this->e('Skipping excluded file '.$lfileh);
				continue;
			}

			// Processing
			if ($value['status'] == 'M') {
				if (isset($handlers['M'])) {
					$handlers['M']($rpath, $file, $lfile, $lfileh, $rfile);
				}
			} elseif ($value['status'] == 'A') {
				if (isset($handlers['A'])) {
					$handlers['A']($rpath, $file, $lfile, $lfileh, $rfile);
				}
			} elseif ($value['status'] == 'D') {
				if (isset($handlers['D'])) {
					$handlers['D']($rpath, $file, $lfile, $lfileh, $rfile);
				}
			} else {
				$this->e('Unknown SCM status '.$value['status'].' for file '.$value['file']);
			}
		}
	}

	/**
	 * Remote temporary directory name
	 *
	 * @param string $rpath 
	 * @return string
	 */
	protected function _getTmpDirName(string $rpath): string {
		$rpath .= '/'.$this->tmpDir;

		return $rpath;
	}

	/**
	 * Remote temporary file name, flat in the temporary directory
	 * note: must be public because it is used through $self
	 *
	 * @param string $rtmppath
	 * @param string $file
	 * @return string
	 */
	public function _getTmpFileName(string $rtmppath, string $file): string {
		return $rtmppath.'/'.hash('sha256', $file);
	}

	/**
	 * Creating temporary directory
	 *
	 * @param string $rtmppath 
	 * @return void
	 */
	protected function _makeTmpDir(string $rtmppath): void {
		if ($this->go === true) {
			$this->e('Preparing temporary directory '.$rtmppath);
			$pwd = $this->target->pwd();
			
			$r = $this->target->cd($rtmppath);
			if (!$this->target->isError($r)) {
				// If cd was successful, going back to where we were
				if ($pwd !== false) {
					$this->target->cd($pwd);
				}
				
				// Deleting tmp dir
				$this->e('Found an existing temporary directory on the remote server, deleting it');
				$r = $this->target->rm($rtmppath.'/', true);
				if ($this->target->isError($r)) {
					$this->e('Could not perform operation, stopping.');
					throw new \Exception('', 1);
				}
			}

			$r = $this->target->mkdir($rtmppath.'/'.$this->backupDir, true);
			$this->e('Creating temporary directory');
			if ($this->target->isError($r)) {
				$this->e('Could not perform operation, stopping.');
				throw new \Exception('', 1);
			}
		}
	}

	/**
	 * Removing temporary directory
	 *
	 * @param string $rtmppath 
	 * @return void
	 */
	protected function _cleanupTmpDir(string $rtmppath): void {
		if ($this->go === true) {
			$this->e('Cleaning up temporary directory '.$rtmppath);
			
			$r = $this->target->rm($rtmppath.'/', true);
			if ($this->target->isError($r)) {
				$this->e('Could not perform operation (!)');
			}
		}
	}

	/**
	 * Checking if remote directory exists
	 * note: must be public because it is used through $self
	 *
	 * @param string $rdir 
	 * @return bool
	 */
	public function _directoryExists(string $rdir): bool {
		$directoryExists = false;
		
		// Checking if directory exists by trying to cd into it
		$pwd = $this->target->pwd();
		$r = $this->target->cd($rdir);
		if ($this->target->isError($r)) {
			$directoryExists = false;
		} else {
			$directoryExists = true;
			
			// If cd was successful, going back to where we were
			if ($pwd !== false) {
				$this->target->cd($pwd);
			}
		}

		return $directoryExists;
	}

	/**
	 * Checking file permissions on the remote server
	 *
	 * @return void
	 */
	public function checkPermissions(): void {
		$this->e('Checking permissions');
		
		if (empty($this->profile['permissions'])) {
			$this->e('No permissions associated to this profile');
			return;
		}
		
		$rpath = $this->profile['target']['path'];
		
		$self = $this;
		$this->_processChanges($rpath, [
			'M' => function(string $rpath, string $file, string $lfile, string $lfileh, string $rfile) use ($self): void {
				$permissions = $self->_checkPermissions($file, $lfile, $lfileh);
				if ($permissions !== false) {
					$self->_updatePermissions($self, $rpath, $file, $lfile, $lfileh, $rfile, $permissions);
				}
			},
			'A' => function(string $rpath, string $file, string $lfile, string $lfileh, string $rfile) use ($self): void {
				$permissions = $self->_checkPermissions($file, $lfile, $lfileh);
				if ($permissions !== false) {
					$self->_updatePermissions($self, $rpath, $file, $lfile, $lfileh, $rfile, $permissions);
				}
			}
		]);
	}

	/**
	 * Checking if the file matches one of the permission rules
	 * note: must be public because it is used through $self
	 *
	 * @param string $file 
	 * @param string $lfile 
	 * @param string $lfileh 
	 * @return string|false new permissions to apply, or false
	 */
	public function _checkPermissions(string $file, string $lfile, string $lfileh): string|false {		
		foreach ($this->profile['permissions'] as $pattern => $permissions) {
			$r = fnmatch($pattern, $file);
			if ($r) {
				$_permissions = explode('-', $permissions);
				if (count($_permissions) == 2) {
					if (is_dir($lfile)) {
						$permission = $_permissions[0];
					}
					else {
						$permission = $_permissions[1];
					}
				}
				else {
					$permission = $permissions;
				}
				
				if (!preg_match('/[0-9]{4}/', $permission)) {
					$this->e('Found new permission '.$permission.' to apply to file '.$lfileh.' but value is not a valid permission, skipping it');
					continue;
				}
				
				return $permission;
			}
		}
		
		return false;
	}

	/**
	 * Updating permissions on a file/folder
	 * note: must be public because it is used through $self
	 *
	 * @param PushFTP $self 
	 * @param string $rpath 
	 * @param string $file 
	 * @param string $lfile 
	 * @param string $lfileh 
	 * @param string $rfile 
	 * @param string $permissions new permissions to apply
	 * @return void
	 */
	public function _updatePermissions(PushFTP $self, string $rpath, string $file, string $lfile, string $lfileh, string $rfile, string $permissions): void {
		if (is_dir($lfile)) {
			$self->e('Updating permissions on directory '.$rfile.' to '.$permissions);
			if ($self->go === true) {
				$r = $self->target->chmod($rfile, (int) octdec($permissions));
				if ($self->target->isError($r)) {
					$self->e('Could not perform operation, stopping.');
					throw new \Exception('', 1);
				}
			}
		}
		else {
			$self->e('Updating permissions on file '.$rfile.' to '.$permissions);
			if ($self->go === true) {
				$r = $self->target->chmod($rfile, (int) octdec($permissions));
				if ($self->target->isError($r)) {
					$self->e('Could not perform operation, stopping.');
					throw new \Exception('', 1);
				}
			}
		}
	}

	/**
	 * Making the CDN flush list from changes
	 *
	 * @return void
	 */
	public function makeCdnFlushList(): void {
		$this->e('Making CDN flush list');
		
		if ($this->profile['cdn']['flushlist'] === null) {
			$this->e('Couldn\'t make CDN flush list since no rules have been defined for this profile');
			return;
		}
		
		$rpath = $this->profile['target']['path'];
		$flushlist = [];
		
		$self = $this;
		$this->_processChanges($rpath, [
			'M' => function(string $rpath, string $file, string $lfile, string $lfileh, string $rfile) use (&$flushlist, $self): void {
				if ($self->_shouldCdnFlush($file)) {
					$flushlist[] = $file;
				}
			},
			'A' => function(string $rpath, string $file, string $lfile, string $lfileh, string $rfile) use (&$flushlist, $self): void {
				if (is_dir($lfile)) {
					// do nothing
				}
				else {
					if ($self->_shouldCdnFlush($file)) {
						$flushlist[] = $file;
					}
				}
			},
			'D' => function(string $rpath, string $file, string $lfile, string $lfileh, string $rfile) use (&$flushlist, $self): void {
				// NB: no specific check on directories since all files inside will be listed as 'D' by SCM
				if ($self->_shouldCdnFlush($file)) {
					$flushlist[] = $file;
				}
			}
		]);

		file_put_contents($this->flushlistfile, '');
		foreach ($flushlist as $flushitem) {
			foreach ($this->profile['cdn']['pathreplace'] as $search => $replace) {
				$flushitem = str_replace($search, $replace, $flushitem);
			}
			$this->e($flushitem);
			file_put_contents($this->flushlistfile, $flushitem."\n", FILE_APPEND);
		}

		$this->e('Dumped CDN flush list to '.$this->flushlistfile);
	}

	/**
	 * Checking if the file matches the CDN flush rules
	 * note: must be public because it is used through $self
	 *
	 * @param string $file 
	 * @return bool
	 */
	public function _shouldCdnFlush(string $file): bool {
		if ($this->profile['cdn']['flushlist'] === null) {
			return false;
		}
		
		$shouldFlush = false;
		foreach ($this->profile['cdn']['flushlist'] as $cdnflush) {
			$r = fnmatch($cdnflush, $file);
			if ($r) {
				$shouldFlush = true;
				break;
			}
		}

		return $shouldFlush;
	}

	/**
	 * Updating remote revision
	 *
	 * @return void
	 **/
	public function updateRemoteRevision(): void {
		if ($this->go === true) {
			$this->e('Updating target rev');
			file_put_contents($this->lrevfile, $this->newrev);
			$r = $this->target->put($this->lrevfile, $this->rrevfile, true);
			if ($this->target->isError($r)) {
				$this->e('Could not update target rev.');
				throw new \Exception('', 1);
			}
			unlink($this->lrevfile);
		}
	}

	/**
	 * Decrypts password
	 *
	 * @param string $encryptedPassword
	 * @return string
	 **/
	protected function _decryptPassword(string $encryptedPassword): string {
		if ($this->key === null) {
			return $encryptedPassword;
		}

		try {
			return \PushFTP\Crypt::decrypt($encryptedPassword, $this->key);
		} catch (\Exception $e) {
			$this->e('Could not decrypt password: '.$e->getMessage());
			throw new \Exception('', 1);
		}
	}

	/**
	 * Echo helper
	 *
	 * Prints string and logs to file if requested
	 *
	 * @param string $str 
	 * @return void
	 */
	public function e(string $str): void {
		echo $str.PHP_EOL;
		
		file_put_contents($this->logfile, $str."\n", FILE_APPEND);
	}
}
