<?php
// phpcs:disable

namespace FluentCommunity\Database\Migrations;

class TermFeedMigrator
{
    /**
     * @param bool $isForced
     * @return void
     */
    public static function migrate($isForced = false)
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'fcom_term_feed';

        $indexPrefix = $wpdb->prefix . 'fcom_tf_';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table || $isForced) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `term_id` BIGINT UNSIGNED NULL,
                `post_id` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `{$indexPrefix}_term_id` (`term_id`),
                INDEX `{$indexPrefix}_post_id` (`post_id`)
            ) $charsetCollate;";
            dbDelta($sql);
        }
    }
}
