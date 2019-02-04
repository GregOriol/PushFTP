<?php

namespace Puscha\Helper;

class DebugHelper
{
    /**
     * Replaces some properties in debugInfo (see __debugInfo) to prevent dumping too much non usefull information.
     *
     * @param array $debugInfo
     */
    public static function simplifyDebugInfo(&$debugInfo)
    {
        if (isset($debugInfo['logger']) && is_object($debugInfo['logger'])) {
            $debugInfo['logger'] = 'object('.get_class($debugInfo['logger']).')';
        }
    }

    /**
     * @param string|string[] $strings
     *
     * @return string
     */
    public static function logPrefix($strings)
    {
        $out = '';

        if (is_string($strings)) {
            $strings = array($strings);
        }

        foreach ($strings as $string) {
            $out .= '['.$string.'] ';
        }

        return $out;
    }

    public static function trueFalseNull($value, $true, $false, $null)
    {
        if ($value === true) {
            return $true;
        } elseif ($value === false) {
            return $false;
        } elseif ($value === null) {
            return $null;
        }

        return null;
    }
}
