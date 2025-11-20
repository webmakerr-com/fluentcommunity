<?php

namespace FluentCommunity\App\Hooks\Handlers;

use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Foundation\Application;
use FluentCommunity\Database\DBMigrator;
use FluentCommunity\App\Models\SpaceGroup;

class ActivationHandler
{
    protected $app = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle($network_wide = false)
    {
        DBMigrator::run($network_wide);
        update_option('fluent_community_db_version', FLUENT_COMMUNITY_DB_VERSION, 'no');

        // We may need to register the action schedulers here
        if (!\as_next_scheduled_action('fluent_community_scheduled_hour_jobs')) {
            \as_schedule_recurring_action(time(), 3600, 'fluent_community_scheduled_hour_jobs', [], 'fluent-community', true);
        }

        if (!\as_next_scheduled_action('fluent_community_daily_jobs')) {
            \as_schedule_recurring_action(time(), 86400, 'fluent_community_daily_jobs', [], 'fluent-community', true);
        }

        $slug = Helper::getPortalSlug();
        add_rewrite_rule('^' . $slug . '/?$', 'index.php?fcom_route=portal_home', 'top'); // For /hooks
        add_rewrite_rule('^' . $slug . '/(.+)/?', 'index.php?fcom_route=$matches[1]', 'top');
        // flush rewrite rules
        flush_rewrite_rules(true);

        // remove the default WordPress templates caching
        wp_cache_delete( 'wp_get_global_stylesheet', 'theme_json' );
        wp_cache_delete( 'wp_get_theme_data_template_parts', 'theme_json' );
    }

    public function maybeCreateDefaultSpaceGroup()
    {
        $exist = SpaceGroup::query()->exists();
        if ($exist) {
            return false;
        }

        $defaultData = [
            'title'       => 'Get Started',
            'slug'        => 'get-started',
            'serial'      => 1,
            'description' => 'This is the default menu group. Please update the title and description.',
            'settings'    => [
                'hide_members'       => 'no',
                'always_show_spaces' => 'yes'
            ]
        ];

        return SpaceGroup::create($defaultData);
    }
}
