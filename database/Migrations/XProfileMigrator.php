<?php
// phpcs:disable

namespace FluentCommunity\Database\Migrations;

class XProfileMigrator
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

        $table = $wpdb->prefix . 'fcom_xprofile';
        $indexPrefix = $wpdb->prefix . 'fcom_xp_';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED NOT NULL UNIQUE,
                `total_points` INT(11) UNSIGNED NOT NULL DEFAULT 0,
                `username` VARCHAR(100) NULL,
                `status` enum('active', 'blocked', 'pending') NOT NULL DEFAULT 'active',
                `is_verified` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `display_name` VARCHAR(192) NULL,
                `avatar` TEXT NULL,
                `short_description` TEXT NULL,
                `last_activity` DATETIME NULL,
                `meta` LONGTEXT NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                 INDEX `{$indexPrefix}_user_id` (`user_id`),
                 INDEX `{$indexPrefix}_username` (`username`)
            ) $charsetCollate;";
            dbDelta($sql);
        }
    }
}
