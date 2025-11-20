<?php

namespace FluentCommunityPro\App\Modules\Giphy;

class GiphyModule
{
    public function register($app)
    {
        /*
        * register the routes
        */
        $app->router->group(function ($router) {
            require_once __DIR__ . '/Http/giphy_api.php';
        });

        add_filter('fluent_community/portal_vars', function ($vars) {
            $vars['features']['giphy_app'] = true;
            return $vars;
        });

    }
}
