<?php
/* Developed by defected.dev | 2021
 *
 * https://github.com/dftd/Osclass-API
*/

require_once '../../../../oc-load.php';

class DFTDAPI_API {
    public function __construct() {
        $this->initClasses();
        $this->CORSHeaders();
        $this->initRouter();
    }

    // TODO: autoload?
    private function initClasses() {
        $actionVersion = DFTDAPI_Helper::getOsclassVersion();

        require_once DFTDAPI_PATH . 'classes/JWT.php';
        require_once DFTDAPI_PATH . 'classes/lib/Router.php';

        foreach(glob(DFTDAPI_PATH . 'classes/models/*.php') as $file) require_once $file;

        require_once DFTDAPI_PATH . 'classes/actions/ItemActions_' . $actionVersion . '.php';
        require_once DFTDAPI_PATH . 'classes/actions/UserActions_' . $actionVersion . '.php';

        require_once DFTDAPI_PATH . 'classes/controllers/Controller.php';
        foreach(glob(DFTDAPI_PATH . 'classes/controllers/*.php') as $file) require_once $file;
    }

    private function CORSHeaders() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Selected-language');

        if(Params::getServerParam('REQUEST_METHOD') == 'OPTIONS') {
            echo '{"method":"OPTIONS"}';
            exit;
        }
    }

    private function initRouter() {
        $router = new \Bramus\Router\Router();
        $router->setNamespace('\DFTDAPI\Controllers');
        $this->registerRoutes($router);
        $router->run();
    }

    private function registerRoutes($router) {
        $router->get('/', 'IndexController@index');

        $router->get('/settings', 'SettingsController@index');
        
        $router->mount('/item', function() use ($router) {
            // Ajax images
            $router->post('image', 'ItemController@imageUpload');
            $router->delete('image/existing', 'ItemController@imageDeleteExisting');
            $router->delete('image/{name}', 'ItemController@imageDelete');
            // Single item (other)
            $router->post('comment', 'ItemController@addComment');
            $router->delete('comment', 'ItemController@deleteComment');
            $router->post('contact', 'ItemController@contact');
            $router->post('mark', 'ItemController@mark');
            // Single item
            $router->get('{id}', 'ItemController@view');
            $router->post('/', 'ItemController@add');
            $router->put('/', 'ItemController@edit');
            $router->delete('/{id}', 'ItemController@delete');
        });
        
        $router->mount('/user', function() use ($router) {
            $router->post('login', 'UserController@login');
            $router->post('logout', 'UserController@logout');
            $router->post('refresh', 'UserController@refresh');

            $router->post('/', 'UserController@add');
            $router->post('validate', 'UserController@validate');
            $router->post('forgot', 'UserController@forgotPassword');
            $router->post('reset', 'UserController@resetPassword');
            $router->get('items', 'UserController@items');
            $router->get('my-items', 'UserController@myItems');
            $router->get('item-count/{type}', 'UserController@itemCount');
            $router->put('email', 'UserController@editEmail');
            $router->put('username', 'UserController@editUsername');
            $router->put('password', 'UserController@editPassword');
            $router->put('/', 'UserController@edit');
            $router->delete('/', 'UserController@delete');
            $router->get('{id}', 'UserController@view');

            $this->needsAuth([
                'logout' => 'POST',
                'my-items' => 'GET',
                'item-count/{type}' => 'GET',
                'edit' => 'GET',
                'email' => 'PUT',
                'username' => 'PUT',
                'password' => 'PUT',
                '/' => 'PUT',
                '/' => 'DELETE'
            ], $router);
        });
        
        $router->get('/category/all', 'CategoryController@all');

        $router->mount('/search', function() use ($router) {
            $router->get('premium', 'SearchController@premium');
            $router->get('latest', 'SearchController@latest');
            $router->get('similar/{id}', 'SearchController@similar');
            $router->get('/', 'SearchController@search');
        });
        
        $router->mount('/location', function() use ($router) {
            $router->get('all', 'LocationController@all');
            $router->get('country/all', 'LocationController@countries');
            $router->get('region/all', 'LocationController@regions');
            $router->get('city/all', 'LocationController@cities');
        });

        $router->mount('/field', function() use ($router) {
            $router->get('category/{category}/', 'FieldController@category');
            $router->get('country/all', 'LocationController@countries');
            $router->get('region/all', 'LocationController@regions');
            $router->get('city/all', 'LocationController@cities');
        });

        osc_run_hook('api_router', $router);
    }

    // TODO: Own functions for router methods or put in helper, so plugins can use this.
    private function needsAuth($routes, $router) {
        foreach($routes as $route => $method) {
            $router->before($method, $route, function() {
                $this->middlewareAuth();
            });
        }
    }

    private function middlewareAuth() {
        $token = DFTDAPI_JWT::get();

        if($token != '') {
            $parsedToken = DFTDAPI_JWT::parse($token);
            if($parsedToken === -1) {
                http_response_code(401);
                echo 'Token expired.';
                die;
            } else if(!$parsedToken) {
                http_response_code(403);
                echo 'Unauthorized.';
                die;
            }
        }
    }
}

new DFTDAPI_API();