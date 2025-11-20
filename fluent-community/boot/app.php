<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly

use FluentCommunity\App\Hooks\Handlers\ActivationHandler;
use FluentCommunity\App\Hooks\Handlers\DeactivationHandler;
use FluentCommunity\Framework\Foundation\Application;

return function ($file) {

    $app = new Application($file);

    register_activation_hook($file, function () use ($app) {
        ($app->make(ActivationHandler::class))->handle();

        if (function_exists('\as_next_scheduled_action')) {
            if (!\as_next_scheduled_action('fluent_community_scheduled_hour_jobs')) {
                \as_schedule_recurring_action(time(), 3600, 'fluent_community_scheduled_hour_jobs', [], 'fluent-community', true);
            }

            if (!\as_next_scheduled_action('fluent_community_daily_jobs')) {
                \as_schedule_recurring_action(time(), 86400, 'fluent_community_daily_jobs', [], 'fluent-community', true);
            }
        }

    });

    register_deactivation_hook($file, function () use ($app) {
        ($app->make(DeactivationHandler::class))->handle();
    });

    require_once FLUENT_COMMUNITY_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
    require_once FLUENT_COMMUNITY_PLUGIN_DIR . 'app/Functions/helpers.php';

    if (file_exists(FLUENT_COMMUNITY_PLUGIN_DIR . 'Modules/modules_init.php')) {
        require_once FLUENT_COMMUNITY_PLUGIN_DIR . 'Modules/modules_init.php';
    }

    add_action('plugins_loaded', function () use ($app) {
        do_action('fluent_community/portal_loaded', $app);

        add_action('init', function () use ($app) {
            do_action('fluent_community/on_wp_init', $app);
        });
    });

    add_action('fluent_community/portal_render_for_user', function () {
        if (!\FluentCommunity\App\Services\Helper::isSiteAdmin()) {
            return;
        }

        if (!\as_next_scheduled_action('fluent_community_scheduled_hour_jobs')) {
            as_schedule_recurring_action(time(), 3600, 'fluent_community_scheduled_hour_jobs', [], 'fluent-community');
        }

        if (!\as_next_scheduled_action('fluent_community_daily_jobs')) {
            \as_schedule_recurring_action(time(), 86400, 'fluent_community_daily_jobs', [], 'fluent-community');
        }
        /*
         * We will remove this after final release
         */
        $currentDBVersion = get_option('fluent_community_db_version');
        if (!$currentDBVersion || version_compare($currentDBVersion, FLUENT_COMMUNITY_DB_VERSION, '<')) {
            update_option('fluent_community_db_version', FLUENT_COMMUNITY_DB_VERSION, 'no');
            \FluentCommunity\Database\DBMigrator::run();
        }


        if (defined('FLUENT_COMMUNITY_PRO_VERSION')) {
            add_filter('fluent_community/portal_notices', function ($notices) {
                if (FLUENT_COMMUNITY_MIN_PRO_VERSION !== FLUENT_COMMUNITY_PRO_VERSION && version_compare(FLUENT_COMMUNITY_MIN_PRO_VERSION, FLUENT_COMMUNITY_PRO_VERSION, '>')) {
                    $updateUrl = admin_url('plugins.php?s=fluent-community&plugin_status=all&fluent-fluent-community-pro-check-update=' . time());
                    $notices[] = '<div style="padding: 10px; background-color: var(--fcom-primary-bg, white);" class="error"><b>' . esc_html__('Heads UP:', 'fluent-community') . ' </b> ' . esc_html__('FluentCommunityPro Plugin needs to be updated to the latest version.', 'fluent-community') . ' <a href="' . esc_url($updateUrl) . '">' . esc_html__('Click here to update', 'fluent-community') . '</a></div>';
                }
                return $notices;
            });
        }

    });
};
