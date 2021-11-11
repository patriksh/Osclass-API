<?php
/* Developed by defected.dev | 2021
 *
 * https://github.com/dftd/Osclass-API
*/

/*
Plugin Name: Osclass API
Plugin URI: https://defected.dev
Description: REST API for Osclass.
Version: 1.0.0
Author: defected.dev
Author URI: https://defected.dev
Plugin update URI: 
*/

define('DFTDAPI_PATH', dirname(__FILE__) . '/');
define('DFTDAPI_PLUGINPATH', osc_plugin_path(__FILE__));
define('DFTDAPI_FOLDER', 'dftd_api');
define('DFTDAPI_PREF_KEY', 'plugin_dftdapi');

require_once DFTDAPI_PATH . 'classes/Helper.php';
require_once DFTDAPI_PATH . 'classes/models/RefreshToken.php';

osc_register_plugin(DFTDAPI_PLUGINPATH, function() {
    // Generate random JWT secret.
    $file = fopen(DFTDAPI_PATH . 'jwt_secret.php', 'w');
    fwrite($file, "<?php define('DFTDAPI_JWT_SECRET', '" . md5(uniqid()) . "');");
    fclose($file);

    // Create refresh token DB table.
    \DFTDAPI\Models\RefreshTokenDAO::newInstance()->install();
});

osc_add_hook(DFTDAPI_PLUGINPATH . '_uninstall', function() {
    // Delete refresh token DB table.
    \DFTDAPI\Models\RefreshTokenDAO::newInstance()->uninstall();

    // Delete custom forgot password email template.
    Page::newInstance()->deleteByInternalName('email_dftdapi_forgot_password');
});

osc_add_hook(DFTDAPI_PLUGINPATH . '_configure', function() {
    osc_redirect_to(osc_route_admin_url('dftdapi-settings'));
});

// Cleanup expired refresh tokens from the DB.
function dftdapi_token_cleanup() {
    \DFTDAPI\Models\RefreshTokenDAO::newInstance()->cleanup();
}
osc_add_hook('cron_weekly', 'dftdapi_token_cleanup');

// Delete refresh tokens when deleting user.
function dftdapi_user_delete_token($user) {
    \DFTDAPI\Models\RefreshTokenDAO::newInstance()->delete(['fk_i_user_id' => $user]);
}
osc_add_hook('delete_user', 'dftdapi_user_delete_token');