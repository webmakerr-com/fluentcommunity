<?php
// phpcs:disable
namespace FluentCommunity\Database\Migrations;

class FeedCommentsMigrator
{
    /**
     * @param bool $isForced
     * @return void
     */
    public static function migrate($isForced = false)
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'fcom_post_comments';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table || $isForced) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED NULL,
                `post_id` BIGINT UNSIGNED NULL,
                `parent_id` BIGINT UNSIGNED NULL,
                `reactions_count` BIGINT UNSIGNED DEFAULT 0,
                `message` LONGTEXT NULL,
                `message_rendered` LONGTEXT NULL,
                `meta` LONGTEXT NULL,
                `type` VARCHAR(100) NULL DEFAULT 'comment',
                `content_type` VARCHAR(100) NULL DEFAULT 'text',
                `status` VARCHAR(100) NULL DEFAULT 'published',
                `is_sticky` TINYINT(1) NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `post_id` (`post_id`),
                INDEX `status` (`status`),
                INDEX `type` (`type`)
            ) $charsetCollate;";
            dbDelta($sql);
        } else {
            // check if meta is exist or not
            $isMigrated = $wpdb->get_col($wpdb->prepare("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='meta' AND TABLE_NAME=%s", $table));
            if(!$isMigrated) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN `meta` LONGTEXT NULL AFTER `message_rendered`");
            }
        }
    }
}
