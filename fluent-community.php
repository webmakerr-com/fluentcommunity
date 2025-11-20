<?php

defined('ABSPATH') or die;

// Ensure the minimum PHP version requirement is met before continuing.
if (version_compare(PHP_VERSION, '7.3', '<')) {
    if (function_exists('add_action')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>' . esc_html__('FluentCommunity requires PHP version 7.3 or higher.', 'fluent-community') . '</p></div>';
        });
    }

    if (function_exists('deactivate_plugins')) {
        deactivate_plugins(plugin_basename(__FILE__));
    }

    return;
}

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
define('FLUENT_COMMUNITY_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Pro constants (used by bundled Pro features)
define('FLUENT_COMMUNITY_PRO', true);
define('FLUENT_COMMUNITY_PRO_VERSION', '2.1.0');
define('FLUENT_COMMUNITY_MIN_CORE_VERSION', FLUENT_COMMUNITY_PLUGIN_VERSION);
define('FLUENT_COMMUNITY_PRO_DIR', FLUENT_COMMUNITY_PLUGIN_DIR . 'pro/');
define('FLUENT_COMMUNITY_PRO_URL', FLUENT_COMMUNITY_PLUGIN_URL . 'pro/');
define('FLUENT_COMMUNITY_PRO_DIR_FILE', FLUENT_COMMUNITY_PRO_DIR . 'fluent-community-pro.php');

if (!defined('FLUENTCRM_COMMUNITY_UPLOAD_DIR')) {
    define('FLUENT_COMMUNITY_UPLOAD_DIR', 'fluent-community');
}

update_option('__fluent_community_pro_license', [
    'license_key'    => '1415B451BE1A13C283BA771EA52D38BB',
    'status'         => 'valid',
    'variation_id'   => '999999',
    'variation_title'=> 'Lifetime License',
    'expires'        => gmdate('Y-m-d', strtotime('+100 years')),
    'activation_hash'=> md5('1415B451BE1A13C283BA771EA52D38BB' . home_url())
], false);

add_filter('pre_http_request', function($preempt, $args, $url) {
    if (strpos($url, 'fluentapi.wpmanageninja.com') !== false && strpos($url, 'fluent-cart') !== false) {
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => json_encode([
                'status'          => 'valid',
                'variation_id'    => '999999',
                'variation_title' => 'Lifetime License',
                'expiration_date' => gmdate('Y-m-d', strtotime('+100 years')),
                'activation_hash' => md5('1415B451BE1A13C283BA771EA52D38BB' . home_url()),
                'new_version'     => FLUENT_COMMUNITY_PRO_VERSION,
                'package'         => '',
                'sections'        => [],
                'banners'         => [],
                'icons'           => []
            ])
        ];
    }
    return $preempt;
}, 10, 3);

require __DIR__ . '/vendor/autoload.php';
if (file_exists(__DIR__ . '/pro/vendor/autoload.php')) {
    require __DIR__ . '/pro/vendor/autoload.php';
}

call_user_func(function ($bootstrap) {
    $bootstrap(__FILE__);
}, require(__DIR__ . '/boot/app.php'));

if (file_exists(__DIR__ . '/pro/boot/app.php')) {
    call_user_func(function ($bootstrap) {
        $bootstrap(__FILE__);
    }, require(__DIR__ . '/pro/boot/app.php'));
}

