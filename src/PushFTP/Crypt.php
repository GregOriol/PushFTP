<?php

namespace PushFTP;

class Crypt
{
	/**
	 * Encrypts a password, result is base64(salt|nonce|ciphertext)
	 *
	 * @param string $password
	 * @param string $key
	 * @return string
	 */
	public static function encrypt($password, $key)
	{
		self::checkSodium();

		$salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
		$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
		$derivedKey = self::deriveKey($key, $salt);

		$ciphertext = sodium_crypto_secretbox($password, $nonce, $derivedKey);
		sodium_memzero($derivedKey);

		return base64_encode($salt.$nonce.$ciphertext);
	}

	/**
	 * Decrypts a password encrypted with encrypt()
	 *
	 * @param string $encrypted
	 * @param string $key
	 * @return string
	 */
	public static function decrypt($encrypted, $key)
	{
		self::checkSodium();

		$data = base64_decode($encrypted, true);
		if ($data === false || strlen($data) < SODIUM_CRYPTO_PWHASH_SALTBYTES + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
			throw new \Exception('Invalid encrypted password format');
		}

		$salt = substr($data, 0, SODIUM_CRYPTO_PWHASH_SALTBYTES);
		$nonce = substr($data, SODIUM_CRYPTO_PWHASH_SALTBYTES, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
		$ciphertext = substr($data, SODIUM_CRYPTO_PWHASH_SALTBYTES + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
		$derivedKey = self::deriveKey($key, $salt);

		$password = sodium_crypto_secretbox_open($ciphertext, $nonce, $derivedKey);
		sodium_memzero($derivedKey);

		if ($password === false) {
			throw new \Exception('Wrong key or corrupted encrypted password');
		}

		return $password;
	}

	/**
	 * Derives an encryption key from the user's key with Argon2id
	 *
	 * @param string $key
	 * @param string $salt
	 * @return string
	 */
	protected static function deriveKey($key, $salt)
	{
		return sodium_crypto_pwhash(
			SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
			$key,
			$salt,
			SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
			SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
			SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
		);
	}

	/**
	 * @return void
	 */
	protected static function checkSodium()
	{
		if (!extension_loaded('sodium')) {
			throw new \Exception('PHP sodium extension is required for encrypted passwords');
		}
	}
}
