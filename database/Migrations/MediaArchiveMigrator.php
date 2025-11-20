<?php
// phpcs:disable
namespace FluentCommunity\Database\Migrations;

class MediaArchiveMigrator
{
    /**
     * Migrate the table.
     *
     * @return void
     */
    public static function migrate()
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $table = $wpdb->prefix . 'fcom_media_archive';
        $indexPrefix = $wpdb->prefix . 'fcom_mar_';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `object_source` VARCHAR(100) NOT NULL,
                `media_key` VARCHAR(100) NOT NULL,
                `user_id` BIGINT NULL,
                `feed_id` BIGINT NULL,
                `is_active` TINYINT(1) DEFAULT 0,
                `sub_object_id` BIGINT NULL,
                `media_type` VARCHAR(192) NULL,
                `driver` VARCHAR(192) NULL DEFAULT 'local',
                `media_path` TEXT NULL,
                `media_url` TEXT NULL,
                `settings` TEXT NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                 INDEX `{$indexPrefix}_mt_is_active` (`is_active`),
                 INDEX `{$indexPrefix}_mto_user_id` (`user_id`),
                 INDEX `{$indexPrefix}_mto_media_key` (`media_key`),
                 INDEX `{$indexPrefix}_mto_feed_id` (`feed_id` )
            ) $charsetCollate;";
            dbDelta($sql);
        }
    }
}
