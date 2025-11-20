<?php

namespace FluentCommunity\App\Hooks\Handlers;

use FluentCommunity\Framework\Foundation\Application;

class DeactivationHandler
{
    protected $app = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handle()
    {
        if (function_exists('\as_unschedule_all_actions')) {
            \as_unschedule_all_actions('fluent_community_scheduled_hour_jobs');
            \as_unschedule_all_actions('fluent_community_daily_jobs');
        }

        if(function_exists('wp_cache_flush_group') && wp_cache_supports( 'flush_group' )) {
            wp_cache_flush_group('fluent_community');
        }
    }
}
