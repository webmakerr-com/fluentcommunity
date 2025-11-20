<?php

namespace FluentCommunityPro\App\Http\Controllers;

use FluentCommunity\Framework\Http\Request\Request;
use FluentCommunityPro\App\Services\PluginManager\FluentLicensing;

class LicenseController extends Controller
{
    public function getStatus(Request $request)
    {
        $licenseManager = FluentLicensing::getInstance();

        $data = $licenseManager->getStatus(true);

        if (is_wp_error($data)) {
            return [
                'status'  => 'invalid',
                'message' => $data->get_error_message(),
            ];
        }

        $status = $data['status'];

        if ('expired' == $status && empty($data['renew_url'])) {
            $data['renew_url'] = $licenseManager->getRenewUrl();
        }

        $data['purchase_url'] = $licenseManager->getConfig('purchase_url');

        unset($data['license_key']);

        return $data;
    }

    public function saveLicense(Request $request)
    {
        $licenseKey = $request->get('license_key');

        $licenseManager = FluentLicensing::getInstance();

        $response = $licenseManager->activate($licenseKey);

        if (is_wp_error($response)) {
            return $this->sendError([
                'message' => $response->get_error_message(),
                'remote_response' => $response,
            ]);
        }

        return [
            'license_data' => $response,
            'message'      => __('Your license key has been successfully updated', 'fluent-community-pro'),
        ];
    }

    public function deactivateLicense(Request $request)
    {

        $licenseManager = FluentLicensing::getInstance();

        $response = $licenseManager->deactivate();

        if (is_wp_error($response)) {
            return $this->sendError([
                'message' => $response->get_error_message(),
            ]);
        }

        unset($response['license_key']);

        return [
            'license_data' => $response,
            'message'      => __('Your license key has been successfully deactivated', 'fluent-community-pro'),
        ];
    }

}
