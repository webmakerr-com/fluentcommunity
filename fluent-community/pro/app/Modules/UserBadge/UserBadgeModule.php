<?php

namespace FluentCommunityPro\App\Modules\UserBadge;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\Framework\Foundation\Application;
use FluentCommunityPro\App\Services\Integrations\FluentCRM\AddBadgeAction;
use FluentCommunityPro\App\Services\Integrations\FluentCRM\RemoveBadgeAction;

class UserBadgeModule
{
    public function register(Application $app, $features = [])
    {
        /*
         * register the routes
         */
        $app->router->group(function ($router) {
            $router->prefix('admin/user-badges')->namespace('\FluentCommunityPro\App\Modules\UserBadge\Controllers')->withPolicy(\FluentCommunity\App\Http\Policies\AdminPolicy::class)->group(function ($router) {
                $router->get('/', 'UserBadgeController@getBadges');
                $router->post('/', 'UserBadgeController@saveBadges');
            });
        });

        if (!isset($features['user_badge']) || $features['user_badge'] !== 'yes') {
            return;
        }

        if (defined('FLUENTCRM')) {
            // Add badge Action
            new AddBadgeAction();
            new RemoveBadgeAction();
        }

        add_filter('fluent_community/portal_vars', function ($vars) {
            $badges = Utility::getOption('user_badges', []);
            if ($badges) {
                $vars['user_badges'] = $badges;
            }
            return $vars;
        });
    }
}
