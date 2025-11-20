<?php

namespace FluentCommunityPro\App\Modules\Followers;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\App\Models\XProfile;

class FollowerHelper
{
    protected static function canView($userId, $permission)
    {
        if ($permission == 'public') {
            return true;
        }

        $currentUserId = get_current_user_id();

        $isOwn = $userId == $currentUserId;
        $isAdmin = Helper::isSiteAdmin($currentUserId);

        if ($isOwn || $isAdmin) {
            return true;
        }

        if ($permission == 'only_me') {
            return $isOwn;
        }

        return XProfile::where('user_id', $currentUserId)->where('status', 'active')->exists();
    }

    public static function canViewFollowers($userId, $settings = [])
    {
        if (!$settings) {
            $settings = self::getSettings();
        }

        $followerPermission = Arr::get($settings, 'who_can_see_followers', 'members');

        return self::canView($userId, $followerPermission);
    }

    public static function canViewFollowings($userId, $settings = [])
    {
        if (!$settings) {
            $settings = self::getSettings();
        }

        $followingPermission = Arr::get($settings, 'who_can_see_followings', 'members');

        return self::canView($userId, $followingPermission);
    }

    public static function getSettings()
    {
        $defaults = [
            'is_enabled'             => Helper::isFeatureEnabled('followers_module') ? 'yes' : 'no',
            'who_can_see_followers'  => 'members',
            'who_can_see_followings' => 'members'
        ];

        $settings = Utility::getOption('followers_settings', []);
        $settings = wp_parse_args($settings, $defaults);

        return $settings;
    }

    public static function updateSettings($settings)
    {
        $prevSettings = self::getSettings();
        $settings = Arr::only($settings, [
            'is_enabled',
            'who_can_see_followers',
            'who_can_see_followings'
        ]);

        $settings = wp_parse_args($settings, $prevSettings);

        $settings['who_can_see_followers'] = in_array($settings['who_can_see_followers'], ['public', 'members', 'only_me']) ? $settings['who_can_see_followers'] : 'members';
        $settings['who_can_see_followings'] = in_array($settings['who_can_see_followings'], ['public', 'members', 'only_me']) ? $settings['who_can_see_followings'] : 'members';
        $settings['is_enabled'] = $settings['is_enabled'] === 'yes' ? 'yes' : 'no';

        if ($prevSettings['is_enabled'] !== $settings['is_enabled'] && $settings['is_enabled'] === 'yes') {
            // maybe we have add the database table
            self::maybeCreateTable();
        }

        $featureConfig = Utility::getFeaturesConfig();
        $featureConfig['followers_module'] = $settings['is_enabled'];
        Utility::updateOption('fluent_community_features', $featureConfig);

        Utility::updateOption('followers_settings', $settings);

        return $settings;
    }

    public static function getCurrentUserFollows($userIds)
    {
        global $wpdb;

        $userId = get_current_user_id();
        $userIds = array_map('intval', $userIds);
        $idsString = implode(',', $userIds);

        $results = [];
        if ($idsString) {
            $table = $wpdb->prefix . 'fcom_followers';
            $results = $wpdb->get_results(
                "SELECT followed_id, level FROM {$table} WHERE follower_id = {$userId} AND followed_id IN ({$idsString})",
                ARRAY_A
            );
        }

        if (empty($results)) {
            return [];
        }

        return array_column($results, 'level', 'followed_id');;
    }

    private static function maybeCreateTable()
    {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'fcom_followers';
        $indexPrefix = $wpdb->prefix . 'fcom_fr_';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `follower_id` BIGINT(20) UNSIGNED NOT NULL,
                `followed_id` BIGINT(20) UNSIGNED NOT NULL,
                `level` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0-blocked, 1-default, 2-email-notify',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX {$indexPrefix}_follower (follower_id),
                INDEX {$indexPrefix}_followed (followed_id),
                UNIQUE INDEX {$indexPrefix}_follow_pair (follower_id, followed_id)
            ) $charsetCollate;";
            dbDelta($sql);
        }
    }
}
