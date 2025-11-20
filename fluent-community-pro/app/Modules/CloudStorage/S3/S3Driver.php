<?php

namespace FluentCommunityPro\App\Modules\CloudStorage\S3;

use FluentCommunityPro\App\Modules\CloudStorage\S3\Helper;
use FluentCommunityPro\App\Modules\CloudStorage\S3\RemoteDriver;
use FluentCommunityPro\App\Modules\CloudStorage\S3\S3;

class S3Driver extends RemoteDriver
{

    private $subFolder = '';

    public function __construct($accessKey, $secretKey, $endpoint, $bucket, $region = 'us-east-1')
    {
        parent::__construct($accessKey, $secretKey, $endpoint, $bucket, $region);
    }

    public function setSubFolder($subFolder)
    {
        $this->subFolder = $subFolder;
        return $this;
    }

    public function putObject($mediaPath, $acl = 'public-read')
    {
        $inputFile = Helper::inputFile($mediaPath);
        if (!$inputFile) {
            return new \WP_Error('file_not_found', 'File not found', []);
        }

        $s3Driver = $this->getDriver();

        $objectName = basename($mediaPath);

        if ($this->subFolder) {
            $objectName = $this->subFolder . '/' . $objectName;
        }

        $response = $s3Driver::putObject($inputFile, $this->bucket, $objectName, $acl);

        if (!$response || (empty($response->code) || $response->code != 200)) {
            $error = [];
            $errorMessage = 'Unknown error occurred when uploading to s3';
            if (!empty($response->error)) {
                $error = $response->error;
                if (is_array($error) && !empty($error['message'])) {
                    $errorMessage = $error['message'];
                }
            }

            return new \WP_Error('s3_error', $errorMessage, $error);
        }

        return [
            'public_url'  => $this->getPublicUrl($objectName),
            'remote_path' => $this->getRemotePath($objectName)
        ];
    }

    public function getPublicUrl($objectName)
    {
        return 'https://' . $this->bucket . '.' . $this->endpoint . '/' . $objectName;
    }

    public function getSignedUrl($path, $ttl = 3600, $media = null)
    {
        $remotePath = 's3://' . $this->bucket . '.' . $this->endpoint . '/';
        $objectName = str_replace($remotePath, '', $path);

        // get signed url
        $s3Driver = $this->getDriver();

        return $s3Driver::getAuthenticatedURL($this->bucket, $objectName, $ttl);
    }

    public function getRemotePath($objectName)
    {
        return 's3://' . $this->bucket . '.' . $this->endpoint . '/' . $objectName;
    }

    public function deleteObject($path)
    {
        $s3Driver = $this->getDriver();

        // Normalize $path to just the object key
        $remotePrefix = 's3://' . $this->bucket . '.' . $this->endpoint . '/';
        if (strpos($path, $remotePrefix) === 0) {
            $objectKey = substr($path, strlen($remotePrefix));
        } else {
            $objectKey = ltrim($path, '/');
        }

        if ($this->subFolder && strpos($objectKey, $this->subFolder . '/') !== 0) {
            $objectKey = $this->subFolder . '/' . $objectKey;
        }

        $s3Driver::deleteObject($this->bucket, $objectKey);
    }

    public function testConnection()
    {
        // get files from the bucket
        $s3Driver = $this->getDriver();

        try {
            $s3Driver::getBucket($this->bucket, null, null, 1);
        } catch (\Exception $exception) {
            return new \WP_Error('s3_error', $exception->getMessage(), []);
        }

        return true;
    }
}
