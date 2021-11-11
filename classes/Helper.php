<?php
/* Developed by defected.dev | 2021
 *
 * https://github.com/dftd/Osclass-API
*/

class DFTDAPI_Helper {
    public static function getPreference($key) {
        return osc_get_preference($key, DFTDAPI_PREF_KEY);
    }

    public static function getBoolPreference($key) {
        return osc_get_bool_preference($key, DFTDAPI_PREF_KEY);
    }

    public static function setPreference($key, $value, $type = 'STRING') {
        return osc_set_preference($key, $value, DFTDAPI_PREF_KEY, $type);
    }

    public static function getOsclassVersion() {
        $supported = [390, 440, 502, 800];
        $default = 390;
        $version = intval(str_replace('.', '', OSCLASS_VERSION));

        return (in_array($version, $supported)) ? $version : $default;
    }
}