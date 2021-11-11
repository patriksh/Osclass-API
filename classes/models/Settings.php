<?php
/* Developed by defected.dev | 2021
 *
 * https://github.com/dftd/Osclass-API
*/

namespace DFTDAPI\Models;

use DAO;
use Currency;
use OSCLocale;

class SettingsDAO extends DAO {
    private static $instance;

    public function __construct() {
        parent::__construct();
    }

    public static function newInstance() {
        if(!self::$instance instanceof self) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public function listCurrencies() {
        $dao = Currency::newInstance();
        $dao->dao->select('pk_c_code, s_description');
        $dao->dao->from($dao->getTableName());
        $dao->dao->where('b_enabled', 1);
        $result = $dao->dao->get();

        if(!$result) return array();

        return $result->result();
    }

    public function listLanguages() {
        $dao = OSCLocale::newInstance();
        $dao->dao->select('pk_c_code, s_name, s_currency_format, s_dec_point, s_thousands_sep, i_num_dec, s_date_format');
        $dao->dao->from($dao->getTableName());
        $dao->dao->where('b_enabled', 1);
        $dao->dao->orderBy('pk_c_code = "' . osc_language() . '" DESC, s_name', 'ASC');
        $result = $dao->dao->get();

        if(!$result) return array();

        return $result->result();
    }
}