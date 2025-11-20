<?php

namespace FluentCommunityPro\App\Modules\LeaderBoard\Services;


use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\Comment;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\Framework\Support\Arr;

class LeaderBoardHelper
{
    public static function getLeaderBoard($lastDays = 0, $limit = 10, $cached = false)
    {
        $cacheKey = 'leader_board_cache_' . $lastDays . '_' . $limit;
        if ($cached) {
            $leaderBoardCache = Utility::getOption($cacheKey);
            if ($leaderBoardCache && !empty($leaderBoardCache['data'])) {
                $createdTimeStamp = Arr::get($leaderBoardCache, 'created_at', 0);
                $currentTimeStamp = current_time('timestamp');
                if ($currentTimeStamp - $createdTimeStamp < 300) { // we are caching for 5 minutes

                    // it's temp. We will release as soon as possible
                    // @todo: remove it
                    $data = $leaderBoardCache['data'] ?? [];
                    // convert the entries into array
                    foreach ($data as $index => $entry) {
                        if (is_object($entry)) {
                            $data[$index] = (array)$entry;
                        }
                    }
                    
                    return $data;
                }
            }
        }

        global $wpdb;
        $dateRange = false;
        if ($lastDays) {
            $currentTimeStamp = current_time('timestamp');
            $dateRange = [gmdate('Y-m-d 00:00:00', ($currentTimeStamp - $lastDays * DAY_IN_SECONDS)), gmdate('Y-m-d 23:59:59', $currentTimeStamp)];
        }

        if ($dateRange) {
            $whereCreatedAt = $wpdb->prepare(" WHERE  r.created_at BETWEEN %s AND %s ", $dateRange[0], $dateRange[1]);
        } else {
            $whereCreatedAt = '';
        }

        $excludedUserIds = self::getExcludedUserIds();

        $whereExcludedUsers = '';
        if (!empty($excludedUserIds)) {
            $whereExcludedUsers = "AND u.user_id NOT IN (" . implode(',', array_map('intval', $excludedUserIds)) . ")";
        }

        $prefix = $wpdb->prefix;
        $query = "
        SELECT 
            u.user_id,
            COALESCE(p.post_likes, 0) + COALESCE(c.comment_likes, 0) AS total_points
        FROM 
            (
                SELECT DISTINCT user_id 
                FROM {$prefix}fcom_posts
                UNION
                SELECT DISTINCT user_id 
                FROM {$prefix}fcom_post_comments
            ) u
        LEFT JOIN 
            (
                SELECT 
                    p.user_id, 
                    COUNT(r.id) AS post_likes
                FROM 
                    {$prefix}fcom_posts p
                LEFT JOIN 
                    {$prefix}fcom_post_reactions r 
                    ON p.id = r.object_id AND r.object_type = 'feed' AND r.type = 'like'
                {$whereCreatedAt}
                GROUP BY 
                    p.user_id
            ) p ON u.user_id = p.user_id
        LEFT JOIN 
            (
                SELECT 
                    c.user_id, 
                    COUNT(r.id) AS comment_likes
                FROM 
                    {$prefix}fcom_post_comments c
                LEFT JOIN 
                    {$prefix}fcom_post_reactions r 
                    ON c.id = r.object_id AND r.object_type = 'comment' AND r.type = 'like'
                {$whereCreatedAt}
                GROUP BY 
                    c.user_id
            ) c ON u.user_id = c.user_id
        WHERE
            1=1
            {$whereExcludedUsers}
        ORDER BY 
            total_points DESC
        LIMIT 10;
    ";

        $results = $wpdb->get_results($query, ARRAY_A);

        Utility::updateOption($cacheKey, ['data' => $results, 'created_at' => current_time('timestamp')]);

        if (!$results) {
            return [];
        }

        return $results;
    }

    public static function getLevelSlugByPoint($currentPoint)
    {
        $level = self::getLevelByPoint($currentPoint);
        return $level['slug'];
    }

    public static function isLevelChanged($oldPoints, $newPoints)
    {
        $oldLevel = self::getLevelByPoint($oldPoints);
        $newLevel = self::getLevelByPoint($newPoints);
        $isChanghed = $oldLevel['slug'] != $newLevel['slug'];

        if ($isChanghed) {
            return $newLevel;
        }

        return null;
    }

    public static function getLevelByPoint($currentPoint)
    {
        $levels = self::getDynamicLevels();

        if (!$currentPoint) {
            // return the first level
            return reset($levels);
        }

        foreach ($levels as $level) {
            if ($currentPoint >= $level['min_points'] && $currentPoint <= $level['max_points']) {
                return $level;
            }
        }

        // let's send the last level
        return end($levels);
    }

    public static function recalculateUserPoints($userId)
    {
        // SUM of all the points of the Comment Model
        $commentPoints = Comment::where('user_id', $userId)
            ->sum('reactions_count');

        $postsPoints = \FluentCommunity\App\Models\Feed::where('user_id', $userId)
            ->sum('reactions_count');

        return $commentPoints + $postsPoints;
    }

    public static function maybeRenewPoints(XProfile $xprofile, $forced = false)
    {
        if ($forced) {
            Utility::forgetCache('last_point_calculated_' . $xprofile->user_id);
        }

        return Utility::getFromCache('last_point_calculated_' . $xprofile->user_id, function () use ($xprofile) {
            $totalPoints = LeaderBoardHelper::recalculateUserPoints($xprofile->user_id);
            if ($totalPoints && $totalPoints != $xprofile->total_points) {
                $oldPoints = $xprofile->total_points;
                $xprofile->total_points = $totalPoints;
                $xprofile->save();
                do_action('fluent_community/user_points_updated', $xprofile, $oldPoints);
            }
            return $xprofile->total_points;
        }, 3600); // calculate points every hour
    }

    public static function getDynamicLevels()
    {
        static $prevSettings;

        if ($prevSettings) {
            return $prevSettings;
        }

        $defaults = [
            'level_1' => [
                'title'      => __('Space Initiate', 'fluent-community-pro'),
                'tagline'    => __('Taking the first steps into our vibrant world', 'fluent-community-pro'),
                'slug'       => 'level_1',
                'level'      => 1,
                'min_points' => 0,
                'max_points' => 4
            ],
            'level_2' => [
                'title'      => __('Space Pathfinder', 'fluent-community-pro'),
                'tagline'    => __('Uncovering the secrets of community mastery', 'fluent-community-pro'),
                'slug'       => 'level_2',
                'level'      => 2,
                'min_points' => 5,
                'max_points' => 19
            ],
            'level_3' => [
                'title'      => __('Space Enthusiast', 'fluent-community-pro'),
                'tagline'    => __('Igniting passion and fostering connections', 'fluent-community-pro'),
                'slug'       => 'level_3',
                'level'      => 3,
                'min_points' => 20,
                'max_points' => 64
            ],
            'level_4' => [
                'title'      => __('Space Contributor', 'fluent-community-pro'),
                'tagline'    => __('Weaving the fabric of shared knowledge', 'fluent-community-pro'),
                'slug'       => 'level_4',
                'level'      => 4,
                'min_points' => 65,
                'max_points' => 154
            ],
            'level_5' => [
                'title'      => __('Space Advocate', 'fluent-community-pro'),
                'tagline'    => __('Championing the cause with unwavering spirit', 'fluent-community-pro'),
                'slug'       => 'level_5',
                'level'      => 5,
                'min_points' => 155,
                'max_points' => 499
            ],
            'level_6' => [
                'title'      => __('Space Virtuoso', 'fluent-community-pro'),
                'tagline'    => __('Exemplifying mastery and skill', 'fluent-community-pro'),
                'slug'       => 'level_6',
                'level'      => 6,
                'min_points' => 500,
                'max_points' => 1999
            ],
            'level_7' => [
                'title'      => __('Space Sage', 'fluent-community-pro'),
                'tagline'    => __('Imparting wisdom and guiding the way', 'fluent-community-pro'),
                'slug'       => 'level_7',
                'level'      => 7,
                'min_points' => 2000,
                'max_points' => 7999
            ],
            'level_8' => [
                'title'      => __('Space Hero', 'fluent-community-pro'),
                'tagline'    => __('Leading with bold innovation and vision', 'fluent-community-pro'),
                'slug'       => 'level_8',
                'level'      => 8,
                'min_points' => 8000,
                'max_points' => 24999
            ],
            'level_9' => [
                'title'      => __('Space Legend', 'fluent-community-pro'),
                'tagline'    => __('Eternally inspiring excellence and unity', 'fluent-community-pro'),
                'slug'       => 'level_9',
                'level'      => 9,
                'min_points' => 25000,
                'max_points' => 10000000000000
            ],
        ];

        $prevSettings = Utility::getOption('fcom_leaderboard_levels', []);

        if (!$prevSettings) {
            return $defaults;
        }

        $prevSettings = wp_parse_args($prevSettings, $defaults);

        return $prevSettings;
    }

    public static function getExcludedUserIds()
    {
        return Utility::getOption('fcom_leaderboard_excluded_user_ids', []);
    }

}
