<?php

namespace FluentCommunityPro\App\Modules\CloudStorage\BunnyCDN;


use FluentCommunity\Framework\Support\Arr;
use FluentCommunityPro\App\Modules\CloudStorage\S3\Helper;

class BunnyCdnDriver
{

    protected $accessKey;

    protected $endpoint;

    protected $bucket;

    protected $publicUrl;

    public function __construct($accessKey, $endPoint, $bucket)
    {
        $this->bucket = $bucket;
        $this->accessKey = $accessKey;
        $this->endpoint = $endPoint;
    }

    public function getDriverName()
    {
        return 'bunny_cdn';
    }

    public function getDriver()
    {
        return $this;
    }

    public function setSubFolder($subFolder)
    {
        $this->subFolder = $subFolder;
        return $this;
    }

    public function setPublicUrl($url)
    {
        $this->publicUrl = $url;
        return $this;
    }

    public function putObject($mediaPath, $acl = 'public-read')
    {
        $fileName = basename($mediaPath);
        $response = $this->uploadFileRequest($mediaPath, $fileName);

        if (Arr::get($response, 'code') > 299) {
            return new \WP_Error('bunnycdn_error', 'Error uploading file to BunnyCDN', $response);
        }

        return [
            'public_url'  => $this->getPublicUrl($fileName),
            'remote_path' => $this->getRemotePath($fileName)
        ];
    }

    public function getPublicUrl($objectName)
    {
        if (!$this->publicUrl) {
            return new \WP_Error('public_url_not_set', 'Public URL not set', []);
        }

        return $this->publicUrl . '/' . $objectName;
    }

    public function getSignedUrl($path, $ttl = 3600, $media = null)
    {
        return null;
    }

    public function getRemotePath($objectName)
    {
        return 'bunny://' . str_replace('https://', '', $this->endpoint) . '/' . $objectName;
    }

    public function deleteObject($path)
    {
        // check if it stats with r2://
        if (strpos($path, 'bunny://') !== 0) {
            return new \WP_Error('invalid_path', 'Invalid path', []);
        }

        $response = $this->deleteFileReguest($path);

        if (Arr::get($response, 'code') > 299) {
            return new \WP_Error('bunnycdn_error', 'Error deleting file from BunnyCDN', $response);
        }

        return true;
    }

    public function testConnection()
    {
        $response = $this->listFiles();

        if (Arr::get($response, 'code') > 299) {
            return new \WP_Error('bunnycdn_error', 'Error connecting to BunnyCDN', $response);
        }

        return true;
    }

    private function uploadFileRequest($filePath, $fileName)
    {
        $url = $this->endpoint . '/' . $fileName;

        $args = array(
            'method'      => 'PUT',
            'timeout'     => 60,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => array(
                'AccessKey'    => $this->accessKey,
                'Content-Type' => 'application/octet-stream',
            ),
            'body'        => file_get_contents($filePath),
        );

        $response = wp_remote_request($url, $args);

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        return array(
            'code' => $code,
            'body' => $body,
        );
    }

    private function deleteFileReguest($remoteFilePath)
    {
        $remoteFilePath = str_replace('bunny://', 'https://', $remoteFilePath);

        $args = array(
            'method'      => 'DELETE',
            'timeout'     => 60,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => array(
                'AccessKey' => $this->accessKey,
            ),
        );

        $response = wp_remote_request($remoteFilePath, $args);

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        return array(
            'code' => $code,
            'body' => $body,
        );
    }

    private function listFiles()
    {
        $url = $this->endpoint . '/';

        $args = array(
            'method'      => 'GET',
            'timeout'     => 60,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => array(
                'AccessKey'    => $this->accessKey,
                'Content-Type' => 'application/json',
            ),
        );

        $response = wp_remote_request($url, $args);

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        return array(
            'code' => $code,
            'body' => $body,
        );
    }
}
