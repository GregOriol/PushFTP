<?php

namespace Pusher;

class Pusher
{
	var $version = '0.5.0';

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

	var $ftp;

	var $lpath = null;
	var $lpathh = null;

	var $rrevfile;
	var $lrevfile;

	var $repo_root;
	var $repo_url;
	var $repo_lpath;
	var $repo_rpath;

	var $rev;
	var $newrev;
	
	var $svn_changes;

	var $tmpDir = '_tmp';

	var $logfile = 'pushftp.log';
	var $svnchangesfile = 'pushftp.svn_changes.txt';
	var $flushlistfile = 'pushftp.flushlist.txt';
	

	/**
	 * Parsing command line
	 *
	 * @return void
	 */
	function parseCommandLine() {
		// Setting up command line parser
		$parser = new \Console_CommandLine();
		$parser->description = 'Push from SVN to FTP.';
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
			'description'		=> "no failure on no changes : exits with OK status if no changes found",
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
			)
		);

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

		// Preparing log file
		file_put_contents($this->logfile, '');

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
	function parseConfigFile() {
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
	}

	/**
	 * Connecting to FTP
	 *
	 * @return void
	 */
	function prepareConnection() {
		if (empty($this->profile['ftp']['path'])) {
			$this->e('FTP path must not be empty : use "." for root folder or specify a remote folder');
			throw new \Exception('', 1);
		}
		
		// TODO: refactor this
		$this->rrevfile = $this->profile['ftp']['path'].'/'.'rev';
		$this->lrevfile = '/tmp/'.$this->profile['ftp']['host'].'-rev';

		$this->ftp  = \Pusher\Target\Factory::create($this->profile['ftp']['type'], $this->profile['ftp']['host'], $this->profile['ftp']['port']);
		$this->e('Connecting to FTP '.$this->profile['ftp']['host'].':'.$this->profile['ftp']['port']);
		$r = $this->ftp->connect();
		if ($this->ftp->isError($r)) {
			$this->e('Could not connect to FTP '.$this->profile['ftp']['host'].':'.$this->profile['ftp']['port']);
			throw new \Exception('', 1);
		}

		$password = $this->profile['ftp']['password'];
		if ($this->key !== null) {
			$password = $this->_decryptPassword($this->profile['ftp']['password']);
		}

		$this->e('Logging in as '.$this->profile['ftp']['login']);
		$r = $this->ftp->login($this->profile['ftp']['login'], $password);
		if ($this->ftp->isError($r)) {
			$this->e('Could not login to FTP with '.$this->profile['ftp']['login'].':'.$this->profile['ftp']['password']);
			throw new \Exception('', 1);
		}
		if ($this->profile['ftp']['type'] == 'ftp') {
			$this->e('Setting passive mode');
			$this->ftp->setPassive();
		}
	}

	/**
	 * Parsing local revision
	 *
	 * @return void
	 **/
	function parseLocalRevision() {
		$this->e('Getting LOCAL version');
		$this->newrev = exec('cd '.$this->lpath.' && svnversion');

		if (!is_numeric($this->newrev)) {
			$this->e('Local SVN revision error, value "'.$this->newrev.'" is not a valid revision');
			if (strpos($this->newrev, ':') !== false) {
				$this->e('Local SVN revision has multiple states, try `svn update` to get a uniform state');
			} elseif (strpos($this->newrev, 'M') !== false) {
				$this->e('Local SVN revision has local modifications, try commiting them or use svn diff/patch');
			}
			throw new \Exception('', 1);
		}

		$this->repo_root = exec('cd '.$this->lpath.' && svn info | grep \'Repository Root\' | awk \'{print $NF}\'');
		$this->repo_url = exec('cd '.$this->lpath.' && svn info | grep \'URL\' | awk \'{print $NF}\'');
		$this->repo_lpath = str_replace($this->repo_root.'/', '', $this->repo_url);
		$this->newrev = $this->repo_lpath.'@'.$this->newrev;
	}

	/**
	 * Parsing remote revision
	 *
	 * @return void
	 **/
	function parseRemoteRevision() {
		$this->e('Getting FTP version');
		$r = $this->ftp->get($this->rrevfile, $this->lrevfile);
		if ($this->ftp->isError($r)) {
			$this->e('No rev file found on the FTP. Use trunk rev 1 as reference ? [Y/n]');

			$r = readline();
			if ($r === false) {
				throw new \Exception('', 1);
			} else {
				if ($r == '' || $r == 'Y') {
					$this->rev = 'trunk@1';
				} else {
					$this->e('No. Stopping');
					throw new \Exception('', 1);
				}
			}
		} else {
			$this->rev = file_get_contents($this->lrevfile);
			unlink($this->lrevfile);
		}

		$r = strpos($this->rev, '@');
		if ($r === false) {
			$this->e('FTP revision '.$this->rev.' doesn\'t match the expected format path@rev');
			throw new \Exception('', 1);
		} else {
			$this->repo_rpath = substr($this->rev, 0, $r);
		}
	}

	/**
	 * Getting SVN changes
	 *
	 * @return void
	 **/
	function getChanges() {
		// Getting changes from SVN
		exec('cd '.$this->lpath.' && svn diff --summarize '.$this->repo_root.'/'.$this->rev.' '.$this->repo_root.'/'.$this->newrev.'', $output, $return_var);
		if ($return_var != 0) {
			$this->e('SVN diff error : '.print_r($output, true));
			throw new \Exception('', 1);
		}

		// Dumping changes list to a log file
		file_put_contents($this->svnchangesfile, '');
		foreach ($output as $row) {
			file_put_contents($this->svnchangesfile, $row."\n", FILE_APPEND);
		}

		// Parsing changes
		$pusherHelper = new PusherHelper();
		$pusherHelper->repo_root = $this->repo_root;
		$pusherHelper->repo_rpath = $this->repo_rpath;
		$this->svn_changes = array_map(array($pusherHelper, 'svn_changes_parse'), $output);
		if (empty($this->svn_changes)) {
			$this->e('No changes found on SVN between FTP version '.$this->rev.' and LOCAL version '.$this->newrev);
			if ($this->nfonc === true) {
				throw new \Exception('', 0);
			} else {
				throw new \Exception('', 1);
			}
		}
		else {
			$this->e('Found '.count($this->svn_changes).' changes on SVN between FTP version '.$this->rev.' and LOCAL version '.$this->newrev);
		}
	}

	/**
	 * Pushing changes
	 *
	 * @return void
	 **/
	function pushChanges() {
		$this->_prepareChanges();
		$this->_commitChanges();
	}

	/**
	 * Rolling back changes
	 *
	 * @return void
	 * @author Grégory ORIOL
	 */
	function rollbackChanges() {
		$this->e("Rolling back changes");
		
		// TODO: implement rollback
		$this->e("Rollback not implemented !!!");
		
		$rpath = $this->profile['ftp']['path'];
		$rtmppath = $this->_getTmpDirName($rpath);
		$this->_cleanupTmpDir($rtmppath);
	}
	
	/**
	 * Pushing changes to a temporary folder
	 *
	 * @return void
	 */
	function _prepareChanges() {
		$this->e('Preparing files on the FTP');
		
		$rpath = $this->profile['ftp']['path'];
		$rpath = $this->_getTmpDirName($rpath);
		$this->_makeTmpDir($rpath);
		
		$self = $this; // for php 5.3, could be remove when will be using php 5.4
		$this->_processChanges($rpath, array(
			'M' => function($rpath, $file, $lfile, $lfileh, $rfile) use ($self) {
				$dir = dirname($file);
				$r = $self->_directoryExists($rpath.'/'.$dir);
				if (!$r) {
					$self->e('Creating tmp directory '.$dir.' for file');
					if ($self->go === true) {
						$r = $self->ftp->mkdir($rpath.'/'.$dir, true);
						if ($self->ftp->isError($r)) {
							$self->e('Could not perform operation, stopping.');
							throw new \Exception('', 1);
						}
					}
				}
				
				$self->e('Preparing '.$lfileh.' at '.$rfile);
				if ($self->go === true) {
					$r = $self->ftp->put($lfile, $rfile, true);
					if ($self->ftp->isError($r)) {
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
							$r = $self->ftp->mkdir($rfile, true);
							if ($self->ftp->isError($r)) {
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
							$r = $self->ftp->mkdir($rpath.'/'.$dir, true);
							if ($self->ftp->isError($r)) {
								$self->e('Could not perform operation, stopping.');
								throw new \Exception('', 1);
							}
						}
					}
			
					$self->e('Preparing '.$lfileh.' at '.$rfile);
					if ($self->go === true) {
						$r = $self->ftp->put($lfile, $rfile, ($self->lenient) ? true : false);
						if ($self->ftp->isError($r)) {
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
	function _commitChanges() {
		$this->e('Commiting files on the FTP');
		
		$rpath = $this->profile['ftp']['path'];
		$rtmppath = $this->_getTmpDirName($rpath);
		
		$self = $this; // for php 5.3, could be remove when will be using php 5.4
		$this->_processChanges($rpath, array(
			'M' => function($rpath, $file, $lfile, $lfileh, $rfile) use ($rtmppath, $self) {
				// TODO: check if directory exists ?
				
				$self->e('Commiting '.$lfileh.' to '.$rfile);
				if ($self->go === true) {
					$r = $self->ftp->rename($rtmppath.'/'.$file, $rfile);
					if ($self->ftp->isError($r)) {
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
							$r = $self->ftp->mkdir($rfile, true);
							if ($self->ftp->isError($r)) {
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
							$r = $self->ftp->mkdir($rpath.'/'.$dir, true);
							if ($self->ftp->isError($r)) {
								$self->e('Could not perform operation, stopping.');
								throw new \Exception('', 1);
							}
						}
					}
			
					$self->e('Commiting '.$lfileh.' to '.$rfile);
					if ($self->go === true) {
						$r = $self->ftp->rename($rtmppath.'/'.$file, $rfile);
						if ($self->ftp->isError($r)) {
							$self->e('Could not perform operation, stopping.');
							throw new \Exception('', 1);
						}
					}
				}
			},
			'D' => function($rpath, $file, $lfile, $lfileh, $rfile) use ($rtmppath, $self) {
				$self->e('Deleting '.$rfile);
				if ($self->go === true) {
					$r = $self->ftp->rm($rfile, true);
					if ($self->ftp->isError($r)) {
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
	function _processChanges($rpath, $handlers = array()) {
		foreach ($this->svn_changes as $value) {
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
				$this->e('Unknown SVN status '.$value['status'].' for file '.$value['file']);
			}
		}
	}

	/**
	 * Remote temporary directory name
	 *
	 * @param string $rpath 
	 * @return void
	 */
	function _getTmpDirName($rpath) {
		$rpath .= '/'.$this->tmpDir;

		return $rpath;
	}

	/**
	 * Creating temporary directory
	 *
	 * @param string $rtmppath 
	 * @return void
	 */
	function _makeTmpDir($rtmppath) {
		if ($this->go === true) {
			$this->e('Preparing temporary directory '.$rtmppath);
			$pwd = $this->ftp->pwd();
			
			$r = $this->ftp->cd($rtmppath);
			if (!$this->ftp->isError($r)) {
				// If cd was successful, going back to where we were
				$r = $this->ftp->cd($pwd);
				
				// Deleting tmp dir
				$this->e('Found an existing temporary directory on the remote server, deleting it');
				$r = $this->ftp->rm($rtmppath.'/', true);
				if ($this->ftp->isError($r)) {
					$this->e('Could not perform operation, stopping.');
					throw new \Exception('', 1);
				}
			}

			$r = $this->ftp->mkdir($rtmppath, true);
			$this->e('Creating temporary directory');
			if ($this->ftp->isError($r)) {
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
	function _cleanupTmpDir($rtmppath) {
		if ($this->go === true) {
			$this->e('Cleaning up temporary directory '.$rtmppath);
			
			if ($this->go === true) {
				$r = $this->ftp->rm($rtmppath.'/', true);
				if ($this->ftp->isError($r)) {
					$this->e('Could not perform operation (!)');
				}
			}
		}
	}

	/**
	 * Checking if remote directory exists
	 *
	 * @param string $rdir 
	 * @return void
	 */
	function _directoryExists($rdir) {
		$directoryExists = false;
		
		// Checking if directory exists by trying to cd into it
		$pwd = $this->ftp->pwd();
		$r = $this->ftp->cd($rdir);
		if ($this->ftp->isError($r)) {
			$directoryExists = false;
		} else {
			$directoryExists = true;
			
			// If cd was successful, going back to where we were
			$r = $this->ftp->cd($pwd);
		}

		return $directoryExists;
	}

	/**
	 * Checking file permissions on the remote server
	 *
	 * @return void
	 */
	function checkPermissions() {
		$this->e('Checking permissions');
		
		if (!isset($this->profile['permissions']) || empty($this->profile['permissions'])) {
			$this->e('No permissions associated to this profile');
			return;
		}
		
		$rpath = $this->profile['ftp']['path'];
		
		$self = $this; // for php 5.3, could be remove when will be using php 5.4
		$this->_processChanges($rpath, array(
			'M' => function($rpath, $file, $lfile, $lfileh, $rfile) use (&$flushlist, $self) {
				$permissions = $self->_checkPermissions($file);
				if ($permissions !== false) {
					$self->_updatePermissions($self, $rpath, $file, $lfile, $lfileh, $rfile, $permissions);
				}
			},
			'A' => function($rpath, $file, $lfile, $lfileh, $rfile) use (&$flushlist, $self) {
				$permissions = $self->_checkPermissions($file);
				if ($permissions !== false) {
					$self->_updatePermissions($self, $rpath, $file, $lfile, $lfileh, $rfile, $permissions);
				}
			}
		));
	}

	/**
	 * Checking if the file matches one of the permission rules
	 *
	 * @param string $file 
	 * @return int new permissions to apply, of false
	 */
	function _checkPermissions($file) {
		if (!isset($this->profile['permissions']) || empty($this->profile['permissions'])) {
			return false;
		}
		
		$shouldUpdatePermissions = false;
		foreach ($this->profile['permissions'] as $pattern => $permissions) {
			$r = fnmatch($pattern, $file);
			if ($r) {
				return $permissions;
			}
		}

		return $shouldUpdatePermissions;
	}

	/**
	 * Updating permissions on a file/folder
	 *
	 * @param string $file 
	 * @param int new permissions to apply, of false
	 * @return void
	 */
	function _updatePermissions($self, $rpath, $file, $lfile, $lfileh, $rfile, $permissions) {
		if (is_dir($lfile)) {
			$self->e('Updating permissions on directory '.$rfile.' to '.$permissions);
			if ($self->go === true) {
				$r = $self->ftp->chmod($rfile, octdec($permissions), true);
				if ($self->ftp->isError($r)) {
					$self->e('Could not perform operation, stopping.');
					throw new \Exception('', 1);
				}
			}
		}
		else {
			$self->e('Updating permissions on file '.$rfile.' to '.$permissions);
			if ($self->go === true) {
				$r = $self->ftp->chmod($rfile, octdec($permissions), false);
				if ($self->ftp->isError($r)) {
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
	function makeCdnFlushList() {
		$this->e('Making CDN flush list');
		
		if (!isset($this->profile['cdn']['flushlist'])) {
			$this->e('Couldn\'t make CDN flush list since no rules have been defined for this profile');
			return;
		}
		
		$rpath = $this->profile['ftp']['path'];
		$flushlist = array();
		
		$self = $this; // for php 5.3, could be remove when will be using php 5.4
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
				// NB: no specific check on directories since all files inside will be listed as 'D' by SVN
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
	 *
	 * @param string $file 
	 * @return boolean
	 */
	function _shouldCdnFlush($file) {
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
	function updateRemoteRevision() {
		if ($this->go === true) {
			$this->e('Updating FTP rev');
			file_put_contents($this->lrevfile, $this->newrev);
			$r = $this->ftp->put($this->lrevfile, $this->rrevfile, true);
			if ($this->ftp->isError($r)) {
				$this->e('Could not update FTP rev.');
				throw new \Exception('', 1);
			}
			unlink($this->lrevfile);
		}
	}

	/**
	 * undocumented function
	 *
	 * @return void
	 **/
	function _decryptPassword($encryptedPassword) {
		$encrypter = new \Crypt_AES();
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
	function e($str) {
		echo $str.PHP_EOL;
		
		file_put_contents($this->logfile, $str."\n", FILE_APPEND);
	}
}

?>
