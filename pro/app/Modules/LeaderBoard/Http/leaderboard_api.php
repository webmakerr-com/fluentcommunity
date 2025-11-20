<?php

/**
 * @var $router FluentCommunity\Framework\Http\Router
 */

$router->prefix('leaderboard')->namespace('\FluentCommunityPro\App\Modules\LeaderBoard\Http\Controllers')->withPolicy(\FluentCommunity\App\Http\Policies\PortalPolicy::class)->group(function ($router) {
    $router->get('/', 'LeaderBoardController@getLeaders');
});

$router->prefix('admin/leaderboards')->namespace('\FluentCommunityPro\App\Modules\LeaderBoard\Http\Controllers')->withPolicy(\FluentCommunity\App\Http\Policies\AdminPolicy::class)->group(function ($router) {
    $router->get('levels', 'LeaderBoardController@getLevels');
    $router->post('levels', 'LeaderBoardController@saveLevels');
});
