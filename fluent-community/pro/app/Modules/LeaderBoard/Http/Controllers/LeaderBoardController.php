<?php

namespace FluentCommunityPro\App\Modules\LeaderBoard\Http\Controllers;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Http\Controllers\Controller;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\App\Services\ProfileHelper;
use FluentCommunity\Framework\Http\Request\Request;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\App\Models\User;
use FluentCommunityPro\App\Modules\LeaderBoard\Services\LeaderBoardHelper;

class LeaderBoardController extends Controller
{
    public function getLeaders(Request $request)
    {
        if (!Utility::canViewLeaderboardMembers()) {
            return $this->sendError([
                'message' => __('You don\'t have permission to view the leaderboard members', 'fluent-community-pro')
            ]);
        }

        $last7Days = LeaderBoardHelper::getLeaderBoard(7, 10, true);
        $last30Days = LeaderBoardHelper::getLeaderBoard(30, 10, true);
        $allTime = LeaderBoardHelper::getLeaderBoard(0, 10, true);

        $userIds = [];

        foreach ($last7Days as $day) {
            $day = (array)$day;
            $userIds[$day['user_id']] = $day['user_id'];
        }

        foreach ($last30Days as $day) {
            $day = (array)$day;
            $userIds[$day['user_id']] = $day['user_id'];
        }

        foreach ($allTime as $day) {
            $day = (array)$day;
            $userIds[$day['user_id']] = $day['user_id'];
        }

        $userIds = array_values($userIds);

        $xProfiles = XProfile::whereIn('user_id', $userIds)
            ->where('status', 'active')
            ->select(ProfileHelper::getXProfilePublicFields())
            ->get();

        $xProfiles = $xProfiles->keyBy('user_id');

        foreach ($last7Days as $index => $day) {
            $day = (array)$day;
            if (isset($xProfiles[$day['user_id']])) {
                $last7Days[$index]['xprofile'] = $xProfiles[$day['user_id']];
            } else {
                unset($last7Days[$index]);
            }
        }

        foreach ($last30Days as $index => $day) {
            $day = (array)$day;
            if (isset($xProfiles[$day['user_id']])) {
                $last30Days[$index]['xprofile'] = $xProfiles[$day['user_id']];
            } else {
                unset($last30Days[$index]);
            }
        }

        foreach ($allTime as $index => $day) {
            $day = (array)$day;

            if (isset($xProfiles[$day['user_id']])) {
                $xprofile = $xProfiles[$day['user_id']];
                if ($xprofile->total_points < $day['total_points']) {
                    $oldPoints = $xprofile->total_points;
                    $profileModel = XProfile::where('user_id', $day['user_id'])->first();
                    $profileModel->total_points = (int)$day['total_points'];
                    $profileModel->save();
                    do_action('fluent_community/user_points_updated', $profileModel, $oldPoints);
                }

                $allTime[$index]['xprofile'] = $xprofile;
            } else {
                unset($allTime[$index]);
            }
        }

        $leaderBoard = [
            [
                'title' => __('Last 7 days', 'fluent-community-pro'),
                'items' => array_values($last7Days),
                'key'   => '7_days'
            ],
            [
                'title' => __('Last 30 days', 'fluent-community-pro'),
                'items' => array_values($last30Days),
                'key'   => '30_days'
            ],
            [
                'title' => __('All time', 'fluent-community-pro'),
                'items' => array_values($allTime),
                'key'   => 'all_time'
            ]
        ];

        return apply_filters('fluent_community/leaderboard_api_response', [
            'leaderboard' => $leaderBoard
        ], $xProfiles, $request->all());
    }

    public function getLevels(Request $request)
    {

        $exludiedUserIds = LeaderBoardHelper::getExcludedUserIds();
        $excludedUsers = [];

        if ($exludiedUserIds) {
            $excludedUsers = User::whereIn('ID', $exludiedUserIds)
                ->select(['ID', 'display_name', 'user_email'])
                ->get();
        }

        return [
            'levels'          => LeaderBoardHelper::getDynamicLevels(),
            'excludedUserIds' => LeaderBoardHelper::getExcludedUserIds(),
            'excludedUsers'   => $excludedUsers
        ];
    }

    public function saveLevels(Request $request)
    {
        $newLevels = $request->get('levels');
        $levels = LeaderBoardHelper::getDynamicLevels();

        foreach ($levels as $levelKey => &$level) {
            $newLevel = Arr::get($newLevels, $levelKey);
            if (!$newLevel) {
                continue;
            }

            $level['title'] = sanitize_text_field($newLevel['title']);
            $level['tagline'] = sanitize_text_field($newLevel['tagline']);
            $level['min_points'] = absint((int)$newLevel['min_points']);
            $level['slug'] = $levelKey;
        }

        $levels = array_values($levels);

        // Let's sort the levels by min_points
        usort($levels, function ($a, $b) {
            return $a['min_points'] - $b['min_points'];
        });

        $formattedLevels = [];

        foreach ($levels as $index => &$level) {
            $nextIndex = $index + 1;
            if ($index == 0) {
                $level['min_points'] = 0;
                $level['max_points'] = $levels[$nextIndex]['min_points'] - 1;
            } else if ($index == count($levels) - 1) {
                $level['min_points'] = $levels[$index - 1]['max_points'] + 1;
                $level['max_points'] = 9999999999;
            } else {
                $level['min_points'] = $levels[$index - 1]['max_points'] + 1;
                $level['max_points'] = $levels[$nextIndex]['min_points'] - 1;
            }

            $level['slug'] = 'level_' . ($index + 1);
            $level['level'] = $index + 1;
            $formattedLevels['level_' . ($index + 1)] = Arr::only($level, ['title', 'tagline', 'slug', 'level', 'min_points', 'max_points']);
        }

        Utility::updateOption('fcom_leaderboard_levels', $formattedLevels);

        $excludedUserIds = $request->get('excludedUserIds', []);

        $excludedUserIds = array_map('absint', $excludedUserIds);
        Utility::updateOption('fcom_leaderboard_excluded_user_ids', $excludedUserIds);

        $removeCacheKeys = [
            'leader_board_cache_7_10',
            'leader_board_cache_30_10',
            'leader_board_cache_0_10'
        ];

        foreach ($removeCacheKeys as $cacheKey) {
            Utility::deleteOption($cacheKey);
        }

        return [
            'message' => __('Leaderboard configuration has been updated.', 'fluent-community-pro'),
            'levels'  => $formattedLevels
        ];
    }
}
