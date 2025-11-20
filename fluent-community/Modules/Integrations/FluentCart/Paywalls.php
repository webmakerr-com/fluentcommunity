<?php

namespace FluentCommunity\Modules\Integrations\FluentCart;

use FluentCart\App\Models\ProductMeta;
use FluentCommunity\Framework\Support\Arr;

class Paywalls
{
    public function register($app)
    {
        $app->router->group(function ($router) {
            require_once __DIR__ . '/Http/cart_api.php';
        });

        add_filter('fluent_community/portal_vars', [$this, 'addPortalVars'], 10, 1);
        add_action('fluent_community/paywall_added', [$this, 'maybeCreateIntegration'], 10, 2);
        add_action('fluent_community/paywall_removed', [$this, 'maybeRemoveIntegration'], 10, 3);
        add_filter('fluent_community/lockscreen_fields', [$this, 'maybeAddPaywallField'], 10, 2);
        add_filter('fluent_community/lockscreen_formatted_field', [$this, 'maybeFormatPaywallField'], 10, 2);
    }

    public function addPortalVars($vars)
    {
        $vars['features']['has_fluentcart'] = true;
        return $vars;
    }

    public function maybeCreateIntegration($space, $productId)
    {
        $communityIntegrations = ProductMeta::query()
            ->where('object_id', $productId)
            ->where('object_type', 'product_integration')
            ->where('meta_key', 'fluent_community')
            ->get();

        $checkIds = $space->type == 'course' ? 'course_ids' : 'space_ids';

        foreach ($communityIntegrations as $integration) {
            $data = $integration->meta_value;
            $isEnabled = Arr::get($data, 'enabled', 'no');
            $eventTrigger = Arr::get($data, 'event_trigger');
            if ($isEnabled != 'yes' || !$eventTrigger || !in_array('order_paid_done', $eventTrigger)) {
                continue;
            }

            $existingIds = Arr::get($data, $checkIds, []);
            if (in_array($space->id, $existingIds)) {
                return;
            }

            $data[$checkIds] = array_merge($existingIds, [(string)$space->id]);
            $integration->meta_value = $data;
            $integration->save();

            do_action('fluent_community/product_integration_feed_updated', $integration->id, $space->id);

            return;
        }

        $integrationFeedData = [
            'enabled'                => 'yes',
            'name'                   => 'FluentCommunity Integration Feed',
            'space_ids'              => [],
            'remove_space_ids'       => [],
            'course_ids'             => [],
            'remove_course_ids'      => [],
            'event_trigger'          => ['order_paid_done'],
            'tag_ids_selection_type' => 'simple',
            'mark_as_verified'       => 'no',
            'watch_on_access_revoke' => 'yes'
        ];

        $integrationFeedData[$checkIds] = [(string) $space->id];

        $communityIntegration = ProductMeta::create([
            'object_id'   => $productId,
            'object_type' => 'product_integration',
            'meta_key'    => 'fluent_community', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value'  => $integrationFeedData // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        ]);

        do_action('fluent_community/product_integration_feed_created', $communityIntegration->id ?? null, $productId);
    }

    public function maybeRemoveIntegration($space, $productId, $requestData)
    {
        $revokeAccess = Arr::get($requestData, 'revoke_access', 'no');
        if ($revokeAccess !== 'yes') {
            return;
        }

        $communityIntegrations = ProductMeta::query()
            ->where('object_id', $productId)
            ->where('object_type', 'product_integration')
            ->where('meta_key', 'fluent_community')
            ->get();

        $checkIds = $space->type == 'course' ? 'course_ids' : 'space_ids';

        foreach ($communityIntegrations as $integration) {
            $data = $integration->meta_value;
            $isEnabled = Arr::get($data, 'enabled', 'no');
            $eventTrigger = Arr::get($data, 'event_trigger');
            if ($isEnabled != 'yes' || !$eventTrigger || !in_array('order_paid_done', $eventTrigger)) {
                continue;
            }
            
            $existingIds = Arr::get($data, $checkIds, []);
            if (!in_array($space->id, $existingIds)) {
                continue;
            }

            $data[$checkIds] = array_diff($existingIds, [(string)$space->id]);
            $integration->meta_value = $data;
            $integration->save();

            do_action('fluent_community/product_integration_feed_updated', $integration->id, $space->id);

            return;
        }
    }

    public function maybeAddPaywallField($fields, $space)
    {
        $paywallField = [
            'hidden'            => true,
            'type'              => 'paywall',
            'label'             => __('Paywalls', 'fluent-community'),
            'name'              => 'paywall',
            'paywalls'          => [],
            'text_color'        => '#FFFFFF',
            'button_text'       => __('Join', 'fluent-community'),
            'button_color'      => '#2B2E33',
            'background_color'  => '',
            'button_text_color' => '#FFFFFF',
            'show_description'  => 'no'
        ];

        $existingNames = array_column($fields, 'name');

        if ($space->hasPaywallIntegration()) {
            if (!in_array('paywall', $existingNames)) {
                $actionIndex = array_search('action', $existingNames);
                if ($actionIndex !== false) {
                    array_splice($fields, $actionIndex, 0, [$paywallField]);
                } else {
                    $fields[] = $paywallField;
                }
            }
        } else {
            if (in_array('paywall', $existingNames)) {
                $fields = array_values(array_filter($fields, function($field) {
                    return $field['name'] != 'paywall';
                }));
            }
        }

        return $fields;
    }

    public function maybeFormatPaywallField($field, $value)
    {
        if ($value['type'] != 'paywall') {
            return $value;
        }

        $value['paywalls'] = array_values(array_filter(
            array_map(function($paywall) {
                return sanitize_text_field($paywall);
            }, $value['paywalls'] ?? []),
            function($paywall) {
                return !empty($paywall) && is_numeric($paywall);
            }
        ));

        $value['show_description'] = Arr::get($value, 'show_description') == 'yes' ? 'yes' : 'no';

        return $value;
    }
}
