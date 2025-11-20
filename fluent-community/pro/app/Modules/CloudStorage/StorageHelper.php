<?php

namespace FluentCommunityPro\App\Modules\CloudStorage;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Services\Helper;

class StorageHelper
{
    public static function getConfig($mode = 'internal')
    {
        if (defined('FLUENT_COMMUNITY_CLOUD_STORAGE') && FLUENT_COMMUNITY_CLOUD_STORAGE) {
            $config = [
                'is_defined'  => true,
                'driver'      => FLUENT_COMMUNITY_CLOUD_STORAGE,
                's3_endpoint' => defined('FLUENT_COMMUNITY_CLOUD_STORAGE_S3_REGION') ? FLUENT_COMMUNITY_CLOUD_STORAGE_S3_REGION : '',
                'account_id'  => defined('FLUENT_COMMUNITY_CLOUD_STORAGE_ACCOUNT_ID') ? FLUENT_COMMUNITY_CLOUD_STORAGE_ACCOUNT_ID : '',
                'access_key'  => defined('FLUENT_COMMUNITY_CLOUD_STORAGE_ACCESS_KEY') ? FLUENT_COMMUNITY_CLOUD_STORAGE_ACCESS_KEY : '',
                'secret_key'  => defined('FLUENT_COMMUNITY_CLOUD_STORAGE_SECRET_KEY') ? FLUENT_COMMUNITY_CLOUD_STORAGE_SECRET_KEY : '',
                'sub_folder'  => defined('FLUENT_COMMUNITY_CLOUD_STORAGE_SUB_FOLDER') ? FLUENT_COMMUNITY_CLOUD_STORAGE_SUB_FOLDER : '',
                'bucket'      => defined('FLUENT_COMMUNITY_CLOUD_STORAGE_BUCKET') ? FLUENT_COMMUNITY_CLOUD_STORAGE_BUCKET : '',
                'public_url'  => defined('FLUENT_COMMUNITY_CLOUD_STORAGE_PUBLIC_URL') ? FLUENT_COMMUNITY_CLOUD_STORAGE_PUBLIC_URL : ''
            ];

            if ($mode === 'internal') {
                return $config;
            }

            return self::maybeAddDummyKeys($config);
        }

        $config = Utility::getOption('fluent_community_storage_config', []);

        $defaults = [
            'driver' => 'local'
        ];

        $config = wp_parse_args($config, $defaults);

        if ($mode === 'internal') {
            return self::maybeDescryptKeys($config);
        }

        return self::maybeAddDummyKeys($config);

    }

    public static function updateConfig($config)
    {
        if (defined('FLUENT_COMMUNITY_CLOUD_STORAGE') && FLUENT_COMMUNITY_CLOUD_STORAGE) {
            return false;
        }

        if ($config['driver'] === 'bunny_cdn') {
            $publicUrl = $config['public_url'];
            // remove trailing slash
            $publicUrl = rtrim($publicUrl, '/');
            $config['public_url'] = $publicUrl;
        }

        $config = self::maybeEncryptKeys($config);
        Utility::updateOption('fluent_community_storage_config', $config);
        return true;
    }


    private static function maybeEncryptKeys($config)
    {
        if ($config['driver'] == 'local') {
            return $config;
        }

        if (!empty($config['access_key'])) {
            $config['access_key'] = Helper::encryptDecrypt($config['access_key'], 'e');
        }

        if (!empty($config['secret_key'])) {
            $config['secret_key'] = Helper::encryptDecrypt($config['secret_key'], 'e');
        }

        return $config;
    }

    private static function maybeDescryptKeys($config)
    {
        if ($config['driver'] === 'local') {
            return $config;
        }

        if (!empty($config['access_key'])) {
            $config['access_key'] = Helper::encryptDecrypt($config['access_key'], 'd');
        }

        if (!empty($config['secret_key'])) {
            $config['secret_key'] = Helper::encryptDecrypt($config['secret_key'], 'd');
        }

        return $config;
    }

    private static function maybeAddDummyKeys($config)
    {
        if ($config['driver'] === 'local') {
            return $config;
        }

        if (!empty($config['access_key'])) {
            $config['access_key'] = 'FCOM_ENCRYPTED_DATA_KEY';
        }

        if (!empty($config['secret_key'])) {
            $config['secret_key'] = 'FCOM_ENCRYPTED_DATA_KEY';
        }

        return $config;
    }
}
