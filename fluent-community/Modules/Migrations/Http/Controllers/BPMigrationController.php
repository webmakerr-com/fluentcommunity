<?php

namespace FluentCommunity\Modules\Migrations\Http\Controllers;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Http\Controllers\Controller;
use FluentCommunity\App\Models\BaseSpace;
use FluentCommunity\App\Models\Space;
use FluentCommunity\App\Models\SpaceGroup;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Http\Request\Request;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\Modules\Migrations\Helpers\BPMigratorHelper;

class BPMigrationController extends Controller
{
    protected $timeLimit = 40;

    protected $startTimeStamp = 0;

    public function getMigrationConfig(Request $request)
    {
        $previousConfig = $this->getCurrentStatus();

        $groups = [];
        $hasGroups = bp_is_active('groups');

        if ($hasGroups) {
            $groups = fluentCommunityApp('db')->table('bp_groups')->select('id', 'name')->get();
            foreach ($groups as $group) {
                $group->members_count = fluentCommunityApp('db')->table('bp_groups_members')->where('group_id', $group->id)->count();
                $group->is_migrated = isset($previousConfig['migrated_groups'][$group->id]);
            }
        }

        $data = [
            'groupItems'     => $groups,
            'featureConfig'  => [
                'has_groups' => $hasGroups
            ],
            'stats'          => BPMigratorHelper::getBbDataStats(),
            'current_status' => $previousConfig,
            'has_previous'   => !empty($previousConfig['migrated_groups']) || !empty($previousConfig['last_migrated_user_id'])
        ];

        return $data;
    }

    public function startMigration(Request $request)
    {
        if ($request->get('reset_migration') === 'yes') {
            update_option('_fcom_bp_migrations_status', [], 'no');
        }

        if ($request->get('delete_current_data') === 'yes') {
            $this->deleteCurrentData();
        }

        $configMap = (array)$request->get('config', []);
        $prevStatus = $this->getCurrentStatus();

        if (bp_is_active('groups')) {
            $groups = fluentCommunityApp('db')->table('bp_groups')->get();
            if ($groups) {
                foreach ($groups as $group) {
                    if (!empty($configMap[$group->id])) {
                        $group->space_menu_id = $configMap[$group->id];
                    }
                }

                $createdMaps = $this->migrateGroups($groups);
                $prevStatus['migrated_groups'] = $createdMaps;
            }
            $prevStatus['current_stage'] = 'group_members';
        } else {
            $prevStatus['current_stage'] = 'posts';
        }

        BPMigratorHelper::maybeEnableFollowersModule();

        $status = $this->updateCurrentStatus($prevStatus);

        return [
            'current_status' => $status,
            'max_ids'        => [
                'group_member_max' => fluentCommunityApp('db')->table('bp_groups_members')->max('id'),
                'max_user_id'      => fluentCommunityApp('db')->table('bp_xprofile_data')->count('user_id'),
                'max_activity_id'  => fluentCommunityApp('db')->table('bp_activity')->max('id')
            ]
        ];
    }

    public function getPollingStatus()
    {
        $this->timeLimit = Utility::getMaxRunTime();
        $this->startTimeStamp = time();

        $status = $this->getCurrentStatus();
        $currentStep = Arr::get($status, 'current_stage', 'groups');

        $validStages = ['group_members', 'posts', 'users', 'completed'];

        if (!in_array($currentStep, $validStages)) {
            return $this->sendError([
                'message' => 'Invalid stage. Please start the migration again.'
            ]);
        }

        if ($currentStep == 'group_members') {
            return $this->syncGroupMembers($status);
        }

        if ($currentStep == 'posts') {
            return $this->syncPostsAndComments($status);
        }

        if ($currentStep == 'users') {
            return $this->syncUsers($status);
        }

        return $this->getCurrentStatus();
    }

    protected function syncUsers($status)
    {
        if ($this->isTimeLimitExceeded(10)) {
            return $this->getCurrentStatus();
        }

        $lastUserId = Arr::get($status, 'last_migrated_user_id', 0);
        $usersIds = fluentCommunityApp('db')->table('bp_xprofile_data')
            ->groupBy('user_id')
            ->select(['user_id'])
            ->when($lastUserId, function ($q) use ($lastUserId) {
                $q->where('user_id', '>', $lastUserId);
            })
            ->orderBy('user_id', 'ASC')
            ->limit(100)
            ->get()
            ->pluck('user_id')
            ->toArray();

        if (!$usersIds) {
            $status['current_stage'] = 'completed';
            $this->updateCurrentStatus($status, false);
            return $this->getCurrentStatus();
        }

        $users = User::whereIn('id', $usersIds)->get();

        if ($users->isEmpty()) {
            $status['current_stage'] = 'completed';
            $this->updateCurrentStatus($status, false);
            return $this->getCurrentStatus();
        }

        $lastUserId = null;

        foreach ($users as $user) {
            $lastUserId = $user->ID;
            BPMigratorHelper::syncUser($user);
        }

        do_action('fluent_community/after_sync_bp_users', $users);

        $status['last_migrated_user_id'] = $lastUserId;
        $this->updateCurrentStatus($status, false);

        return $this->syncUsers($status);
    }

    protected function syncGroupMembers($status)
    {
        if ($this->isTimeLimitExceeded(10)) {
            return $this->getCurrentStatus();
        }

        $lastMemberId = Arr::get($status, 'last_migrated_member_id', 0);

        $groupMemberEntries = fluentCommunityApp('db')->table('bp_groups_members')
            ->when($lastMemberId, function ($q) use ($lastMemberId) {
                $q->where('id', '>', $lastMemberId);
            })
            ->orderBy('id', 'ASC')
            ->limit(100)
            ->get();

        if ($groupMemberEntries->isEmpty()) {
            $status['current_stage'] = 'posts';
            $this->updateCurrentStatus($status, false);
            return $this->getCurrentStatus();
        }

        foreach ($groupMemberEntries as $entry) {
            $spaceId = Arr::get($status['migrated_groups'], $entry->group_id);
            if (!$spaceId) {
                continue;
            }

            $role = 'member';
            if ($entry->is_admin == 1) {
                $role = 'admin';
            } else if ($entry->is_mod == 1) {
                $role = 'moderator';
            }

            $entryData = [
                'space_id'   => $spaceId,
                'user_id'    => $entry->user_id,
                'status'     => 'active',
                'role'       => $role,
                'created_at' => $entry->date_modified,
            ];

            if (!Helper::isUserInSpace($entryData['user_id'], $entryData['space_id'])) {
                fluentCommunityApp('db')->table('fcom_space_user')->insert($entryData);
            }

            $status['last_migrated_member_id'] = $entry->id;
            $status = $this->updateCurrentStatus($status, false);
        }

        return $this->syncGroupMembers($status);
    }

    protected function syncPostsAndComments($status)
    {
        if ($this->isTimeLimitExceeded(10)) {
            return $this->getCurrentStatus();
        }

        $lastPostId = Arr::get($status, 'last_activity_id', 0);

        $isBuddyBoss = BPMigratorHelper::isBuddyBoss();

        $posts = fluentCommunityApp('db')->table('bp_activity')
            ->where('type', 'activity_update')
            ->when($isBuddyBoss, function ($q) {
                $q->whereNotIn('privacy', ['media', 'onlyme']);
            })
            ->when($lastPostId, function ($q) use ($lastPostId) {
                $q->where('id', '>', $lastPostId);
            })
            ->orderBy('id', 'ASC')
            ->limit(40)
            ->get();

        if ($posts->isEmpty()) {
            $status['current_stage'] = 'users';
            $this->updateCurrentStatus($status, false);
            return $this->getCurrentStatus();
        }

        foreach ($posts as $post) {
            $status['last_activity_id'] = $post->id;
            $status = $this->updateCurrentStatus($status, false);

            if (fluentCommunityApp('db')->table('bp_activity_meta')->where('activity_id', $post->id)->where('meta_key', '_fcom_feed_id')->exists()) {
                continue;
            }

            $feed = BPMigratorHelper::migratePost($post, Arr::get($status['migrated_groups'], $post->item_id, NULL));

            if ($feed) {
                fluentCommunityApp('db')->table('bp_activity_meta')
                    ->insert([
                        'activity_id' => $post->id,
                        'meta_key'    => '_fcom_feed_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                        'meta_value'  => $feed->id // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                    ]);
            }
        }

        return $this->syncPostsAndComments($status);
    }

    protected function migrateGroups($groups)
    {
        $createdMaps = [];
        foreach ($groups as $group) {
            $createdSpace = BPMigratorHelper::migrateGroupData($group);
            if ($createdSpace) {
                $createdMaps[$group->id] = $createdSpace->id;
            }
        }

        update_option('_bp_fcom_group_maps', $createdMaps);

        return $createdMaps;
    }

    private function isTimeLimitExceeded($offset = 10)
    {
        $timeLimit = $this->timeLimit - $offset;
        $currentTime = time();
        $timeElapsed = $currentTime - $this->startTimeStamp;

        return $timeElapsed >= $timeLimit;
    }

    private function getCurrentStatus()
    {
        $defults = [
            'migrated_groups'         => [],
            'last_activity_id'        => 0,
            'migrated_posts_count'    => 0,
            'last_migrated_user_id'   => 0,
            'last_migrated_member_id' => 0,
            'current_stage'           => 'groups',
        ];

        $status = (array)get_option('_fcom_bp_migrations_status', $defults);

        $status = wp_parse_args($status, $defults);

        return $status;
    }

    private function updateCurrentStatus($newData, $resync = true)
    {
        if ($resync) {
            $status = $this->getCurrentStatus();
            $newData = wp_parse_args($newData, $status);
        }

        $newData = Arr::only($newData, [
            'migrated_groups',
            'last_activity_id',
            'last_migrated_member_id',
            'migrated_posts_count',
            'last_migrated_user_id',
            'current_stage'
        ]);

        update_option('_fcom_bp_migrations_status', $newData, 'no');

        return $newData;
    }

    private function deleteCurrentData()
    {
        BPMigratorHelper::deleteCurrentData();
    }
}
