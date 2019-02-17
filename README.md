# Puscha

A deployment tool to push change from a repository (Git/SVN) to a target (SFTP/FTP) by calculating differences between pushed versions.

SCMs can be Git or SVN repositories, and targets can be SFTP or FTP.

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.0-8892BF.svg?style=flat)](https://php.net/)

Its main feature is to update a target server according to the changes made on the SCM repository. The tool will retrieve the SCM differences since the last push and upload the new or changed files to the server.

* Uploads only new/changed files
* Deletes removed files
* Dry run mode to simulate the push
* Logs actions, detected changes and SCM diff for review
* Temp folder used on the server for operations (uploads, ...), to prevent disrupting the site during long operations and allow rollback if something fails (connexions drops, write not possible, ...)
* Stores encrypted passwords
* Excludes files that shouldn't be pushed from SCM to server
* Updates permissions on pushed files/folder

Best used with CI setups (Jenkins, GitLab, Travis, ...).

Puscha actually uses [Flysytem](http://flysystem.thephpleague.com) underneath, so many more adapters than SFTP/FTP could be available for the target: Azure, AWS S3, DigitalOcean Spaces, Dropbox, Rackspace, WebDAV, ZipArchive, ...

Puscha can deploy to multiple targets at the same time.

NB: Puscha is a major refactoring of [PushFTP](https://github.com/GregOriol/PushFTP)

## Minimum requirements

* PHP 7.0+
* SVN 1.9 (maybe less)
* Git 2.9 (maybe less)

## Configuration
A puscha.json or puscha.yaml file at the root of the project contains all the settings. See the `samples` folder for various configurations.

A JSON schema is provided (`src/schema.json`), and a test config command is available to validate the configuration file (see below).

## Usage
Download the `puscha.phar` file, which contains the packaged tool, then:
```
$ php puscha.phar list
```

It is also possible to clone the repository and, after running `composer install`, run:
```
$ ./puscha list
```

## Commands
### Run
The main command runs a profile from the configuration file:

```
$ php puscha.phar --help run
```

This command is best run with -v for more information on what is being done.
It can also be run with -vvv for detailed debug logs.

### Encrypt password
A helper command is provided to encrypt passwords:

```
$ php puscha.phar --help tools:encrypt-password
```

### Test config
A helper command is provided to encrypt passwords:

```
$ php puscha.phar --help tools:test-config
```

## Development

A Vagrantfile is provided to launch a "target" box with SFTP and FTP. Check the file to adapt the settings to your environment.

The password for the ubuntu user of the box can usually be found in the file `~/.vagrant.d/boxes/ubuntu-VAGRANTSLASH-xenial64/20161221.0.0/virtualbox/Vagrantfile` (depending on your version of the xenial box).

### Testing
PHPUnit tests are provided in the tests directory, as well as a phpunit.xml.
Run tests with:
```
$ php composer.phar run test
```

### Code style
Symfony code style is applied (rules from: https://github.com/djoos/Symfony-coding-standard)
Run phpcs with:
```
$ php composer.phar run codestyle
```

## Known issues
### With pure-ftpd and hidden files not enabled
Current flysystem's ftp implementation doesn't correctly hidden folders on pure-ftpd servers when not explicitely enabled (https://github.com/thephpleague/flysystem/issues/1006), which causes Puscha to fail creating it's temporary folders.
Solutions: enable dot files in pure-ftp ("yes" in `/etc/pure-ftpd/conf/DisplayDotFiles`), use another ftp server (vsftp for example), use sftp, ...
