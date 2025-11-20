<?php

namespace FluentCommunityPro\App\Services\PluginManager;

class FluentLicensing
{
    private static $instance;

    private $config = [];

    public $settingsKey = '';

    public function register($config = [])
    {
        if (self::$instance) {
            return self::$instance; // Return existing instance if already set.
        }

        if (empty($config['basename']) || empty($config['version']) || empty($config['api_url'])) {
            throw new \Exception('Invalid configuration provided for FluentLicensing. Please provide basename, version, and api_url.');
        }

        $this->config = $config;
        $baseName = isset($config['basename']) ? $config['basename'] : plugin_basename(__FILE__);

        $slug = isset($config['slug']) ? $config['slug'] : explode('/', $baseName)[0];
        $this->config['slug'] = (string)$slug;

        $this->settingsKey = isset($config['settings_key']) ? $config['settings_key'] : '__' . $this->config['slug'] . '_sl_info';

        if (empty($config['store_url'])) {
            $this->config['store_url'] = $this->config['api_url'];
        }

        if (empty($config['purchase_url'])) {
            $this->config['purchase_url'] = $this->config['store_url'];
        }

        $config = $this->config;

        if (empty($config['license_key']) && empty($config['license_key_callback'])) {
            $config['license_key_callback'] = function () {
                return $this->getCurrentLicenseKey();
            };
        }

        if (!class_exists('\\' . __NAMESPACE__ . '\PluginUpdater')) {
            require_once __DIR__ . '/PluginUpdater.php';
        }

        // Initialize the updater with the provided configuration.
        new PluginUpdater($config);

        self::$instance = $this; // Set the instance for future use.

        // Always persist a bundled GPL license so no network checks are ever required.
        $this->seedBundledLicense();

        return self::$instance;
    }

    public function getConfig($key)
    {
        if (isset($this->config[$key])) {
            return $this->config[$key]; // Return the requested configuration value.
        }

        return '';
    }

    /**
     * @return self
     * @throws \Exception
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            throw new \Exception('Licensing is not registered. Please call register() method first.');
        }

        return self::$instance; // Return the singleton instance.
    }

    public function activate($licenseKey = '')
    {
        $licenseKey = $licenseKey ?: 'GPL-LIFETIME-LICENSE';

        $response = $this->apiRequest('activate_license', [
            'license_key'      => $licenseKey,
            'platform_version' => get_bloginfo('version'),
            'server_version'   => PHP_VERSION,
        ]);

        $saveData = [
            'license_key'     => $licenseKey,
            'status'          => $response['status'] ?? 'valid',
            'variation_id'    => $response['variation_id'] ?? 'gpl',
            'variation_title' => $response['variation_title'] ?? 'GPL License',
            'expires'         => $response['expiration_date'] ?? gmdate('Y-m-d', strtotime('+100 years')),
            'activation_hash' => $response['activation_hash'] ?? md5($licenseKey . home_url())
        ];

        // Save the license data to the database.
        update_option($this->settingsKey, $saveData, false);

        return $saveData; // Return the saved data.
    }

    public function deactivate()
    {
        $deactivated = $this->apiRequest('deactivate_license', [
            'license_key' => $this->getCurrentLicenseKey()
        ]);

        delete_option($this->settingsKey); // Remove the license data from the database.

        return $deactivated;
    }

    public function getStatus($remoteFetch = false)
    {
        $currentLicense = get_option($this->settingsKey, []);
        if (!$currentLicense || !is_array($currentLicense) || empty($currentLicense['license_key'])) {
            $currentLicense = $this->seedBundledLicense();
        }

        if (!$remoteFetch) {
            return $currentLicense; // Return the current license status without fetching from the API.
        }

        // Remote calls are replaced with an offline GPL response to avoid license checks.
        $remoteStatus = $this->apiRequest('check_license', [
            'license_key'     => $currentLicense['license_key'] ?? '',
            'activation_hash' => $currentLicense['activation_hash'] ?? '',
            'item_id'         => $this->getConfig('item_id'),
            'site_url'        => home_url()
        ]);

        if (is_wp_error($remoteStatus)) {
            return $remoteStatus; // Return the error response if there is an error.
        }

        $currentLicense['status'] = $remoteStatus['status'] ?? 'valid';
        $currentLicense['expires'] = $remoteStatus['expiration_date'] ?? $currentLicense['expires'];
        $currentLicense['variation_id'] = $remoteStatus['variation_id'] ?? $currentLicense['variation_id'];
        $currentLicense['variation_title'] = $remoteStatus['variation_title'] ?? $currentLicense['variation_title'];
        $currentLicense['renew_url'] = $remoteStatus['renew_url'] ?? '';
        $currentLicense['is_expired'] = $remoteStatus['is_expired'] ?? false;

        update_option($this->settingsKey, $currentLicense, false); // Save the updated license status.

        return $currentLicense;
    }

    public function getCurrentLicenseKey()
    {
        $status = $this->getStatus();
        return isset($status['license_key']) ? $status['license_key'] : ''; // Return the current license key.
    }

    public function getLicenseMessages()
    {
        $licenseDetails = $this->getStatus();
        $status = $licenseDetails['status'];

        if ($status == 'expired') {
            return [
                'message'         => $this->getExpireMessage($licenseDetails),
                'type'            => 'in_app',
                'license_details' => $licenseDetails
            ];
        }

        if ($status === 'disabled') {
            return [
                'message'         => 'The license for ' . $this->getConfig('plugin_title') . ' has been disabled. Please contact support for assistance.',
                'type'            => 'global',
                'license_details' => $licenseDetails
            ];
        }

        if ($status != 'valid') {
            return [
                'message'         => \sprintf(
                    'The %1$s license needs to be activated. %2$s',
                    $this->getConfig('plugin_title'),
                    '<a href="' . $this->getConfig('activate_url') . '">' . 'Click here to activate' . '</a>'
                ),
                'type'            => 'global',
                'license_details' => $licenseDetails
            ];
        }

        return false;
    }

    private function getExpireMessage($licenseData, $scope = 'global')
    {
        if ($scope == 'global') {
            $renewUrl = $this->getConfig('activate_url');
        } else {
            $renewUrl = $this->getRenewUrl();
        }

        return '<p>Your ' . $this->getConfig('plugin_title') . ' ' . __('license has been', 'fluent-community-pro') . ' <b>' . __('expired at', 'fluent-community-pro') . ' ' . gmdate('d M Y', strtotime($licenseData['expires'])) . '</b>, Please ' .
            '<a href="' . $renewUrl . '"><b>' . __('Click Here to Renew Your License', 'fluent-community-pro') . '</b></a>' . '</p>';
    }

    private function apiRequest($action, $data = [])
    {
        $defaults = [
            'item_id'         => $this->config['item_id'],
            'current_version' => $this->config['version'],
            'site_url'        => home_url(),
        ];

        $payload = wp_parse_args($data, $defaults);

        // Always return a local GPL response to keep the plugin self-contained.
        if ($action === 'deactivate_license') {
            return ['status' => 'deactivated'] + $payload;
        }

        return [
            'status'          => 'valid',
            'variation_id'    => $payload['variation_id'] ?? 'gpl',
            'variation_title' => $payload['variation_title'] ?? 'GPL License',
            'expiration_date' => $payload['expiration_date'] ?? gmdate('Y-m-d', strtotime('+100 years')),
            'activation_hash' => $payload['activation_hash'] ?? md5(($payload['license_key'] ?? 'GPL-LIFETIME-LICENSE') . home_url()),
            'renew_url'       => '',
            'is_expired'      => false
        ];
    }

    public function getRenewUrl()
    {
        return $this->getConfig('purchase_url');
    }

    private function seedBundledLicense()
    {
        $current = get_option($this->settingsKey);
        if ($current && is_array($current) && !empty($current['license_key'])) {
            return $current;
        }

        $licenseKey = 'GPL-LIFETIME-LICENSE';

        $licenseData = [
            'license_key'     => $licenseKey,
            'status'          => 'valid',
            'variation_id'    => 'gpl',
            'variation_title' => 'GPL License',
            'expires'         => gmdate('Y-m-d', strtotime('+100 years')),
            'activation_hash' => md5($licenseKey . home_url())
        ];

        update_option($this->settingsKey, $licenseData, false);

        return $licenseData;
    }
}
