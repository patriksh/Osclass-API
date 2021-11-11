<?php
/* Developed by defected.dev | 2021
 *
 * https://github.com/dftd/Osclass-API
*/

namespace DFTDAPI\Models;

use DAO;

class LocationDAO extends DAO {
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

    public function listAllCountries() {
        $found = false;
        $key = md5(osc_base_url() . 'DFTDAPI\Models\LocationDAO:listAllCountries');
        $cache = osc_cache_get($key, $found);

        if(!$cache) {
            $this->dao->select('pk_c_code, s_name');
            $this->dao->from(DB_TABLE_PREFIX . 't_country');
            $result = $this->dao->get();
    
            if(!$result) return array();    
            $countries = $result->result();

            osc_cache_set($key, $countries, OSC_CACHE_TTL);
            return $countries;
        } else {
            return $cache;
        }
    }

    public function listAllRegions() {
        $found = false;
        $key = md5(osc_base_url() . 'DFTDAPI\Models\LocationDAO:listAllRegions');
        $cache = osc_cache_get($key, $found);

        if(!$cache) {
            $this->dao->select('pk_i_id, fk_c_country_code, s_name');
            $this->dao->from(DB_TABLE_PREFIX . 't_region');
            $result = $this->dao->get();
    
            if(!$result) return array(); 
            $regions = $result->result();

            osc_cache_set($key, $regions, OSC_CACHE_TTL);
            return $regions;
        } else {
            return $cache;
        }
    }

    public function listAllCities() {
        $found = false;
        $key = md5(osc_base_url() . 'DFTDAPI\Models\LocationDAO:listAllCities');
        $cache = osc_cache_get($key, $found);

        if(!$cache) {
            $this->dao->select('pk_i_id, fk_i_region_id, s_name');
            $this->dao->from(DB_TABLE_PREFIX . 't_city');
            $result = $this->dao->get();
    
            if(!$result) return array(); 
            $cities = $result->result();

            osc_cache_set($key, $cities, OSC_CACHE_TTL);
            return $cities;
        } else {
            return $cache;
        }
    }
}