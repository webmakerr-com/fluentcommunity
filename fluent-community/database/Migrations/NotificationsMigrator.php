<?php
// phpcs:disable
namespace FluentCommunity\Database\Migrations;

class NotificationsMigrator
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

        $table = $wpdb->prefix . 'fcom_notifications';
        $indexPrefix = $wpdb->prefix . 'fcom_nt_';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `feed_id` BIGINT UNSIGNED NULL,
                `object_id` BIGINT UNSIGNED NULL,
                `src_user_id` BIGINT UNSIGNED NULL,
                `src_object_type` VARCHAR(100) NULL,
                `action` VARCHAR(100) NULL,
                `title` VARCHAR(192) NULL,
                `content` TEXT NULL,
                `route` TEXT NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                 INDEX `{$indexPrefix}_mt_idx` (`feed_id`),
                 INDEX `{$indexPrefix}_mto_id_idx` (`object_id`),
                 INDEX `{$indexPrefix}_mto_id_key` (`action` )
            ) $charsetCollate;";
            dbDelta($sql);
        }
    }
}
