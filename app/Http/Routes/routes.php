<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/**
 * @var $router FluentCommunity\Framework\Http\Router
 */

$router->namespace('FluentCommunity\App\Http\Controllers')->group(function($router) {
    require_once __DIR__ . '/api.php';
});

if (file_exists(FLUENT_COMMUNITY_PLUGIN_DIR .'dev/seed-routes.php')) {
    require_once FLUENT_COMMUNITY_PLUGIN_DIR .'dev/seed-routes.php';
}
