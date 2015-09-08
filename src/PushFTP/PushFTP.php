<?php

namespace PushFTP;

class PushFTP
{
	var $version = PUSHFTP_VERSION;

	var $path = null;
	var $profileName = null;
	var $log = null;
	var $go = null;
	var $lenient = null;
	var $nfonc = null;
	var $cdnflushlist = null;
	var $key = null;

	var $config = null;
	var $profile = null;

	var $target;
	var $scm;

	var $lpath = null;
	var $lpathh = null;

	var $rrevfile;
	var $lrevfile;

	var $rev;
	var $newrev;
	
	var $scm_changes;

	var $tmpDir = '_tmp';

	var $logfile 			= 'pushftp.log';
	var $scmchangesfile 	= 'pushftp.scm_changes.txt';
	var $scmdifffile 		= 'pushftp.scm_diff.txt';
	var $scmlogfile			= 'pushftp.scm_log.txt';
	var $flushlistfile 		= 'pushftp.flushlist.txt';

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

	public function run() {
		$this->parseCommandLine();

		$this->parseConfigFile();
		$this->prepareTarget();

		$this->parseLocalRevision();
		$this->parseTargetRevision();

		$this->parseChanges();
		try {
			$this->pushChanges();
		} catch (Exception $e) {
			$this->rollbackChanges();
			throw new \Exception('', 1);
		}

		$this->checkPermissions();

		if ($this->cdnflushlist) {
			$this->makeCdnFlushList();
		}

		$this->updateRemoteRevision();
	}

	/**
	 * Parsing command line
	 *
	 * @return void
	 */
	public function parseCommandLine() {
		// Setting up command line parser
		$parser = new \Console_CommandLine();
		$parser->description = 'Push SCM changes to a target.';
		$parser->version = $this->version;
		$parser->addOption('profile', array(
			'long_name'			=> '--profile',
			'description'		=> "profile to use from pushftp.json file",
			'action'			=> 'StoreString'
		));
		$parser->addOption('go', array(
			'long_name'			=> '--go',
			'description'		=> "real, otherwise dry run",
			'action'			=> 'StoreTrue'
		));
		$parser->addOption('lenient', array(
			'long_name'			=> '--lenient',
			'description'		=> "lenient mode, doesn't stop on files already existing/deleted when adding or deleting",
			'action'			=> 'StoreTrue'
		));
		$parser->addOption('nfonc', array(
			'long_name'			=> '--nfonc',
			'description'		=> "no failure on no changes : exits with OK status if no changes found (useful with scripting or CI)",
			'action'			=> 'StoreTrue'
		));
		$parser->addOption('cdnflushlist', array(
			'long_name'			=> '--cdnflushlist',
			'description'		=> "make CDN flush list",
			'action'			=> 'StoreTrue'
		));
		$parser->addOption('key', array(
			'long_name'			=> '--key',
			'description'		=> "key to decrypt AES encrypted passwords",
			'action'			=> 'StoreString'
		));
		$parser->addArgument('path', array(
			'multiple'		=> false,
			'description'	=> "local path where pushftp.json is located, or pushftp.json path"
		));

		// Parsing command line
		try {
			$cli = $parser->parse();
			// print_r($cli->options);
			// print_r($cli->args);
		} catch (\Exception $exc) {
			$parser->displayError($exc->getMessage());
			throw new \Exception("Error parsing command line", 1, $exc);
		}

		// Saving values
		$this->path = $cli->args['path'];
		$this->profileName = $cli->options['profile'];
		$this->go = $cli->options['go'];
		$this->lenient = $cli->options['lenient'];
		$this->nfonc = $cli->options['nfonc'];
		$this->cdnflushlist = $cli->options['cdnflushlist'];
		$this->key = $cli->options['key'];

		// Performing some checks
		if ($this->profileName === NULL) {
			$this->e('No profile defined : a profile is mandatory to proceed');
			throw new \Exception('', 1);
		}

		if ($this->go !== true) {
			$this->e('/!\ DRY RUN /!\\');
		}

		if ($this->lenient !== true) {
			$this->e('/!\ LENIENT MODE /!\\');
		}

		if ($this->key === NULL) {
			$this->e('No key set, will try to read plaintext passwords');
		} else {
			$this->e('Key set, will try to read AES encrypted passwords');
		}
	}

	/**
	 * Parsing configuration file
	 *
	 * @return void
	 */
	public function parseConfigFile() {
		$this->lpath = realpath($this->path); // real local path
		$this->lpathh = $this->path; // human readable local path

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
		if ($config === NULL) {
			$this->e('Could not parse pushftp.json file in '.$this->lpathh);
			throw new \Exception('', 1);
		}
		if (!isset($config['profiles']) || !isset($config['profiles'][$this->profileName])) {
			$this->e('No profile found matching '.$this->profileName);
			throw new \Exception('', 1);
		}

		$this->e('Using profile '.$this->profileName);

		// Saving values
		$this->config = $config;
		$this->profile = $config['profiles'][$this->profileName];

		// Updating local path
		if (isset($this->profile['target']['local_path']) && !empty($this->profile['target']['local_path'])) {
			$this->e('Using a local path \''.$this->profile['target']['local_path'].'\' different from the pushftp base path');
			$this->lpath .= '/'.$this->profile['target']['local_path'];
			$this->lpathh .= '/'.$this->profile['target']['local_path'];
		}
	}

	/**
	 * Connecting to target
	 *
	 * @return void
	 */
	public function prepareTarget() {
		if (!isset($this->profile['target']) || empty($this->profile['target'])) {
			$this->e('Target configuration not found on the profile');
			throw new \Exception('', 1);
		}
		
		if (empty($this->profile['target']['path'])) {
			$this->e('Target path must not be empty : use "." for root folder or specify a remote folder');
			throw new \Exception('', 1);
		}
		
		$this->rrevfile = $this->profile['target']['path'].'/'.'rev';
		$this->lrevfile = '/tmp/pushftp-'.sha1($this->profileName.'-'.$this->profile['target']['host'].'-'.time()).'-rev';
		
		try {
			$this->target = \PushFTP\Target\Factory::create($this->profile['target']['type'], $this->profile['target']['host'], $this->profile['target']['port']);
		} catch (\Exception $e) {
			$this->e($e->getMessage());
			throw new \Exception('', 1);
		}
		
		$this->e('Connecting to target '.$this->profile['target']['type'].' '.$this->profile['target']['host'].':'.$this->profile['target']['port']);
		$r = $this->target->connect();
		if ($this->target->isError($r)) {
			$this->e('Could not connect to target '.$this->profile['target']['type'].': '.$this->profile['target']['host'].':'.$this->profile['target']['port']);
			throw new \Exception('', 1);
		}
		
		$password = $this->profile['target']['password'];
		if ($this->key !== null) {
			$password = $this->_decryptPassword($this->profile['target']['password']);
		}
		
		if (!empty($this->profile['target']['rsakey'])) {
			$key = new \phpseclib\Crypt\RSA();
			$key->setPassword($password);
			$key->loadKey(file_get_contents($this->lpath.'/'.$this->profile['target']['rsakey']));
			$password = $key;
		}

		$this->e('Logging in as '.$this->profile['target']['login']);
		$r = $this->target->login($this->profile['target']['login'], $password);
		if ($this->target->isError($r)) {
			$this->e('Could not login on target with '.$this->profile['target']['login'].':'.$this->profile['target']['password']);
			throw new \Exception('', 1);
		}

		if (!empty($this->profile['target']['mode']) && $this->profile['target']['mode'] == 'passive') {
			$this->e('Setting passive mode');
			$this->target->setPassive();
		}
	}

	/**
	 * Parsing local revision
	 *
	 * @return void
	 **/
	public function parseLocalRevision() {
		$this->e('Getting local version');
		
		try {
			$this->scm = \PushFTP\SCM\Factory::create($this->lpath);
			$this->newrev = $this->scm->getCurrentVersion();
		} catch (\Exception $e) {
			$this->e($e->getMessage());
			throw new \Exception('', 1);
		}
		
		// TODO: is this really useful ?
		if (!empty($this->scm->repo_root)) {
			$this->e('Using SCM root '.$this->scm->repo_root.' (note: local and FTP must have been pulled from the same repository !)');
		}
	}

	/**
	 * Parsing remote revision
	 *
	 * @return void
	 **/
	public function parseTargetRevision() {
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
			$revdata = trim(file_get_contents($this->lrevfile));
			$this->rev = trim($revdata);
			
			// Cleanup
			unlink($this->lrevfile);
		}

		// Parsing revision and checking the value
		$r = strpos($this->rev, '@');
		if (strlen($this->rev) == 0) {
			$this->e('Target revision is empty');
			throw new \Exception('', 1);
		} elseif ($r === false) {
			$this->e('Target revision "'.$this->rev.'" doesn\'t match the expected format path@rev');
			throw new \Exception('', 1);
		} else {
			$this->repo_rpath = substr($this->rev, 0, $r);
		}
	}

	/**
	 * Getting SCM changes
	 *
	 * @return void
	 **/
	public function parseChanges() {
		$this->e('Getting SCM changes between '.$this->rev.' and '.$this->newrev);
		
		// Getting changes from SCM
		$output = $this->scm->getChanges($this->rev, $this->newrev);
		if ($output === false) {
			$this->e('Could not get SCM changes between '.$this->rev.' and '.$this->newrev);
			throw new \Exception('', 1);
		}

		// Parsing changes
		$this->scm->repo_rpath = $this->repo_rpath;
		$this->scm_changes = array_map(array($this->scm, 'parseChanges'), $output);

		// Dumping changes list
		file_put_contents($this->scmchangesfile, '');
		foreach ($this->scm_changes as $change) {
			file_put_contents($this->scmchangesfile, implode("\t", $change)."\n", FILE_APPEND);
		}

		// Dumping diff
		$this->scm->dumpDiff($this->rev, $this->newrev, getcwd().'/'.$this->scmdifffile);

		// Dumping log
		$this->scm->dumpLog($this->rev, $this->newrev, getcwd().'/'.$this->scmlogfile);

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
	public function pushChanges() {
		$this->_prepareChanges();
		$this->_applyChanges();
	}

	/**
	 * Rolling back changes
	 *
	 * @return void
	 */
	public function rollbackChanges() {
		$this->e('Rolling back changes');
		
		// TODO: implement rollback
		$this->e('Rollback not implemented');
		
		$rpath = $this->profile['target']['path'];
		$rtmppath = $this->_getTmpDirName($rpath);
		$this->_cleanupTmpDir($rtmppath);
	}
	
	/**
	 * Pushing changes to a temporary folder
	 *
	 * @return void
	 */
	protected function _prepareChanges() {
		$this->e('Preparing changes on the target');
		
		$rpath = $this->profile['target']['path'];
		$rpath = $this->_getTmpDirName($rpath);
		$this->_makeTmpDir($rpath);
		
		$self = $this;
		$this->_processChanges($rpath, array(
			'M' => function($rpath, $file, $lfile, $lfileh, $rfile) use ($self) {
				$dir = dirname($file);
				$r = $self->_directoryExists($rpath.'/'.$dir);
				if (!$r) {
					$self->e('Creating tmp directory '.$dir.' for file');
					if ($self->go === true) {
						$r = $self->target->mkdir($rpath.'/'.$dir, true);
						if ($self->target->isError($r)) {
							$self->e('Could not perform operation, stopping.');
							throw new \Exception('', 1);
						}
					}
				}
				
				$self->e('Preparing '.$lfileh.' at '.$rfile);
				if ($self->go === true) {
					$r = $self->target->put($lfile, $rfile, true);
					if ($self->target->isError($r)) {
						$self->e('Could not perform operation, stopping.');
						throw new \Exception('', 1);
					}
				}
				
				// TODO: copy file that will be replace to a tmp dir in case of rollback
			},
			'A' => function($rpath, $file, $lfile, $lfileh, $rfile) use ($self) {
				if (is_dir($lfile)) {
					$r = $self->_directoryExists($rfile);
					if (!$r) {
						$self->e('Creating tmp directory '.$rfile);
						if ($self->go === true) {
							$r = $self->target->mkdir($rfile, true);
							if ($self->target->isError($r)) {
								$self->e('Could not perform operation, stopping.');
								throw new \Exception('', 1);
							}
						}
					}
				}
				else {
					$dir = dirname($file);
					$r = $self->_directoryExists($rpath.'/'.$dir);
					if (!$r) {
						$self->e('Creating tmp directory '.$dir.' for new file');
						if ($self->go === true) {
							$r = $self->target->mkdir($rpath.'/'.$dir, true);
							if ($self->target->isError($r)) {
								$self->e('Could not perform operation, stopping.');
								throw new \Exception('', 1);
							}
						}
					}
			
					$self->e('Preparing '.$lfileh.' at '.$rfile);
					if ($self->go === true) {
						$r = $self->target->put($lfile, $rfile, ($self->lenient) ? true : false);
						if ($self->target->isError($r)) {
							$self->e('Could not perform operation, stopping.');
							throw new \Exception('', 1);
						}
					}
				}
			},
			'D' => function($rpath, $file, $lfile, $lfileh, $rfile) use ($self) {
				// Nothing to do
				
				// TODO: copy file that will be deleted to a tmp dir in case of rollback
			}
		));
	}

	/**
	 * Applying changes
	 *
	 * @return void
	 */
	protected function _applyChanges() {
		$this->e('Applying changes on the target');
		
		$rpath = $this->profile['target']['path'];
		$rtmppath = $this->_getTmpDirName($rpath);
		
		$self = $this;
		$this->_processChanges($rpath, array(
			'M' => function($rpath, $file, $lfile, $lfileh, $rfile) use ($rtmppath, $self) {
				// TODO: check if directory exists ?
				
				$self->e('Commiting '.$lfileh.' to '.$rfile);
				if ($self->go === true) {
					$r = $self->target->rename($rtmppath.'/'.$file, $rfile);
					if ($self->target->isError($r)) {
						$self->e('Could not perform operation, stopping.');
						// TODO: rollback ?
						throw new \Exception('', 1);
					}
				}
			},
			'A' => function($rpath, $file, $lfile, $lfileh, $rfile) use ($rtmppath, $self) {
				if (is_dir($lfile)) {
					$r = $self->_directoryExists($rfile);
					if (!$r) {
						$self->e('Creating real directory '.$rfile);
						if ($self->go === true) {
							$r = $self->target->mkdir($rfile, true);
							if ($self->target->isError($r)) {
								$self->e('Could not perform operation, stopping.');
								throw new \Exception('', 1);
							}
						}
					}
				}
				else {
					$dir = dirname($file);
					$r = $self->_directoryExists($rpath.'/'.$dir);
					if (!$r) {
						$self->e('Creating real directory '.$dir.' for new file');
						if ($self->go === true) {
							$r = $self->target->mkdir($rpath.'/'.$dir, true);
							if ($self->target->isError($r)) {
								$self->e('Could not perform operation, stopping.');
								throw new \Exception('', 1);
							}
						}
					}
			
					$self->e('Commiting '.$lfileh.' to '.$rfile);
					if ($self->go === true) {
						$r = $self->target->rename($rtmppath.'/'.$file, $rfile);
						if ($self->target->isError($r)) {
							$self->e('Could not perform operation, stopping.');
							throw new \Exception('', 1);
						}
					}
				}
			},
			'D' => function($rpath, $file, $lfile, $lfileh, $rfile) use ($rtmppath, $self) {
				$self->e('Deleting '.$rfile);
				if ($self->go === true) {
					$r = $self->target->rm($rfile, true);
					if ($self->target->isError($r)) {
						if ($self->lenient) {
							$self->e('Could not perform operation, continuing (lenient mode).');
						} else {
							$self->e('Could not perform operation, stopping.');
							throw new \Exception('', 1);
						}
					}
				}
			}
		));
		
		//
		
		$this->_cleanupTmpDir($rtmppath);
	}

	/**
	 * Generic changes processor
	 *
	 * @param string $rpath 
	 * @param array $handlers array containing handlers for each type of change
	 * @return void
	 */
	protected function _processChanges($rpath, $handlers = array()) {
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
	 * @return void
	 */
	protected function _getTmpDirName($rpath) {
		$rpath .= '/'.$this->tmpDir;

		return $rpath;
	}

	/**
	 * Creating temporary directory
	 *
	 * @param string $rtmppath 
	 * @return void
	 */
	protected function _makeTmpDir($rtmppath) {
		if ($this->go === true) {
			$this->e('Preparing temporary directory '.$rtmppath);
			$pwd = $this->target->pwd();
			
			$r = $this->target->cd($rtmppath);
			if (!$this->target->isError($r)) {
				// If cd was successful, going back to where we were
				$r = $this->target->cd($pwd);
				
				// Deleting tmp dir
				$this->e('Found an existing temporary directory on the remote server, deleting it');
				$r = $this->target->rm($rtmppath.'/', true);
				if ($this->target->isError($r)) {
					$this->e('Could not perform operation, stopping.');
					throw new \Exception('', 1);
				}
			}

			$r = $this->target->mkdir($rtmppath, true);
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
	protected function _cleanupTmpDir($rtmppath) {
		if ($this->go === true) {
			$this->e('Cleaning up temporary directory '.$rtmppath);
			
			if ($this->go === true) {
				$r = $this->target->rm($rtmppath.'/', true);
				if ($this->target->isError($r)) {
					$this->e('Could not perform operation (!)');
				}
			}
		}
	}

	/**
	 * Checking if remote directory exists
	 * note: must be public because it is used through $self
	 *
	 * @param string $rdir 
	 * @return void
	 */
	public function _directoryExists($rdir) {
		$directoryExists = false;
		
		// Checking if directory exists by trying to cd into it
		$pwd = $this->target->pwd();
		$r = $this->target->cd($rdir);
		if ($this->target->isError($r)) {
			$directoryExists = false;
		} else {
			$directoryExists = true;
			
			// If cd was successful, going back to where we were
			$r = $this->target->cd($pwd);
		}

		return $directoryExists;
	}

	/**
	 * Checking file permissions on the remote server
	 *
	 * @return void
	 */
	public function checkPermissions() {
		$this->e('Checking permissions');
		
		if (!isset($this->profile['permissions']) || empty($this->profile['permissions'])) {
			$this->e('No permissions associated to this profile');
			return;
		}
		
		$rpath = $this->profile['target']['path'];
		
		$self = $this;
		$this->_processChanges($rpath, array(
			'M' => function($rpath, $file, $lfile, $lfileh, $rfile) use (&$flushlist, $self) {
				$permissions = $self->_checkPermissions($file, $lfile, $lfileh);
				if ($permissions !== false) {
					$self->_updatePermissions($self, $rpath, $file, $lfile, $lfileh, $rfile, $permissions);
				}
			},
			'A' => function($rpath, $file, $lfile, $lfileh, $rfile) use (&$flushlist, $self) {
				$permissions = $self->_checkPermissions($file, $lfile, $lfileh);
				if ($permissions !== false) {
					$self->_updatePermissions($self, $rpath, $file, $lfile, $lfileh, $rfile, $permissions);
				}
			}
		));
	}

	/**
	 * Checking if the file matches one of the permission rules
	 * note: must be public because it is used through $self
	 *
	 * @param string $file 
	 * @return int new permissions to apply, of false
	 */
	public function _checkPermissions($file, $lfile, $lfileh) {
		if (!isset($this->profile['permissions']) || empty($this->profile['permissions'])) {
			return false;
		}
		
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
	 * @param string $file 
	 * @param int new permissions to apply, of false
	 * @return void
	 */
	public function _updatePermissions($self, $rpath, $file, $lfile, $lfileh, $rfile, $permissions) {
		if (is_dir($lfile)) {
			$self->e('Updating permissions on directory '.$rfile.' to '.$permissions);
			if ($self->go === true) {
				$r = $self->target->chmod($rfile, octdec($permissions));
				if ($self->target->isError($r)) {
					$self->e('Could not perform operation, stopping.');
					throw new \Exception('', 1);
				}
			}
		}
		else {
			$self->e('Updating permissions on file '.$rfile.' to '.$permissions);
			if ($self->go === true) {
				$r = $self->target->chmod($rfile, octdec($permissions));
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
	public function makeCdnFlushList() {
		$this->e('Making CDN flush list');
		
		if (!isset($this->profile['cdn']['flushlist'])) {
			$this->e('Couldn\'t make CDN flush list since no rules have been defined for this profile');
			return;
		}
		
		$rpath = $this->profile['target']['path'];
		$flushlist = array();
		
		$self = $this;
		$this->_processChanges($rpath, array(
			'M' => function($rpath, $file, $lfile, $lfileh, $rfile) use (&$flushlist, $self) {
				if ($self->_shouldCdnFlush($file)) {
					$flushlist[] = $file;
				}
			},
			'A' => function($rpath, $file, $lfile, $lfileh, $rfile) use (&$flushlist, $self) {
				if (is_dir($lfile)) {
					// do nothing
				}
				else {
					if ($self->_shouldCdnFlush($file)) {
						$flushlist[] = $file;
					}
				}
			},
			'D' => function($rpath, $file, $lfile, $lfileh, $rfile) use (&$flushlist, $self) {
				// NB: no specific check on directories since all files inside will be listed as 'D' by SCM
				if ($self->_shouldCdnFlush($file)) {
					$flushlist[] = $file;
				}
			}
		));

		file_put_contents($this->flushlistfile, '');
		foreach ($flushlist as $flushitem) {
			if (isset($this->profile['cdn']['pathreplace'])) {
				foreach ($this->profile['cdn']['pathreplace'] as $search => $replace) {
					$flushitem = str_replace($search, $replace, $flushitem);
				}
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
	 * @return boolean
	 */
	public function _shouldCdnFlush($file) {
		if (!isset($this->profile['cdn']['flushlist'])) {
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
	public function updateRemoteRevision() {
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
	 * Decrypts password using the AES algorithm
	 *
	 * @return string
	 **/
	protected function _decryptPassword($encryptedPassword) {
		$encrypter = new \phpseclib\Crypt\AES();
		$encrypter->setKey($this->key);

		$password = $encrypter->decrypt(base64_decode($encryptedPassword));

		return $password;
	}

	/**
	 * Echo helper
	 *
	 * Prints string and logs to file if requested
	 *
	 * @param string $str 
	 * @return void
	 */
	public function e($str) {
		echo $str.PHP_EOL;
		
		file_put_contents($this->logfile, $str."\n", FILE_APPEND);
	}
}
