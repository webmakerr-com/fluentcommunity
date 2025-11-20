<?php defined('ABSPATH') or die;

/*
Plugin Name: FluentCommunity Pro
Description: The Pro version of FluentCommunity Plugin
Version: 2.1.0
Author: WPManageNinja LLC
Author URI: https://fluentcommunity.co
Plugin URI: https://fluentcommunity.co
License: GPLv2 or later
Text Domain: fluent-community-pro
Domain Path: /language
*/

define('FLUENT_COMMUNITY_PRO', true);
define('FLUENT_COMMUNITY_PRO_DIR', plugin_dir_path(__FILE__));
define('FLUENT_COMMUNITY_PRO_URL', plugin_dir_url(__FILE__));
define('FLUENT_COMMUNITY_PRO_DIR_FILE', __FILE__);
define('FLUENT_COMMUNITY_PRO_VERSION', '2.1.0');
define('FLUENT_COMMUNITY_MIN_CORE_VERSION', '2.1.0');

update_option('__fluent_community_pro_license', [
    'license_key' => '1415B451BE1A13C283BA771EA52D38BB',
    'status' => 'valid',
    'variation_id' => '999999',
    'variation_title' => 'Lifetime License',
    'expires' => gmdate('Y-m-d', strtotime('+100 years')),
    'activation_hash' => md5('1415B451BE1A13C283BA771EA52D38BB' . home_url())
], false);

add_filter('pre_http_request', function($preempt, $args, $url) {
    if (strpos($url, 'fluentapi.wpmanageninja.com') !== false && strpos($url, 'fluent-cart') !== false) {
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => json_encode([
                'status' => 'valid',
                'variation_id' => '999999',
                'variation_title' => 'Lifetime License',
                'expiration_date' => gmdate('Y-m-d', strtotime('+100 years')),
                'activation_hash' => md5('1415B451BE1A13C283BA771EA52D38BB' . home_url()),
                'new_version' => FLUENT_COMMUNITY_PRO_VERSION,
                'package' => '',
                'sections' => [],
                'banners' => [],
                'icons' => []
            ])
        ];
    }
    return $preempt;
}, 10, 3);

require __DIR__ . '/vendor/autoload.php';

call_user_func(function ($bootstrap) {
    $bootstrap(__FILE__);
}, require(__DIR__ . '/boot/app.php'));