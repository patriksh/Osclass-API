# Osclass API

Osclass API plugin, with all the required endpoints and JWT auth.

## Osclass App (Native + PWA)

You always wanted a mobile app for Osclass, didn't you? Well, you can get one now, and it's built with this very API!

Check it **now** at https://osclass.app

## Usage

API URL is yoursite.com/oc-content/plugins/dftd_api/api/.

Check `api/index.php` for list of endpoints.

## ItemActions / UserActions

API contains `ItemActions` and `UserActions` classes from several Osclass versions, with defined namespace and modified hooks.

All the hooks and filters are prefixed by `api_`, so no plugins interfer with API by default.

If you still prefer to use default action classes, replace `use \DFTDAPI\Actions\UserActions as UserActions;` with `use UserActions;`, same for item.

## Plugins

Easily extendable using the simple `api_router` hook.

Example:

```php
function my_api_extension($router) {
    $router->get('/hello-world', function() {
        echo 'Hello world!';
    });
}
osc_add_hook('api_router', 'my_api_extension');
```
