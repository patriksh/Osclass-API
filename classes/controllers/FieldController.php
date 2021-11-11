<?php
/* Developed by defected.dev | 2021
 *
 * https://github.com/dftd/Osclass-API
*/

namespace DFTDAPI\Controllers;

use Field;

class FieldController extends Controller {
    public $fieldDAO;

    public function __construct() {
        parent::__construct();
        $this->fieldDAO = Field::newInstance();
    }

    public function category(int $category) {
        return $this->json($this->fieldDAO->findByCategory($category));
    }
}