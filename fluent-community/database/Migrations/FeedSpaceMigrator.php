<?php
// phpcs:disable
namespace FluentCommunity\Database\Migrations;

class FeedSpaceMigrator
{
    /**
     * @param bool $isForced
     * @return void
     */
    public static function migrate($isForced = false)
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'fcom_spaces';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table || $isForced) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `created_by` BIGINT UNSIGNED NULL,
                `parent_id` BIGINT UNSIGNED NULL,
                `title` VARCHAR(194) NOT NULL,
                `slug` VARCHAR(194) NOT NULL,
                `logo` TEXT NULL,
                `cover_photo` TEXT NULL,
                `description` LONGTEXT NULL,
                `type` VARCHAR(100) NULL,
                `privacy` VARCHAR(100) NULL DEFAULT 'public',
                `status` VARCHAR(100) NULL DEFAULT 'published',
                `serial` INT(11) NULL DEFAULT 1,
                `settings` LONGTEXT NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `title` (`title`),
                INDEX `status` (`status`)
            ) $charsetCollate;";
            dbDelta($sql);
        }
    }
}
