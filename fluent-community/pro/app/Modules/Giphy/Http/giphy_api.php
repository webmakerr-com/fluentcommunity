<?php

/**
 * @var $router FluentCommunity\Framework\Http\Router
 */

$router->prefix('giphy')->namespace('\FluentCommunityPro\App\Modules\Giphy\Http\Controllers')->withPolicy(\FluentCommunity\App\Http\Policies\PortalPolicy::class)->group(function ($router) {
    $router->get('/', 'GiphyController@index');
});
