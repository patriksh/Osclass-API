<?php
/* Developed by defected.dev | 2021
 *
 * https://github.com/dftd/Osclass-API
*/

namespace DFTDAPI\Controllers;

class IndexController extends Controller {
    public function index() {
        echo 'Hello ' . osc_page_title();
    }
}