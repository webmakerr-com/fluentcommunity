<?php
// phpcs:disable

namespace FluentCommunity\Database\Migrations;

class TermMigrator
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

        $table = $wpdb->prefix . 'fcom_terms';
        $indexPrefix = $wpdb->prefix . 'fcom_tm_';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `parent_id` BIGINT UNSIGNED NULL,
                `taxonomy_name` VARCHAR(50) NOT NULL,
                `slug` VARCHAR(100) NOT NULL,
                `title` LONGTEXT NULL,
                `description` LONGTEXT NULL,
                `settings` LONGTEXT NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                 INDEX `{$indexPrefix}_mt_tax` (`taxonomy_name`),
                 INDEX `{$indexPrefix}_mt_slug` (`slug`)
            ) $charsetCollate;";
            dbDelta($sql);
        }
    }
}
