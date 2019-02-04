**WARNING**: PushFTP is **DEPRECATED** in favor of [Puscha](https://github.com/GregOriol/Puscha), which is a major refactoring of this tool. No more changes will be made to this repository. Please switch to Puscha!

----

# PushFTP

A PHP script to handle pushing files to an FTP/SFTP from an SVN/Git repository.

Its main feature is to update a server according to the changes made on the related SCM repository. The script will check the SCM differences since last push and upload the new or changed files to the server.

* Uploads only new/changed files
* Deletes removed files
* Dry run mode to simulate the push
* Logs actions, detected changes and SCM diff for review
* Temp folder used on the server for upload, to prevent disrupting the site during long uploads
* Stores encrypted passwords
* Excludes files that shouldn't be pushed from SCM to server
* Generates a flush list for CDNs
* Updates permissions on pushed files/folder

Best used with CI setups (Jenkins, ...).

## Configuration
A pushftp.json file at the root of the project contains all the settings. See samples folder.

## Usage
	php build/pushftp.phar --help

## Tools
### Encrypt password
A helper tool is provided to encrypt passwords

	php encryptPassword.php --help
