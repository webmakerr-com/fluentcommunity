<?php

namespace FluentCommunity\App\Hooks\CLI;

use FluentCommunity\App\Models\XProfile;
use FluentCommunity\Modules\Migrations\Helpers\BPMigratorHelper;
use FluentCommunity\Modules\Migrations\Helpers\PostMigrator;
use FluentCommunity\App\Models\User;
use FluentCommunity\Framework\Support\Arr;

class Commands
{

    public function migrate_from_bb($args, $assoc_args = [])
    {
        $migrator = new BuddyPressMigrator();

//        $migrator->migrateGroups();
//
//        die();


//        $migrator->migratePosts();
//
//        die('Yap');
//
//

//        $postMigrator = new PostMigrator(13461);
//        $feed = $postMigrator->migrate();
//        dd($feed);


//        \WP_CLI::line('Starting BuddyPress Data Migration...');
//        BPMigratorHelper::deleteCurrentData();
//        \WP_CLI::line('Deleted existing Fluent Community data.');
//        $migrator->migrateGroups();
//
//        die();

        \WP_CLI::line('Starting BuddyPress Data Migration...');
        BPMigratorHelper::deleteCurrentData();
        \WP_CLI::line('Deleted existing Fluent Community data.');
        $migrator->migrateGroups();
        \WP_CLI::line('Migrated BuddyPress Groups to Fluent Community Spaces.');
        $migrator->syncGroupMembers();
        \WP_CLI::line('Synchronized Group Members to Space Members.');
        $migrator->migratePosts();
        \WP_CLI::line('Migrated BuddyPress Posts to Fluent Community Feeds.');
        $migrator->syncUsers();
        \WP_CLI::line('Synchronized Users.');

        \WP_CLI::line('Calculating User points.');
        $this->recalculate_user_points();

        \WP_CLI::success('BuddyPress Data Migration Completed Successfully.');

        die();

//
//        die();

//        $migrator->migratePosts();
//        die();

        $postMigrator = new PostMigrator(13052);

        $feed = $postMigrator->migrate();

        dd($feed);

        die();


//        $stats = $migrator->getStats();
//        \WP_CLI::line('BuddyPress Data Stats:');
//        \WP_CLI\Utils\format_items('table', $stats, ['key', 'count']);

        BPMigratorHelper::deleteCurrentData();
        $migrator->migratePosts();

        die('OK');

        $migrator->migrateGroups();
        $migrator->syncGroupMembers();
        $migrator->migratePosts();
        $migrator->syncUsers();

        $fluentStats = [
            [
                'key'   => 'Total Users',
                'count' => XProfile::count()
            ],
            [
                'key'   => 'Total Spaces',
                'count' => \FluentCommunity\App\Models\Space::count()
            ],
            [
                'key'   => 'Total Posts',
                'count' => \FluentCommunity\App\Models\Feed::count()
            ],
            [
                'key'   => 'Total Comments',
                'count' => \FluentCommunity\App\Models\Comment::count()
            ],
            [
                'key'   => 'Total Reactions',
                'count' => \FluentCommunity\App\Models\Reaction::count()
            ]
        ];

        \WP_CLI\Utils\format_items('table', $fluentStats, ['key', 'count']);

    }

    /**
     * usage: wp fluent_community sync_x_profile --force
     */
    public function sync_x_profile($args, $assoc_args = [])
    {
        $isForced = Arr::get($assoc_args, 'force', false) == 1;

        $users = User::orderBy('ID', 'ASC')->get();

        foreach ($users as $user) {
            $result = $user->syncXProfile($isForced);
            \WP_CLI::line('Synced XProfile for UserID: ' . $user->ID . ' - ' . $result->id);
        }

        \WP_CLI::success('XProfile Synced Successfully');
    }

    /**
     * usage: wp fluent_community recalculate_user_points
     */
    public function recalculate_user_points()
    {
        $xProfiles = \FluentCommunity\App\Models\XProfile::all();

        $progress = \WP_CLI\Utils\make_progress_bar('Recalculating Points', count($xProfiles));

        foreach ($xProfiles as $xProfile) {

            $progress->tick();

            $currentPoint = BPMigratorHelper::recalculateUserPoints($xProfile->user_id);
            if ($currentPoint > $xProfile->total_points) {
                $oldPoints = $xProfile->total_points;
                $xProfile->total_points = $currentPoint;
                $xProfile->save();
                do_action('fluent_community/user_points_updated', $xProfile, $oldPoints);
                \WP_CLI::line(
                    'Recalculated Points for User: ' . $xProfile->display_name . ' - ' . $oldPoints . ' to ' . $currentPoint
                );
            }
        }

        $progress->finish();

        \WP_CLI::success('Points Recalculated Successfully for ' . count($xProfiles) . ' users');
    }


}
