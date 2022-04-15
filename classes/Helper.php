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
        $supported = [390, 440, 510, 800];
        $default = 390;
        $version = intval(str_replace('.', '', OSCLASS_VERSION));

        // Use 5.1.0 for future Mindstellar updates.
        if($version >= 500 && $version <= 599) $version = 510;

        // Use 8.0.0 for future Osclasspoint updates.
        if($version >= 800 && $version <= 899) $version = 800;

        return (in_array($version, $supported)) ? $version : $default;
    }
}