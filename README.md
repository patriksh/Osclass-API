# Osclass API

Osclass API plugin, with all the required endpoints and JWT auth.

## Osclass App (Native + PWA)

You always wanted a mobile app for Osclass, didn't you? Well, you can get one now.

Check the demo: https://pwa.osclass.app

Osclass App will soon be available for sale on it's official website. You, the reader of this message can get it early, and for a special discount!

Contact me at info[at]defected.dev for more info.

## Routes

Check `api/index.php`.

## ItemActions / UserActions

API contains `ItemActions` and `UserActions` classes from several Osclass versions, with defined namespace and modified hooks.

All the hooks and filters are prefixed by `api_`, so no plugins interfer with API by default.

If you still with to use default action classes, replace `use \DFTDAPI\Actions\UserActions as UserActions;` with `use UserActions`, same for item.

## Plugins

Easily extendable using the simple `api_router` hook.

Example:

```php
osc_add_hook('api_router', function($router) {
    $router->get('/hello-world', function() {
        echo 'Hello world!';
    });
});
```
