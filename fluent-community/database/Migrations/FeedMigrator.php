<?php
// phpcs:disable
namespace FluentCommunity\Database\Migrations;

class FeedMigrator
{
    /**
     * @param bool $isForced
     * @return void
     */
    public static function migrate($isForced = false)
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'fcom_posts';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table || $isForced) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED NULL,
                `parent_id` BIGINT UNSIGNED NULL,
                `title` VARCHAR(192) NULL,
                `slug` VARCHAR(192) NULL,
                `message` LONGTEXT NULL,
                `message_rendered` LONGTEXT NULL,
                `type` VARCHAR(100) NULL DEFAULT 'feed',
                `content_type` VARCHAR(100) NULL DEFAULT 'text',
                `space_id` BIGINT UNSIGNED NULL,
                `privacy` VARCHAR(100) NULL DEFAULT 'public',
                `status` VARCHAR(100) NULL DEFAULT 'published', 
                `featured_image` TEXT NULL,
                `meta` LONGTEXT NULL,
                `is_sticky` TINYINT(1) NULL DEFAULT 0,
                `comments_count` INT(11) NULL DEFAULT 0,
                `reactions_count` INT(11) NULL DEFAULT 0,
                `priority` INT(11) NULL DEFAULT 0,
                `expired_at` DATETIME NULL,
                `scheduled_at` DATETIME NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `user_id` (`user_id`),
                INDEX `slug` (`slug`),
                INDEX `created_at` (`created_at`),
                INDEX `idx_space_id_status` (`space_id`, `status`),
                INDEX `idx_space_id_status_privacy` (`space_id`, `status`, `privacy`)
            ) $charsetCollate;";
            dbDelta($sql);
        } else {
            // check if scheduled_at is exist or not
            $isMigrated = $wpdb->get_col($wpdb->prepare("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='scheduled_at' AND TABLE_NAME=%s", $table));
            if(!$isMigrated) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN `scheduled_at` DATETIME NULL AFTER `expired_at`");
            }
        }
    }
}
