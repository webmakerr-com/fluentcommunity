<?php

/**
 * @var $router FluentCommunity\Framework\Http\Router
 */

$router->prefix('cart')
    ->namespace('\FluentCommunity\Modules\Integrations\FluentCart\Http\Controllers')
    ->withPolicy(\FluentCommunity\App\Http\Policies\SpacePolicy::class)
    ->group(function ($router) {
        $router->get('/products/search', 'PaywallController@searchProduct');
        $router->post('/products/create', 'PaywallController@createProduct');
        $router->get('/spaces/{spaceId}/paywalls', 'PaywallController@getPaywalls')->int('spaceId');
        $router->post('/spaces/{spaceId}/paywalls', 'PaywallController@addPaywall')->int('spaceId');
        $router->delete('/spaces/{spaceId}/paywalls', 'PaywallController@removePaywall')->int('spaceId');
    });
