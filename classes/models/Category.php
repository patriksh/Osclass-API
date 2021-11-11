<?php
/* Developed by defected.dev | 2021
 *
 * https://github.com/dftd/Osclass-API
*/

namespace DFTDAPI\Models;

use Category;

class CategoryDAO extends Category {
    public function __construct() {
        parent::__construct();
    }

    public function listEnabled() {
        $found = false;
        $key = md5(osc_base_url() . 'DFTDAPI\Models\CategoryDAO:listEnabled:' . osc_current_user_locale());
        $cache = osc_cache_get($key, $found);

        if(!$cache) {
            $this->dao->select('a.*, b.*, c.i_num_items');
            $this->dao->from($this->getTableName() . ' as a');
            $this->dao->join(DB_TABLE_PREFIX . 't_category_description as b', '(a.pk_i_id = b.fk_i_category_id AND b.fk_c_locale_code = "' . osc_current_user_locale() . '")', 'INNER');
            $this->dao->join(DB_TABLE_PREFIX . 't_category_stats as c ', 'a.pk_i_id = c.fk_i_category_id', 'LEFT');
            $this->dao->where('a.b_enabled = 1');
            $this->dao->where('b.s_name != ""');
            $this->dao->orderBy('i_position', 'ASC');
            $result = $this->dao->get();

            if(!$result) return array();    
            $categories = $result->result();

            osc_cache_set($key, $categories, OSC_CACHE_TTL);
            return $categories;
        } else {
            return $cache;
        }
    }
}