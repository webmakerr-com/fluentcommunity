<?php

namespace FluentCommunityPro\App\Modules\CloudStorage;

use FluentCommunity\Framework\Support\Arr;
use FluentCommunityPro\App\Modules\CloudStorage\BunnyCDN\BunnyCdnDriver;
use FluentCommunityPro\App\Modules\CloudStorage\S3\CloudFlareDriver;
use FluentCommunityPro\App\Modules\CloudStorage\S3\S3Driver;

class CloudStorageModule
{
    public function register($app)
    {
        add_filter('fluent_community/media_upload_data', function ($mediaData) {
            $remoteDreiver = $this->getDriver();
            if (!$remoteDreiver) {
                unset($mediaData['acl']);
                return $mediaData;
            }

            $mediaPath = $mediaData['media_path'];

            $acl = Arr::get($mediaData, 'acl', 'public-read');

            try {
                $response = $remoteDreiver->putObject($mediaPath, $acl);
            } catch (\Exception $exception) {
                return $mediaData;
            }

            if (!$response || is_wp_error($response)) {
                if (is_wp_error($response)) {
                    return $response;
                }
                return $mediaData;
            }

            $mediaData['media_path'] = $response['remote_path'];
            $mediaData['media_url'] = $response['public_url'];
            $mediaData['driver'] = 's3';

            unset($mediaData['acl']);

            // unlink the old file now
            @unlink($mediaPath);

            return $mediaData;
        });

        add_action('fluent_community/delete_remote_media_s3', [$this, 'deleteRemoteMedia']);
        add_filter('fluent_community/media_signed_public_url_s3', [$this, 'maybeGetSignedUrl'], 10, 3);
    }

    public function deleteRemoteMedia($media)
    {
        $remoteDreiver = $this->getDriver();
        if (!$remoteDreiver) {
            return;
        }

        try {
            $remoteDreiver->deleteObject($media->media_path);
        } catch (\Exception $exception) {
            // do nothing for now
        }
    }

    public function getDriver()
    {
        return $this->getConnectionDriver();
    }

    public function getConnectionDriver($config = null)
    {
        if (!$config) {
            $config = StorageHelper::getConfig();
        }

        $driver = $config['driver'];
        if (!$driver) {
            return null;
        }


        switch ($driver):
            case 'local':
                return null;
            case 'cloudflare_r2':
                return $this->cloudflareDriver($config);
            case 'amazon_s3':
                return $this->s3Driver($config);
            case 'bunny_cdn':
                return $this->bunnyCdnDriver($config);
            default:
                return null;
        endswitch;

        return null;
    }

    private function cloudflareDriver($config = null)
    {
        if (!$config) {
            $config = StorageHelper::getConfig();
        }

        if (
            empty($config['access_key']) ||
            empty($config['secret_key']) ||
            !isset($config['bucket']) ||
            !isset($config['public_url']) ||
            !isset($config['account_id']) ||
            $config['driver'] != 'cloudflare_r2'
        ) {
            return null;
        }

        $endPoint = $config['account_id'] . '.r2.cloudflarestorage.com';

        $driver = (new CloudFlareDriver($config['access_key'], $config['secret_key'], $endPoint, $config['bucket']))
            ->setPublicUrl($config['public_url']);

        if (!empty($config['sub_folder'])) {
            $driver = $driver->setSubFolder($config['sub_folder']);
        }

        return $driver;
    }

    private function bunnyCdnDriver($config = null)
    {
        if (!$config) {
            $config = StorageHelper::getConfig();
        }

        if (
            empty($config['access_key']) ||
            empty($config['s3_endpoint']) ||
            empty($config['bucket']) ||
            $config['driver'] != 'bunny_cdn'
        ) {
            return null;
        }

        $apiUrl = "https://" . $config['s3_endpoint'] . '/' . $config['bucket'];

        $driver = (new BunnyCdnDriver($config['access_key'], $apiUrl, $config['bucket']))
            ->setPublicUrl(Arr::get($config, 'public_url'));

        return $driver;
    }

    private function s3Driver($config = null)
    {
        if (!$config) {
            $config = StorageHelper::getConfig();
        }

        if (
            empty($config['access_key']) ||
            empty($config['secret_key']) ||
            !isset($config['bucket']) ||
            $config['driver'] != 'amazon_s3'
        ) {
            return null;
        }

        $endPoint = defined('FLUENT_COMMUNITY_CLOUD_STORAGE_ENDPOINT') ? FLUENT_COMMUNITY_CLOUD_STORAGE_ENDPOINT : '';

        if (!$endPoint) {
            $endPoint = Arr::get($config, 's3_endpoint');
        }

        $region = defined('FLUENT_COMMUNITY_CLOUD_STORAGE_REGION') ? FLUENT_COMMUNITY_CLOUD_STORAGE_REGION : '';

        if (!$endPoint) {
            if ($region) {
                $endPoint = 's3-' . $region . '.amazonaws.com';
            } else {
                $endPoint = 's3.amazonaws.com';
            }
        }

        if (!$region && $endPoint != 's3.amazonaws.com') {
            $parts = explode('.', $endPoint);
            $region = substr($parts[0], 3);
        }

        $driver = new S3Driver($config['access_key'], $config['secret_key'], $endPoint, $config['bucket'], $region);

        if (!empty($config['sub_folder'])) {
            $driver = $driver->setSubFolder($config['sub_folder']);
        }

        return $driver;
    }

    public function maybeGetSignedUrl($url, $media, $expires = 3600)
    {
        $remoteDriver = $this->getDriver();
        if (!$remoteDriver) {
            return $url;
        }

        $signedUrl = $remoteDriver->getSignedUrl($media->media_path, $expires, $media);

        if (!$signedUrl || is_wp_error($signedUrl)) {
            return $url;
        }

        return $signedUrl;
    }
}
