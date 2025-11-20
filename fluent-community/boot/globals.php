<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<?php

/**
 ***** DO NOT CALL ANY FUNCTIONS DIRECTLY FROM THIS FILE ******
 *
 * This file will be loaded even before the framework is loaded
 * so the $app is not available here, only declare functions here.
 */


/**
 * Get the fluent coommunity application instance
 *
 * @param string $module The binding/key name for a component.
 * @param array $parameters constructor dependencies if any.
 * @return \FluentCommunity\Framework\Foundation\Application|mixed
 */
function fluentCommunityApp($module = null, $parameters = [])
{
    return \FluentCommunity\App\App::make($module, $parameters);
}

function fluentCommunitySanitizeArray($array)
{
    return array_map(function ($value) {
        return is_array($value) ? fluentCommunitySanitizeArray($value) : sanitize_text_field($value);
    }, $array);
}
