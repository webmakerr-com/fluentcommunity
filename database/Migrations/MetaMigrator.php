<?php
// phpcs:disable
namespace FluentCommunity\Database\Migrations;

class MetaMigrator
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

        $table = $wpdb->prefix . 'fcom_meta';
        $indexPrefix = $wpdb->prefix . 'fcom_mt_';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `object_type` VARCHAR(50) NOT NULL,
                `object_id` BIGINT NULL,
                `meta_key` VARCHAR(100) NOT NULL,
                `value` LONGTEXT NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                 INDEX `{$indexPrefix}_mt_idx` (`object_type` ASC),
                 INDEX `{$indexPrefix}_mto_id_idx` (`object_id` ASC),
                 INDEX `{$indexPrefix}_mto_id_meta_key` (`meta_key` )
            ) $charsetCollate;";
            dbDelta($sql);
        } else {
            $isMigrated = $wpdb->get_col($wpdb->prepare("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='meta_key' AND TABLE_NAME=%s", $table));
            if(!$isMigrated) {
                $wpdb->query("ALTER TABLE {$table} CHANGE `key` `meta_key` varchar(100) NOT NULL AFTER `object_id`");
            }
        }
    }
}
