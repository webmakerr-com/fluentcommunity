<?php
// phpcs:disable
namespace FluentCommunity\Database\Migrations;

class FeedSpaceUserMigrator
{
    /**
     * @param bool $isForced
     * @return void
     */
    public static function migrate($isForced = false)
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'fcom_space_user';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table || $isForced) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `space_id` BIGINT UNSIGNED NULL,
                `user_id` VARCHAR(194) NOT NULL,
                `status` VARCHAR(100) NULL DEFAULT 'active',
                `role` VARCHAR(100) NULL DEFAULT 'member',
                `meta` TEXT NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `status` (`status`),
                INDEX `space_id_user_id` (`space_id`, `user_id`),
                INDEX `role` (`role`)
            ) $charsetCollate;";
            dbDelta($sql);
        } else {
            // check we have column notification_level
            // if yes then drop it
            $column_names = $wpdb->get_col("DESC " . $table, 0);
            if (in_array('notification_level', $column_names)) {
                $wpdb->query("ALTER TABLE $table DROP INDEX notification_level");
                $wpdb->query("ALTER TABLE $table DROP COLUMN notification_level");
            }

            // check if we have meta column
            // if not then add it
            if (!in_array('meta', $column_names)) {
                $wpdb->query("ALTER TABLE $table ADD COLUMN `meta` TEXT NULL AFTER `role`");
            }

            //  now check index for space_id_user_id
            $index_names = $wpdb->get_col("SHOW INDEX FROM $table", 2);
            if (!in_array('space_id_user_id', $index_names)) {
                $wpdb->query("ALTER TABLE $table ADD INDEX `space_id_user_id` (`space_id`, `user_id`)");
            }
            
            // now check index for role
            if (!in_array('role', $index_names)) {
                $wpdb->query("ALTER TABLE $table ADD INDEX `role` (`role`)");
            }

        }
    }
}
