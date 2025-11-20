<?php

/**
 * @var $router FluentCommunity\Framework\Http\Router
 */

$router->prefix('migrations')
    ->namespace('\FluentCommunity\Modules\Migrations\Http\Controllers')
    ->withPolicy(\FluentCommunity\App\Http\Policies\AdminPolicy::class)
    ->group(function ($router) {
        $router->get('/', 'MigrationController@getAvailableMigrations');
        $router->get('/buddypress/config', 'BPMigrationController@getMigrationConfig');
        $router->post('/buddypress/start', 'BPMigrationController@startMigration');
        $router->get('/buddypress/status', 'BPMigrationController@getPollingStatus');
    });
