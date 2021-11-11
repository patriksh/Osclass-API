<?php
/* Developed by defected.dev | 2021
 *
 * https://github.com/dftd/Osclass-API
*/

namespace DFTDAPI\Controllers;

use DFTDAPI\Models\SettingsDAO;
use Preference;

class SettingsController extends Controller {
    public $settingsDAO;

    public function __construct() {
        parent::__construct();
        $this->settingsDAO = SettingsDAO::newInstance();
    }

    public function index() {
        $settings = [];

        $settings['item'] = [
            'auth_required' => osc_reg_user_post(),
            'title_max_chars' => osc_max_characters_per_title(),
            'description_max_chars' => osc_max_characters_per_description(),
            'images_enabled' => osc_images_enabled_at_items(),
            'images_count' => osc_max_images_per_item(),
            'image_size' => explode('x', osc_normal_dimensions()),
            'price_enabled' => osc_price_enabled_at_items(),
        ];

        $settings['user'] = [
            'enabled' => osc_users_enabled(),
            'register' => osc_user_registration_enabled(),
        ];

        $settings['currency'] = [
            'default' => osc_currency(),
            'list' => $this->settingsDAO->listCurrencies(),
        ];

        $settings['locale'] = [
            'default' => osc_language(),
            'list' => $this->settingsDAO->listLanguages(),
        ];

        $settings['plugins'] = osc_apply_filter('api_settings_plugins', []);

        return $this->json($settings);
    }
}