<?php
/* Developed by defected.dev | 2021
 *
 * https://github.com/dftd/Osclass-API
*/

namespace DFTDAPI\Controllers;

use DFTDAPI\Models\CategoryDAO;

class CategoryController extends Controller {
    public $categoryDAO;

    public function __construct() {
        parent::__construct();
        $this->categoryDAO = CategoryDAO::newInstance();
    }

    public function all() {
        return $this->json($this->categoryDAO->listEnabled());
    }
}