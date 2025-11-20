<?php

/**
 * All registered filter's handlers should be in app\Hooks\Handlers,
 * addFilter is similar to add_filter and addCustomFlter is just a
 * wrapper over add_filter which will add a prefix to the hook name
 * using the plugin slug to make it unique in all wordpress plugins,
 * ex: $app->addCustomFilter('foo', ['FooHandler', 'handleFoo']) is
 * equivalent to add_filter('slug-foo', ['FooHandler', 'handleFoo']).
 */

/**
 * @var $app FluentCommunity\Framework\Foundation\Application;
 */

add_filter('fluent_community/portal_vars', function ($vars) {
    $vars['pro'] = true;
    return $vars;
});


add_filter('fluent_community/features/analytics', function ($setting) {
    $setting['status'] = 'yes';
    return $setting;
});


add_filter('fluent_community/storage_settings_response', function ($response) {
    $response['s3_locations'] = [
        's3.amazonaws.com'                   => 'Global',
        's3-us-east-1.amazonaws.com'         => 'US East (N. Virginia)',
        's3-us-east-2.amazonaws.com'         => 'US East (Ohio)',
        's3-us-west-1.amazonaws.com'         => 'US West (N. California)',
        's3-us-west-2.amazonaws.com'         => 'US West (Oregon)',
        's3-ca-central-1.amazonaws.com'      => 'Canada (Central)',
        's3-eu-west-1.amazonaws.com'         => 'Europe (Ireland)',
        's3-eu-west-2.amazonaws.com'         => 'Europe (London)',
        's3-eu-west-3.amazonaws.com'         => 'Europe (Paris)',
        's3-eu-central-1.amazonaws.com'      => 'Europe (Frankfurt)',
        's3-eu-north-1.amazonaws.com'        => 'Europe (Stockholm)',
        's3-ap-south-1.amazonaws.com'        => 'Asia Pacific (Mumbai)',
        's3-ap-southeast-1.amazonaws.com'    => 'Asia Pacific (Singapore)',
        's3-ap-southeast-2.amazonaws.com'    => 'Asia Pacific (Sydney)',
        's3-ap-northeast-1.amazonaws.com'    => 'Asia Pacific (Tokyo)',
        's3-ap-northeast-2.amazonaws.com'    => 'Asia Pacific (Seoul)',
        's3-ap-northeast-3.amazonaws.com'    => 'Asia Pacific (Osaka)',
        's3-sa-east-1.amazonaws.com'         => 'South America (São Paulo)',
        's3-cn-north-1.amazonaws.com.cn'     => 'China (Beijing)',
        's3-cn-northwest-1.amazonaws.com.cn' => 'China (Ningxia)',
        's3-us-gov-west-1.amazonaws.com'     => 'AWS GovCloud (US-West)',
        's3-us-gov-east-1.amazonaws.com'     => 'AWS GovCloud (US-East)'
    ];


    $response['bunny_locations'] = [
        'storage.bunnycdn.com' => 'Falkenstein, DE',
        'uk.storage.bunnycdn.com' => 'London, UK',
        'ny.storage.bunnycdn.com' => 'New York, US',
        'la.storage.bunnycdn.com' => 'Los Angeles, US',
        'sg.storage.bunnycdn.com' => 'Singapore, SG',
        'se.storage.bunnycdn.com' => 'Stockholm, SE',
        'br.storage.bunnycdn.com' => 'São Paulo, BR',
        'jh.storage.bunnycdn.com' => 'Johannesburg, SA',
        'syd.storage.bunnycdn.com' => 'Sydney, SYD',
    ];

    return $response;
});
