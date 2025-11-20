<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * @var $app FluentCommunity\Framework\Foundation\Application
 */

$app->addFilter('fluent_community/auth/signup_fields', function ($fields) {
    $authSettings = \FluentCommunity\App\Services\AuthenticationService::getAuthSettings();
    if (!empty($authSettings['signup']['form']['fields']['terms'])) {
        $fields['terms'] = $authSettings['signup']['form']['fields']['terms'];
    }
    return $fields;
}, 10, 1);

$app->addFilter('fluent_community/lockscreen_fields', function ($fields) {
    if (!defined('FLUENTCART_VERSION')) {
        $existingNames = array_column($fields, 'name');
        if (in_array('paywall', $existingNames)) {
            $fields = array_values(array_filter($fields, function($field) {
                return $field['name'] != 'paywall';
            }));
        }
    }
    return $fields;
}, 10, 1);