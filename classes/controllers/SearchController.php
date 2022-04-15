<?php
/* Developed by defected.dev | 2021
 *
 * https://github.com/dftd/Osclass-API
*/

namespace DFTDAPI\Controllers;

use Search;
use Field;
use Item;
use ItemResource;
use LatestSearches;
use Params;

class SearchController extends Controller {
    public $searchDAO;

    public function __construct() {
        parent::__construct();
        $this->searchDAO = Search::newInstance();
        $this->fieldDAO = Field::newInstance();
        $this->itemResourceDAO = ItemResource::newInstance();
    }

    public function search() {
        $category = Params::getParam('category');
        if($category != '') $this->searchDAO->addCategory($category);

        $user = Params::getParam('user');
        if($user != '') $this->searchDAO->fromUser($user);

        $withPicture = Params::getParam('withPicture');
        if(Params::getParam('withPicture') != '') $this->searchDAO->withPicture((bool) $withPicture);

        $onlyPremium = Params::getParam('premium');
        if(Params::getParam('premium') != '') $this->searchDAO->onlyPremium((bool) $onlyPremium);

        $this->searchDAO->addCityArea(Params::getParam('cityArea'));
        $this->searchDAO->addCity(Params::getParam('cityId'));
        $this->searchDAO->addRegion(Params::getParam('regionId'));
        $this->searchDAO->addCountry(Params::getParam('countryId'));
        $this->searchDAO->addLocale(Params::getParam('locale'));
        $this->searchDAO->priceRange(Params::getParam('priceMin'), Params::getParam('priceMax'));
        
        $allowedColumnsForSorting = Search::getAllowedColumnsForSorting();
        $allowedTypesForSorting = Search::getAllowedTypesForSorting();

        $order = Params::getParam('order');
        $order = in_array($order, $allowedColumnsForSorting) ? $order : osc_default_order_field_at_search(); 
        
        $orderType = Params::getParam('orderType');
        $orderType = in_array($orderType, $allowedTypesForSorting) ? $orderType : osc_default_order_type_at_search(); 

        $page = (int) Params::getParam('iPage');
        $page = ($page) ? $page - 1 : 0;

        $perPage = intval(Params::getParam('pagesize'));
        if($perPage > 0) {
            if($perPage > osc_max_results_per_page_at_search()) $perPage = osc_max_results_per_page_at_search();
        } else {
            $perPage = osc_default_results_per_page_at_search();
        }

        $pattern = trim(strip_tags(Params::getParam('pattern')));
        if($pattern != '') $this->searchDAO->addPattern($pattern);

        $this->searchDAO->order($order, $orderType);
        $this->searchDAO->page($page, $perPage);

        $custom_fields = json_decode(Params::getParam('meta'), true);
        $fields = $this->fieldDAO->findIDSearchableByCategories(Params::getParam('category'));
        $table = DB_TABLE_PREFIX . 't_item_meta';

        if(is_array($custom_fields)) {
            foreach($custom_fields as $key => $aux) {
                if(in_array($key, $fields)) {
                    $field = $this->fieldDAO->findByPrimaryKey($key);
                    switch($field['e_type']) {
                        case 'TEXTAREA':
                        case 'TEXT':
                        case 'URL':
                            if($aux == '') break;

                            $aux = "%$aux%";
                            $sql = "SELECT fk_i_item_id FROM $table WHERE ";
                            $str_escaped = $this->searchDAO->dao->escape($aux);
                            $sql .= $table.'.fk_i_field_id = '.$key.' AND ';
                            $sql .= $table.".s_value LIKE ".$str_escaped;
                            $this->searchDAO->addConditions(DB_TABLE_PREFIX . 't_item.pk_i_id IN(' . $sql . ')');
                        break;
                        case 'DROPDOWN':
                        case 'RADIO':
                            if($aux == '') break;

                            $sql = "SELECT fk_i_item_id FROM $table WHERE ";
                            $str_escaped = $this->searchDAO->dao->escape($aux);
                            $sql .= $table.'.fk_i_field_id = '.$key.' AND ';
                            $sql .= $table.".s_value = ".$str_escaped;
                            $this->searchDAO->addConditions(DB_TABLE_PREFIX . 't_item.pk_i_id IN(' . $sql . ')');
                        break;
                        case 'CHECKBOX':
                            if($aux == '') break;

                            $sql = "SELECT fk_i_item_id FROM $table WHERE ";
                            $sql .= $table.'.fk_i_field_id = '.$key.' AND ';
                            $sql .= $table.".s_value = 1";
                            $this->searchDAO->addConditions(DB_TABLE_PREFIX . 't_item.pk_i_id IN(' . $sql . ')');
                        break;
                        case 'DATE':
                            if($aux == '') break;

                            $y = (int) date('Y', $aux);
                            $m = (int) date('n', $aux);
                            $d = (int) date('j', $aux);
                            $start = mktime('0', '0', '0', $m, $d, $y);
                            $end = mktime('23', '59', '59', $m, $d, $y);
                            $sql = "SELECT fk_i_item_id FROM $table WHERE ";
                            $sql .= $table.'.fk_i_field_id = '.$key.' AND ';
                            $sql .= $table.".s_value >= ".($start)." AND ";
                            $sql .= $table.".s_value <= ".$end;
                            $this->searchDAO->addConditions(DB_TABLE_PREFIX . 't_item.pk_i_id IN(' . $sql . ')');
                        break;
                        case 'DATEINTERVAL':
                            if(is_array($aux) && (!empty($aux['from']) && !empty($aux['to']))) {
                                $from = $aux['from'];
                                $to = $aux['to'];
                                $start = $from;
                                $end = $to;
                                $sql = "SELECT fk_i_item_id FROM $table WHERE ";
                                $sql .= $table.'.fk_i_field_id = '.$key.' AND ';
                                $sql .= $start." >= ".$table.".s_value AND s_multi = 'from'";
                                $sql1 = "SELECT fk_i_item_id FROM $table WHERE ";
                                $sql1 .= $table.".fk_i_field_id = ".$key." AND ";
                                $sql1 .= $end." <= ".$table.".s_value AND s_multi = 'to'";
                                $sql_interval = "select a.fk_i_item_id from(".$sql.") a where a.fk_i_item_id IN(" . $sql1 . ")";
                                $this->searchDAO->addConditions(DB_TABLE_PREFIX . 't_item.pk_i_id IN(' . $sql_interval . ')');
                            }
                        break;
                    }
                }
            }
        }

        osc_run_hook('api_search_conditions', Params::getParamsAsArray());

        $key = md5(osc_base_url() . $this->searchDAO->toJson());
        $found = null;
        $cache = osc_cache_get($key, $found);

        $aItems = null;
        $iTotalItems = null;
        if($cache) {
            $aItems = $cache['aItems'];
            $iTotalItems = $cache['iTotalItems'];
        } else {
            $aItems = $this->searchDAO->doSearch();
            $iTotalItems = $this->searchDAO->count();
            $_cache['aItems'] = $aItems;
            $_cache['iTotalItems'] = $iTotalItems;
            osc_cache_set($key, $_cache, OSC_CACHE_TTL);
        }
        
        $aItems = osc_apply_filter('pre_show_items', $aItems);
        $aItems = osc_apply_filter('api_pre_show_items', $aItems, 'search');

        osc_run_hook('api_search', $this->searchDAO);

        if(osc_save_latest_searches() && (!Params::getParam('page') == null || Params::getParam('page') == 1)) {
            $savePattern = osc_apply_filter('save_latest_searches_pattern', $pattern);
            if($savePattern != '') {
                LatestSearches::newInstance()->insert(['s_search' => $savePattern, 'd_date' => date('Y-m-d H:i:s')]);
            }
        }

        foreach($aItems as $key => $item) {
            $aItems[$key]['resources'] = $this->itemResourceDAO->getAllResourcesFromItem($item['pk_i_id']);
        }

        $aPremiums = [];
        if(!$page) {
            $aPremiums = $this->searchDAO->getPremiums(10);
            foreach($aPremiums as $key => $item) {
                $aPremiums[$key]['resources'] = $this->itemResourceDAO->getAllResourcesFromItem($item['pk_i_id']);
            }
        }

        return $this->json(['items' => $aItems, 'total' => $iTotalItems, 'premiums' => $aPremiums]);
    }

    public function premium() {
        $count = 10;
        $items = $this->searchDAO->getPremiums($count);

        foreach($items as $index => $item) {
            unset($items[$index]['s_secret']);
            $items[$index]['resources'] = $this->itemResourceDAO->getAllResourcesFromItem($item['pk_i_id']);
        }

        $items = osc_apply_filter('api_pre_show_items', $items, 'premium');

        return $this->json($items);
    }

    public function latest() {
        $items = $this->searchDAO->getLatestItems();

        foreach($items as $index => $item) {
            unset($items[$index]['s_secret']);
            $items[$index]['resources'] = $this->itemResourceDAO->getAllResourcesFromItem($item['pk_i_id']);
        }

        $items = osc_apply_filter('api_pre_show_items', $items, 'latest');

        return $this->json($items);
    }

    public function similar(int $id) {
        $category = (int) Params::getParam('category');
        if(!$category) {
            $item = Item::newInstance()->findByPrimaryKey($id);
            $category = $item['fk_i_category_id'];
        }

        // new search?
        $search = new Search();
        $search->addCategory($category);
        $search->limit(1, 5); // idk
        $search->addItemConditions(DB_TABLE_PREFIX.'t_item.pk_i_id != '.$id);
        $items = $search->doSearch();

        foreach($items as $index => $item) {
            unset($items[$index]['s_secret']);
            $items[$index]['resources'] = $this->itemResourceDAO->getAllResourcesFromItem($item['pk_i_id']);
        }

        $items = osc_apply_filter('api_pre_show_items', $items, 'similar');

        return $this->json($items);
    }
}