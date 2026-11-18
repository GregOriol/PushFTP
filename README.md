# PushFTP

A PHP script to handle pushing files to an SFTP server from a Git repository.

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

### Jump host
An sftp target behind a bastion can be reached like ssh's ProxyJump, with a `jump` object in the target (see `samples/pushftp-jump.json`). Only `host` is required, ssh's defaults apply to the others (`~/.ssh` keys, agent and known_hosts).

The tunnel is opened by the system `ssh` (`ssh -W`, OpenSSH 8.4+), the target's authentication still goes through PushFTP end-to-end. The jump's `password` is its `privatekey` passphrase, encrypted like the target's one, and only given to ssh through its environment (askpass). The jump host must be in `known_hosts`, e.g.:

```
ssh-keyscan -p 22 bastion.example.com > bastion.known_hosts
```

## Usage
	php build/pushftp.phar push --profile=preprod ./project
	php build/pushftp.phar push --help

## Encrypt password
Passwords can be encrypted (Argon2id + XSalsa20-Poly1305, requires the PHP sodium extension)

	php build/pushftp.phar encrypt-password

The password and key are prompted when not given with `--password=...` and `--key=...`. The key can also be read from the `PUSHFTP_KEY` environment variable. The same applies to `push` to decrypt the passwords, where `--key` alone prompts for it.

Passwords encrypted with versions before 0.9 (AES) must be encrypted again.

## Generating a private/public key

```
ssh-keygen -t ed25519 -f ./preprod
```
Type a password

Encrypt the password
```
php build/pushftp.phar encrypt-password
```
