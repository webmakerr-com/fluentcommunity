<?php

namespace FluentCommunity\App\Hooks\CLI;

use FluentCommunity\App\Models\User;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\Modules\Migrations\Helpers\BPMigratorHelper;
use FluentCommunity\Modules\Migrations\Helpers\PostMigrator;

class BuddyPressMigrator
{
    public function getStats()
    {
        $stats = BPMigratorHelper::getBbDataStats();
        $formattedStats = [];

        foreach ($stats as $key => $stat) {
            $formattedStats[] = [
                'key'   => $key,
                'count' => $stat
            ];
        }

        return $formattedStats;
    }

    public function migrateGroups()
    {
        $hasGroups = bp_is_active('groups');

        if (!$hasGroups) {
            return \WP_CLI::line('BuddyPress Groups component is not active. Please activate it before running the migration.');
        }

        $groups = fluentCommunityApp('db')->table('bp_groups')->get();

        $createdMaps = [];

        foreach ($groups as $group) {
            $createdSpace = BPMigratorHelper::migrateGroupData($group);
            if ($createdSpace) {
                $createdMaps[$group->id] = $createdSpace->id;
            }
        }

        \WP_CLI::line(count($createdMaps) . ' BuddyPress Groups migrated successfully.');

        update_option('_bp_fcom_group_maps', $createdMaps);

        return $createdMaps;
    }

    public function migratePosts($lastPostId = 0)
    {
        if (!$lastPostId) {
            $lastPostId = get_option('_bp_fcom_last_post_id', 0);
        }

        $isBuddyBoss = BPMigratorHelper::isBuddyBoss();

        $posts = fluentCommunityApp('db')->table('bp_activity')
            ->where('type', 'activity_update')
            ->when($isBuddyBoss, function ($q) {
                $q->whereNotIn('privacy', ['media', 'onlyme', 'document']);
            })
            ->when($lastPostId, function ($q) use ($lastPostId) {
                $q->where('id', '>', $lastPostId);
            })
            ->where(function ($q) {
                $q->where('secondary_item_id', '=', '0')
                    ->orWhereNull('secondary_item_id');
            })
            ->orderBy('id', 'ASC')
            ->limit(100)
            ->get();

        $migrated = 0;

        foreach ($posts as $post) {
            $migrated++;
            $lastPostId = $post->id;

            if (fluentCommunityApp('db')->table('bp_activity_meta')->where('activity_id', $post->id)->where('meta_key', '_fcom_feed_id')->exists()) {
                continue;
            }

            $feed = (new PostMigrator($post))->migrate();

            if (!$feed) {
                continue;
            }

            fluentCommunityApp('db')->table('bp_activity_meta')
                ->insert([
                    'activity_id' => $post->id,
                    'meta_key'    => '_fcom_feed_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                    'meta_value'  => $feed->id // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                ]);
        }

        update_option('_bp_fcom_last_post_id', $lastPostId);

        if (!$migrated) {
            \WP_CLI::line('No more posts to migrate.');
            return true;
        }

        \WP_CLI::line($migrated . ' posts migrated successfully. Last Post ID: ' . $lastPostId . ' at ' . gmdate('Y-m-d H:i:s'));

        return $this->migratePosts($lastPostId);
    }

    public function syncUsers()
    {
        $lastUserId = get_option('_bp_fcom_last_user_id', 0);

        if (!$lastUserId) {
            $lastUserId = 0;
        }

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

        if (!count($usersIds)) {
            \WP_CLI::line('No more users to sync.');
            return true;
        }

        $users = User::whereIn('id', $usersIds)->get();

        if ($users->isEmpty()) {
            \WP_CLI::line('No users found to sync.');
            return true;
        }


        foreach ($users as $user) {
            $lastUserId = $user->ID;
            BPMigratorHelper::syncUser($user);
        }

        do_action('fluent_community/after_sync_bp_users', $users);

        update_option('_bp_fcom_last_user_id', $lastUserId);

        \WP_CLI::line('Synced ' . count($users) . ' users successfully. Last User ID: ' . $lastUserId . ' at ' . gmdate('Y-m-d H:i:s'));

        return $this->syncUsers();
    }

    public function syncGroupMembers()
    {
        $lastMemberId = get_option('_bp_fcom_last_migrated_member_id', 0);

        $groupMemberEntries = fluentCommunityApp('db')->table('bp_groups_members')
            ->when($lastMemberId, function ($q) use ($lastMemberId) {
                $q->where('id', '>', $lastMemberId);
            })
            ->orderBy('id', 'ASC')
            ->limit(100)
            ->get();

        $spaceMaps = get_option('_bp_fcom_group_maps', []);

        $hadItem = false;

        foreach ($groupMemberEntries as $entry) {
            $hadItem = true;
            $spaceId = Arr::get($spaceMaps, $entry->group_id);

            $lastMemberId = $entry->id;

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
        }

        update_option('_bp_fcom_last_migrated_member_id', $lastMemberId);

        if (!$hadItem) {
            \WP_CLI::line('No more group members to sync.');
            return true;
        }

        \WP_CLI::line('Synced ' . count($groupMemberEntries) . ' group members successfully. Last Member ID: ' . $lastMemberId . ' at ' . gmdate('Y-m-d H:i:s'));

        return $this->syncGroupMembers();

    }

    public function finishingUp()
    {

    }

}
