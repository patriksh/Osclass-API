<?php
/* Developed by defected.dev | 2021
 *
 * https://github.com/dftd/Osclass-API
*/

namespace DFTDAPI\Controllers;

use DFTDAPI_JWT;
use Params;
use Session;

class Controller {
    public $authUserId = 0;

    public function __construct() {
        $this->fixParams();
        $this->loadLanguage();
        $this->authenticate();
    }

    private function fixParams() {
        $input = file_get_contents("php://input");
        if($input != '') {
            $params = json_decode($input, true);
            if(is_array($params)) {
                foreach($params as $param => $value) {
                    Params::setParam($param, $value);
                }
            }
        }
    }

    private function loadLanguage() {
        Session::newInstance()->_drop('userLocale');

        $locale = Params::getServerParam('HTTP_SELECTED_LANGUAGE');
        if($locale && strlen($locale) === 5 && array_search($locale, array_column(osc_get_locales(), 'pk_c_code'), true) !== false) {
            Session::newInstance()->_set('userLocale', $locale);
        }
    }

    private function authenticate() {
        Session::newInstance()->_drop('userId');

        $userId = DFTDAPI_JWT::getAuthenticatedUser();
        if($userId) {
            Session::newInstance()->_set('userId', $userId);
        }
    }

    public function json($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($json);
    }

    public function abort($code, $message = '') {
        http_response_code($code);
        if($message != '') {
            $this->json(['message' => $message]);
        }
        die;
    }
}