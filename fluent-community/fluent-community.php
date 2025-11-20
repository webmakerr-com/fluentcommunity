<?php

defined('ABSPATH') or die;

/**
 * Plugin Name: FluentCommunity
 * Description: The super-fast Community Plugin for WordPress
 * Version: 2.1.01
 * Author: WPManageNinja LLC
 * Author URI: https://fluentcommunity.co
 * Plugin URI: https://fluentcommunity.co
 * License: GPLv2 or later
 * Text Domain: fluent-community
 * Domain Path: /language
 */

define('FLUENT_COMMUNITY_PLUGIN_VERSION', '2.1.01');
define('FLUENT_COMMUNITY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FLUENT_COMMUNITY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FLUENT_COMMUNITY_DIR_FILE', __FILE__);
define('FLUENT_COMMUNITY_START_TIME', microtime(true));
define('FLUENT_COMMUNITY_DB_VERSION', '1.0.5');
define('FLUENT_COMMUNITY_MIN_PRO_VERSION', '2.1.0');

if (!defined('FLUENTCRM_COMMUNITY_UPLOAD_DIR')) {
    define('FLUENT_COMMUNITY_UPLOAD_DIR', 'fluent-community');
}

require __DIR__ . '/vendor/autoload.php';

call_user_func(function ($bootstrap) {
    $bootstrap(__FILE__);
}, require(__DIR__ . '/boot/app.php'));

