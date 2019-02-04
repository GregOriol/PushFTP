<?php

namespace Puscha\Helper;

class PasswordHelper
{
    /**
     * @param string $string
     * @param string $key
     *
     * @return string
     */
    public static function encrypt($string, $key)
    {
        $encrypter = new \phpseclib\Crypt\AES();
        $encrypter->setKey($key);
        $result = $encrypter->encrypt($string);
        $result = base64_encode($result);

        return $result;
    }

    /**
     * @param string $string
     * @param string $key
     *
     * @return string
     */
    public static function decrypt($string, $key)
    {
        $decrypter = new \phpseclib\Crypt\AES();
        $decrypter->setKey($key);
        $result = $decrypter->decrypt(base64_decode($string));

        return $result;
    }
}
