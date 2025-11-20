<?php
// phpcs:disable
namespace FluentCommunity\Database\Migrations;

class FeedReactionsMigrator
{
    /**
     * @param bool $isForced
     * @return void
     */
    public static function migrate($isForced = false)
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'fcom_post_reactions';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table || $isForced) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED NULL,
                `object_id` BIGINT UNSIGNED NULL,
                `parent_id` BIGINT UNSIGNED NULL,
                `object_type` VARCHAR(100) default 'feed',
                `type` VARCHAR(100) NULL DEFAULT 'like',
                `ip_address` VARCHAR(100) NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX object_user_object_type_type (object_id, user_id, object_type, type),
                INDEX object_type_parent_id_user_id (object_type, parent_id, user_id)
            ) $charsetCollate;";
            dbDelta($sql);
        } else {
            $indexes = $wpdb->get_results("SHOW INDEX FROM $table");

            $hasObjectUserObjectTypeType = false;
            $hasObjectTypeParentIdUserId = false;

            foreach ($indexes as $index) {
                if ($index->Key_name === 'object_user_object_type_type') {
                    $hasObjectUserObjectTypeType = true;
                }
                if ($index->Key_name === 'object_type_parent_id_user_id') {
                    $hasObjectTypeParentIdUserId = true;
                }
            }

            if (!$hasObjectUserObjectTypeType) {
                $wpdb->query("ALTER TABLE $table ADD INDEX object_user_object_type_type (object_id, user_id, object_type, type)");
            }

            if (!$hasObjectTypeParentIdUserId) {
                $wpdb->query("ALTER TABLE $table ADD INDEX object_type_parent_id_user_id (object_type, parent_id, user_id)");
            }

            $removeIndexes = ['object_id', 'object_type', 'type'];

            foreach ($indexes as $index) {
                if (in_array($index->Column_name, $removeIndexes)) {
                    $wpdb->query("ALTER TABLE $table DROP INDEX $index->Key_name");
                }
            }
        }
    }
}
