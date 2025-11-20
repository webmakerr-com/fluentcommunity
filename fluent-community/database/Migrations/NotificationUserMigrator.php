<?php
// phpcs:disable

namespace FluentCommunity\Database\Migrations;

class NotificationUserMigrator
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

        $table = $wpdb->prefix . 'fcom_notification_users';
        $indexPrefix = $wpdb->prefix . 'fcom_nu_';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `object_type` VARCHAR(50) NULL DEFAULT 'notification',
                `notification_type` VARCHAR(50) NULL DEFAULT 'web',
                `object_id` BIGINT UNSIGNED NULL,
                `user_id` BIGINT UNSIGNED NULL,
                `is_read` TINYINT(1) UNSIGNED NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                 INDEX `{$indexPrefix}_mto_id_uio` (`user_id`, `is_read`, `object_type`),
                 INDEX `{$indexPrefix}_mto_id_oion` (`object_id`, `is_read`, `object_type`, `notification_type`)
            ) $charsetCollate;";
            dbDelta($sql);
        } else {
            self::maybeAlterDBColumns();
        }
    }

    private static function maybeAlterDBColumns()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'fcom_notification_users';
        // get column names
        $column_names = $wpdb->get_col("DESC " . $table, 0);

        if(in_array('notification_id', $column_names)) {
            $wpdb->query("ALTER TABLE $table CHANGE `notification_id` `object_id` bigint unsigned NULL");
        }

        $newItems = ['object_type', 'notification_type'];
        $newItems = array_diff($newItems, $column_names);
        if($newItems) {
            if(in_array('object_type', $newItems)) {
                $wpdb->query("ALTER TABLE $table ADD object_type VARCHAR(50) NULL DEFAULT 'notification' AFTER id");
            }
            if(in_array('notification_type', $newItems)) {
                $wpdb->query("ALTER TABLE $table ADD notification_type VARCHAR(50) NULL DEFAULT 'web' AFTER object_type");
            }
        }

        $allIndexes = $wpdb->get_col("SHOW INDEX FROM $table", 2);
        $previousIndexName = $wpdb->prefix . 'fcom_nu__mto_id_idx';
        if(in_array($previousIndexName, $allIndexes)) {
            // remove this index
            $wpdb->query("ALTER TABLE $table DROP INDEX `{$previousIndexName}`");
        }

        $newIndex1 = $wpdb->prefix . 'fcom_nu__mto_id_uio';

        if(in_array($newIndex1, $allIndexes)) {
            // remove this index
            $wpdb->query("ALTER TABLE $table DROP INDEX `{$newIndex1}`");
        }

        $index2 = $wpdb->prefix . 'fcom_nu__mto_id_oion';

        if(!in_array($index2, $allIndexes)) {
            // add this index
            $wpdb->query("ALTER TABLE $table ADD INDEX `{$index2}` (`object_id`, `is_read`, `object_type`, `notification_type`)");
        }
    }
}
