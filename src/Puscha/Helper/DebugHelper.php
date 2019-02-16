<?php

namespace Puscha\Helper;

class DebugHelper
{
    /**
     * Replaces some properties in debugInfo (see __debugInfo) to prevent dumping too much non useful information.
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
     * @param bool|null $value
     * @param string    $true
     * @param string    $false
     * @param string    $null
     *
     * @return string|null
     */
    public static function trueFalseNull($value, $true = 'true', $false = 'false', $null = 'null')
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
