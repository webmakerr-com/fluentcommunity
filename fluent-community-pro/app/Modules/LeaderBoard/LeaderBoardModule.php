<?php

namespace FluentCommunityPro\App\Modules\LeaderBoard;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\NotificationSubscriber;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\Framework\Foundation\Application;
use FluentCommunityPro\App\Modules\LeaderBoard\Services\LeaderBoardHelper;
use FluentCommunityPro\App\Modules\LeaderBoard\UserLevelUpgradedTrigger;

class LeaderBoardModule
{
    public function register(Application $app, $features)
    {
        /*
         * register the routes
         */
        $app->router->group(function ($router) {
            require_once __DIR__ . '/Http/leaderboard_api.php';
        });

        if (!isset($features['leader_board_module']) || $features['leader_board_module'] !== 'yes') {
            return;
        }

        add_action('fluent_community/portal_render_for_user', function ($xprofile) {
            LeaderBoardHelper::maybeRenewPoints($xprofile);
        });

        add_filter('fluent_community/portal_vars', function ($vars) {
            $vars['features']['leaderboard'] = true;
            $vars['leaderboard_levels'] = LeaderBoardHelper::getDynamicLevels();
            return $vars;
        });

        /**
         * Daily User Point Syncs
         */
        add_action('fluent_community_daily_jobs', [$this, 'syncUserPoints']);
        add_action('fluent_community_sync_user_points', [$this, 'syncUserPoints']);

        // Check user's got a level upgrade
        add_action('fluent_community/user_points_updated', [$this, 'maybeLevelUpgraded'], 10, 2);

        // init fluentCRM automation trigger
        if (defined('FLUENTCRM')) {
            new UserLevelUpgradedTrigger();
        }

    }

    public function syncUserPoints()
    {
        $lastSyncedDate = Utility::getOption('last_leaderboard_synced_date');

        if ($lastSyncedDate && $lastSyncedDate == gmdate('Y-m-d', current_time('timestamp'))) {
            return; // already synced
        }
        $lastSyncUserId = Utility::getOption('last_leaderboard_synced_user_id');

        if (!$lastSyncedDate) {
            $lastSyncedDate = gmdate('Y-m-d', current_time('timestamp'));
        }

        $lastSyncedDate = gmdate('Y-m-d 00:00:01', strtotime($lastSyncedDate));

        $userIds = NotificationSubscriber::where('created_at', '>=', $lastSyncedDate)
            ->select(['user_id'])
            ->limit(30)
            ->distinct('user_id')
            ->orderBy('user_id', 'ASC')
            ->when($lastSyncUserId, function ($q) use ($lastSyncUserId) {
                $q->where('user_id', '>', $lastSyncUserId);
            })
            ->pluck('user_id')->toArray();

        if (empty($userIds)) {
            Utility::updateOption('last_leaderboard_synced_user_id', 0);
            Utility::updateOption('last_leaderboard_synced_date', gmdate('Y-m-d', current_time('timestamp')));
            return;
        }

        $xprofiles = XProfile::whereIn('user_id', $userIds)->get();

        foreach ($xprofiles as $xprofile) {
            LeaderBoardHelper::maybeRenewPoints($xprofile);
            Utility::updateOption('last_leaderboard_synced_user_id', $xprofile->user_id);
        }

        if (microtime(true) - FLUENT_COMMUNITY_START_TIME < 45) {
            return $this->syncUserPoints();
        }

        as_schedule_single_action(time(), 'fluent_community_sync_user_points', null, 'fluent-community');

        return true;
    }

    public function maybeLevelUpgraded($xprofile, $oldPoints)
    {
        $oldLevel = LeaderBoardHelper::getLevelByPoint($oldPoints);
        if ($oldLevel['max_points'] >= $xprofile->total_points) {
            return;
        }

        $newLevel = LeaderBoardHelper::getLevelByPoint($xprofile->total_points);

        if ($oldLevel['slug'] != $newLevel['slug']) {
            do_action('fluent_community/user_level_upgraded', $xprofile, $newLevel, $oldLevel);
        }
    }
}
