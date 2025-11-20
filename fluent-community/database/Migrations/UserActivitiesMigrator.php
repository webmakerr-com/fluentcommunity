<?php
// phpcs:disable

namespace FluentCommunity\Database\Migrations;

class UserActivitiesMigrator
{
    /**
     * @param bool $isForced
     * @return void
     */
    public static function migrate($isForced = false)
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'fcom_user_activities';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table || $isForced) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED NULL,
                `feed_id` BIGINT UNSIGNED NULL,
                `space_id` BIGINT UNSIGNED NULL,
                `related_id` BIGINT UNSIGNED NULL,
                `message` TEXT NULL,
                `is_public` TINYINT(1) NULL DEFAULT 1,
                `action_name` VARCHAR(100) NULL DEFAULT '',
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `feed_id` (`feed_id`),
                INDEX `user_id` (`user_id`),
                INDEX `action_name` (`action_name`)
            ) $charsetCollate;";
            dbDelta($sql);
        }
    }
}
