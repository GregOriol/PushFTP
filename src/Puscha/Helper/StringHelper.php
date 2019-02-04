<?php

namespace Puscha\Helper;

class StringHelper
{
    const POOL_ALPHA                        = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const POOL_NUM                          = '0123456789';
    const POOL_NUM_NONZERO                  = '123456789';
    const POOL_ALPHANUM                     = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    const POOL_ALPHANUM_HUMANREADABLE       = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ123456789';
    const POOL_HEXDEC                       = '0123456789abcdef';
    const POOL_DISTINCT                     = '2345679ACDEFHJKLMNPRSTUVWXYZ';

    /**
     * Random string generator
     * Inspired by: https://gist.github.com/raveren/5555297.
     *
     * @param string $pool   The seed to use for random characters (see consts SEED_ALPHANUM, SEED_ALPHANUM_HUMANREADABLE, ...)
     * @param int    $length The length of the string
     *
     * @return string
     */
    public static function generateRandomString($pool = self::POOL_ALPHA, $length = 8)
    {
        $str = '';
        $max = mb_strlen($pool, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $str .= $pool[random_int(0, $max)];
        }

        return $str;
    }
}
