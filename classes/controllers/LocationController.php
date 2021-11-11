<?php
/* Developed by defected.dev | 2021
 *
 * https://github.com/dftd/Osclass-API
*/

namespace DFTDAPI\Controllers;

use DFTDAPI\Models\LocationDAO;

class LocationController extends Controller {
    public $countryDAO;
    public $regionDAO;
    public $cityDAO;

    public function __construct() {
        parent::__construct();
        $this->locationDAO = LocationDAO::newInstance();
    }

    public function all() {
        return $this->json([
            'countries' => $this->locationDAO->listAllCountries(),
            'regions' => $this->locationDAO->listAllRegions(),
            'cities' => $this->locationDAO->listAllCities()
        ]);
    }

    public function countries() {
        return $this->json($this->locationDAO->listAllCountries());
    }

    public function regions() {
        return $this->json($this->locationDAO->listAllRegions());
    }

    public function cities() {
        return $this->json($this->locationDAO->listAllCities());
    }
}