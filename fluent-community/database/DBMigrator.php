<?php

namespace FluentCommunity\Database;

use FluentCommunity\Database\Migrations\FeedCommentsMigrator;
use FluentCommunity\Database\Migrations\FeedMigrator;
use FluentCommunity\Database\Migrations\FeedReactionsMigrator;
use FluentCommunity\Database\Migrations\FeedSpaceMigrator;
use FluentCommunity\Database\Migrations\FeedSpaceUserMigrator;
use FluentCommunity\Database\Migrations\MediaArchiveMigrator;
use FluentCommunity\Database\Migrations\MetaMigrator;
use FluentCommunity\Database\Migrations\NotificationsMigrator;
use FluentCommunity\Database\Migrations\NotificationUserMigrator;
use FluentCommunity\Database\Migrations\TermFeedMigrator;
use FluentCommunity\Database\Migrations\TermMigrator;
use FluentCommunity\Database\Migrations\UserActivitiesMigrator;
use FluentCommunity\Database\Migrations\XProfileMigrator;

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

class DBMigrator
{
    public static function run($network_wide = false)
    {
        if($network_wide) {
            global $wpdb;
            $blogs = $wpdb->get_results("SELECT blog_id FROM $wpdb->blogs"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            foreach ($blogs as $blog) {
                switch_to_blog($blog->blog_id);
                self::migrate();
                restore_current_blog();
            }
            return;
        }

        self::migrate();
    }

    private static function migrate()
    {
        FeedSpaceMigrator::migrate();
        FeedMigrator::migrate();
        FeedReactionsMigrator::migrate();
        FeedCommentsMigrator::migrate();
        FeedSpaceUserMigrator::migrate();
        MetaMigrator::migrate();
        NotificationsMigrator::migrate();
        NotificationUserMigrator::migrate();
        UserActivitiesMigrator::migrate();
        TermMigrator::migrate();
        TermFeedMigrator::migrate();
        MediaArchiveMigrator::migrate();
        XProfileMigrator::migrate();
    }
}
