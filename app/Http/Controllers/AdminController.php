<?php

namespace FluentCommunity\App\Http\Controllers;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\BaseSpace;
use FluentCommunity\App\Services\AuthenticationService;
use FluentCommunity\App\Services\CustomSanitizer;
use FluentCommunity\App\Services\FeedsHelper;
use FluentCommunity\App\Services\ProfileHelper;
use FluentCommunity\Modules\Auth\AuthHelper;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\App\Services\OnboardingService;
use FluentCommunity\Framework\Http\Request\Request;
use FluentCommunity\Framework\Support\Arr;

class AdminController extends Controller
{
    public function getGeneralSettings(Request $request)
    {
        $settings = Helper::generalSettings(false);

        $userRoles = wp_roles()->roles;

        $formatedRoles = [];
        foreach ($userRoles as $role => $roleData) {
            $formatedRoles[$role] = $roleData['name'];
        }

        unset($formatedRoles['administrator']);

        return [
            'settings'                     => $settings,
            'user_roles'                   => $formatedRoles,
            'users_can_register'           => !!get_option('users_can_register'),
            'user_registration_enable_url' => admin_url('options-general.php')
        ];
    }

    public function saveGeneralSettings(Request $request)
    {
        $inputs = $request->get('settings', []);
        $settings = Helper::generalSettings(false);
        $inputs = Arr::only($inputs, array_keys($settings));

        $settings['logo'] = Arr::get($inputs, 'logo', '');
        $settings['white_logo'] = Arr::get($inputs, 'white_logo', '');
        $settings['featured_image'] = Arr::get($inputs, 'featured_image', '');

        if (!empty($settings['logo'])) {
            $settings['logo'] = sanitize_url($settings['logo']);
        }

        if (!empty($settings['white_logo'])) {
            $settings['white_logo'] = sanitize_url($settings['white_logo']);
        }

        if (!empty($settings['featured_image'])) {
            $settings['featured_image'] = sanitize_url($settings['featured_image']);
        }

        if (!empty($settings['site_title'])) {
            $settings['site_title'] = sanitize_text_field(Arr::get($inputs, 'site_title', ''));
        }

        $settings['disable_global_posts'] = sanitize_text_field(Arr::get($inputs, 'disable_global_posts', 'no'));
        $settings['access'] = Arr::get($inputs, 'access', []);
        $settings['auth_content'] = wp_kses_post(Arr::get($inputs, 'auth_content', ''));
        $settings['auth_redirect'] = sanitize_url(Arr::get($inputs, 'auth_redirect', ''));
        $settings['restricted_role_content'] = wp_kses_post(Arr::get($inputs, 'restricted_role_content', ''));
        $settings['logo_permalink'] = sanitize_url(Arr::get($inputs, 'logo_permalink', ''));
        $settings['logo_permalink_type'] = sanitize_text_field(Arr::get($inputs, 'logo_permalink_type', 'default'));
        $settings['auth_url'] = sanitize_url(Arr::get($inputs, 'auth_url', ''));
        $media = Helper::getMediaFromUrl($settings['logo']);
        $whiteMedia = Helper::getMediaFromUrl($settings['white_logo']);
        $featuredMedia = Helper::getMediaFromUrl($settings['featured_image']);
        $settings['cutsom_auth_url'] = sanitize_url(Arr::get($inputs, 'cutsom_auth_url', ''));
        $settings['auth_form_type'] = sanitize_text_field(Arr::get($inputs, 'auth_form_type', ''));
        $settings['explicit_registration'] = sanitize_text_field(Arr::get($inputs, 'explicit_registration', 'no'));

        $isCustomSignupPage = $settings['auth_form_type'] === 'default' && Arr::get($inputs, 'use_custom_signup_page', 'no') == 'yes';
        if (!$isCustomSignupPage) {
            $settings['custom_signup_url'] = '';
            $settings['use_custom_signup_page'] = 'no';
        } else {
            $settings['use_custom_signup_page'] = 'yes';

            $url = Arr::get($inputs, 'custom_signup_url', '');

            if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
                return $this->sendError([
                    'message' => __('Please provide a valid signup URL', 'fluent-community')
                ]);
            }
            $settings['custom_signup_url'] = $url;
        }

        if ($media) {
            $settings['logo'] = $media->public_url;
            $media->update([
                'is_active'     => true,
                'user_id'       => get_current_user_id(),
                'object_source' => 'general'
            ]);
        }

        if ($whiteMedia) {
            $settings['white_logo'] = $whiteMedia->public_url;
            $whiteMedia->update([
                'is_active'     => true,
                'user_id'       => get_current_user_id(),
                'object_source' => 'general'
            ]);
        }

        if ($featuredMedia) {
            $settings['featured_image'] = $featuredMedia->public_url;
            $featuredMedia->update([
                'is_active'     => true,
                'user_id'       => get_current_user_id(),
                'object_source' => 'general'
            ]);
        }

        $slugChanged = false;
        if ($settings['slug'] != $inputs['slug'] && !defined('FLUENT_COMMUNITY_PORTAL_SLUG')) {
            $settings['slug'] = $inputs['slug'];
            $slugChanged = true;
        }

        update_option('fluent_community_settings', $settings);

        $redirectUrl = '';

        if ($slugChanged) {
            // flush rewrite rules
            flush_rewrite_rules(true);
            Helper::generalSettings(false);
            $redirectUrl = Helper::baseUrl('admin/settings/general');
        }

        return [
            'message'      => __('Settings have been saved successfully.', 'fluent-community'),
            'redirect_url' => $redirectUrl
        ];
    }

    public function getEmailSettings(Request $request)
    {
        $emailSettings = Utility::getEmailNotificationSettings();
        $globalSettings = Helper::generalSettings(false);
        if (empty($emailSettings['logo'])) {
            $emailSettings['global_logo'] = $globalSettings['logo'];
        }

        return [
            'email_settings' => $emailSettings
        ];
    }

    public function saveEmailSettings(Request $request)
    {
        $prevSettings = Utility::getEmailNotificationSettings();
        $newSettings = $request->get('email_settings', []);

        $logo = Arr::get($newSettings, 'logo', '');

        if ($logo) {
            $logoMedia = Helper::getMediaFromUrl($logo);
            if ($logoMedia) {
                $newSettings['logo'] = $logoMedia->public_url;
                $logoMedia->update([
                    'is_active'     => true,
                    'user_id'       => get_current_user_id(),
                    'object_source' => 'general'
                ]);
            }
        }

        $newSettings = wp_parse_args($newSettings, $prevSettings);
        $newSettings = CustomSanitizer::santizeEmailSettings($newSettings);

        Utility::updateOption('global_email_settings', $newSettings);

        /*
         * We are unscheduling all digest schedules if the settings has been changed or disabled
         */
        $isScheduleChanged = ($newSettings['digest_mail_day'] != $prevSettings['digest_mail_day']) || ($newSettings['daily_digest_time'] != $prevSettings['daily_digest_time']);
        if ($isScheduleChanged) {
            if (as_next_scheduled_action('fluent_community_send_daily_digest_init')) {
                as_unschedule_all_actions('fluent_community_send_daily_digest_init', [], 'fluent-community');
            }
        }

        return [
            'email_settings' => $newSettings,
            'message'        => __('Email notification settings have been updated', 'fluent-community')
        ];
    }

    public function getStorageSettings(Request $request)
    {
        if (!defined('FLUENT_COMMUNITY_PRO')) {
            $config = [
                'driver' => 'local'
            ];
        } else {
            $config = \FluentCommunityPro\App\Modules\CloudStorage\StorageHelper::getConfig('view');
        }

        return apply_filters('fluent_community/storage_settings_response', [
            'config' => $config
        ]);
    }

    public function updateStorageSettings(Request $request)
    {
        if (!defined('FLUENT_COMMUNITY_PRO')) {
            return $this->sendError([
                'message' => __('Sorry, you can not update this config. Please activate pro', 'fluent-community')
            ]);
        }

        if (defined('FLUENT_COMMUNITY_CLOUD_STORAGE') && FLUENT_COMMUNITY_CLOUD_STORAGE) {
            return $this->sendError([
                'message' => __('You can not update the storage settings as it is defined in the config file', 'fluent-community')
            ]);
        }

        $config = $request->get('config', []);

        $driver = Arr::get($config, 'driver', 'local');

        $validation = [
            'driver' => 'required'
        ];

        $isRemote = in_array($driver, ['amazon_s3', 'bunny_cdn', 'cloudflare_r2']);

        if ($driver == 'cloudflare_r2') {
            $validation = [
                'driver'     => 'required',
                'access_key' => 'required',
                'secret_key' => 'required',
                'bucket'     => 'required',
                'public_url' => 'required|url',
                'account_id' => 'required',
            ];
        } else if ($driver == 'amazon_s3') {
            $validation = [
                'driver'     => 'required',
                'access_key' => 'required',
                'secret_key' => 'required',
                'bucket'     => 'required'
            ];
        } else if ($driver == 'bunny_cdn') {
            $validation = [
                'driver'      => 'required',
                'access_key'  => 'required',
                's3_endpoint' => 'required',
                'bucket'      => 'required',
                'public_url'  => 'required|url'
            ];
        }

        $this->validate($config, $validation);

        if ($isRemote) {
            $previousConfig = \FluentCommunityPro\App\Modules\CloudStorage\StorageHelper::getConfig();
            if ($config['access_key'] == 'FCOM_ENCRYPTED_DATA_KEY') {
                $config['access_key'] = Arr::get($previousConfig, 'access_key');
            }

            if ($config['secret_key'] == 'FCOM_ENCRYPTED_DATA_KEY') {
                $config['secret_key'] = Arr::get($previousConfig, 'secret_key');
            }

            $driver = (new \FluentCommunityPro\App\Modules\CloudStorage\CloudStorageModule)->getConnectionDriver($config);

            if (!$driver) {
                return $this->sendError([
                    'message' => __('Could not connect to the remote storage service. Please check your credentials', 'fluent-community')
                ]);
            }

            $test = $driver->testConnection();
            if (!$test || is_wp_error($test)) {
                return $this->sendError([
                    'message' => __('Could not connect to the remote storage service. Error: ', 'fluent-community') . is_wp_error($test) ? $test->get_error_message() : 'Unknow Error'
                ]);
            }
        }

        $config = Arr::only($config, [
            'driver',
            'access_key',
            'secret_key',
            'bucket',
            'public_url',
            'account_id',
            'sub_folder',
            's3_endpoint'
        ]);

        if ($config['driver'] == 'local') {
            $config = [
                'driver' => 'local'
            ];

            $featureConfig = Utility::getFeaturesConfig();
            $featureConfig['cloud_storage'] = 'no';
            Utility::updateOption('fluent_community_features', $featureConfig);
        } else {
            $featureConfig = Utility::getFeaturesConfig();
            $featureConfig['cloud_storage'] = 'yes';
            Utility::updateOption('fluent_community_features', $featureConfig);
        }

        $config = array_filter($config);
        \FluentCommunityPro\App\Modules\CloudStorage\StorageHelper::updateConfig($config);

        return [
            'message' => __('Storage settings have been updated successfully', 'fluent-community')
        ];
    }

    public function getWelcomeBannerSettings(Request $request)
    {
        $settings = apply_filters('fluent_community/get_welcome_banner_settings', Helper::getWelcomeBannerSettings());

        return [
            'settings' => $settings
        ];
    }

    public function updateWelcomeBannerSettings(Request $request)
    {
        $settings = $request->get('settings', []);

        if (!empty($settings['login']['description'])) {
            $description = wp_unslash($settings['login']['description']);
            $settings['login']['description'] = CustomSanitizer::unslashMarkdown($description);
        }

        if (!empty($settings['logout']['description'])) {
            $description = wp_unslash($settings['logout']['description']);
            $settings['logout']['description'] = CustomSanitizer::unslashMarkdown($description);
        }

        $settings = CustomSanitizer::sanitizeWelcomeBannerSettings($settings);

        if (Arr::get($settings, 'login.enabled') == 'yes') {
            $settings['login']['description_rendered'] = wp_kses_post(FeedsHelper::mdToHtml(Arr::get($settings, 'login.description')));
        }

        if (Arr::get($settings, 'logout.enabled') == 'yes') {
            $settings['logout']['description_rendered'] = wp_kses_post(FeedsHelper::mdToHtml(Arr::get($settings, 'logout.description')));
            if (Arr::get($settings, 'logout.buttonLabel') && Arr::get($settings, 'logout.useCustomUrl') != 'yes') {
                $settings['logout']['buttonLink'] = Helper::getAuthUrl();
            }
        }

        $settings = apply_filters('fluent_community/update_welcome_banner_settings', $settings);

        Utility::updateOption('welcome_banner_settings', $settings);

        Utility::setCache('welcome_banner_settings', $settings, WEEK_IN_SECONDS);

        return [
            'message'  => __('Welcome banner settings have been updated successfully', 'fluent-community'),
            'settings' => $settings
        ];
    }

    public function getAuthSettings()
    {
        $settings = AuthenticationService::getAuthSettings();

        $settings['login']['form']['fields'] = AuthHelper::getLoginFormFields();
        $settings['signup']['form']['fields'] = AuthHelper::getFormFields();

        $settings = apply_filters('fluent_community/get_auth_settings', $settings);

        return [
            'settings' => $settings
        ];
    }

    public function getOnBoardingSettings()
    {
        $settings = Helper::generalSettings();

        $currentUser = get_user_by('ID', get_current_user_id());

        $settings['has_fluentcrm'] = defined('FLUENTCRM') ? 'yes' : 'no';
        $settings['has_fluentsmtp'] = defined('FLUENTMAIL_PLUGIN_FILE') ? 'yes' : 'no';
        $settings['has_fluentcart'] = defined('FLUENTCART_VERSION') ? 'yes' : 'no';
        $settings['template'] = '';
        $settings['install_fluentcrm'] = 'yes';
        $settings['install_fluentsmtp'] = 'yes';
        $settings['install_fluentcart'] = 'yes';
        $settings['subscribe_to_newsletter'] = 'yes';
        $settings['share_data'] = 'no';
        if ($currentUser) {
            $settings['user_full_name'] = $currentUser->first_name ? $currentUser->first_name . ' ' . $currentUser->last_name : $currentUser->display_name;
            $settings['user_email_address'] = $currentUser->user_email;
        } else {
            $settings['user_full_name'] = '';
            $settings['user_email_address'] = '';
        }

        return [
            'settings' => $settings
        ];
    }


    public function saveOnBoardingSettings(Request $request)
    {
        $inputs = $request->get('settings', []);
        $settings = Helper::generalSettings();

        if (Arr::get($inputs, 'site_title')) {
            $settings['site_title'] = sanitize_text_field(Arr::get($inputs, 'site_title'));
        }

        $logo = Arr::get($inputs, 'logo');

        if ($logo) {
            $media = Helper::getMediaFromUrl($logo);
            if ($media) {
                $settings['logo'] = $media->public_url;
                $media->update([
                    'is_active'     => true,
                    'user_id'       => get_current_user_id(),
                    'object_source' => 'onboarding'
                ]);
            }
        }

        if (Arr::get($inputs, 'slug') && empty($settings['is_slug_defined'])) {
            $settings['slug'] = sanitize_title(Arr::get($inputs, 'slug'));
        }
        update_option('fluent_community_settings', $settings, 'yes');

        // Email Subscription Settings
        $subscriptionSettings = array_filter([
            'user_id'                 => get_current_user_id(),
            'subscribe_to_newsletter' => sanitize_text_field(Arr::get($inputs, 'subscribe_to_newsletter', 'no')),
            'share_data'              => sanitize_text_field(Arr::get($inputs, 'share_data', 'no')),
            'user_full_name'          => sanitize_text_field(Arr::get($inputs, 'user_full_name', '')),
            'user_email_address'      => sanitize_email(Arr::get($inputs, 'user_email_address', ''))
        ]);

        Utility::updateOption('onboarding_sub_settings', $subscriptionSettings);
        OnboardingService::maybeCreateSpaceTemplates(Arr::get($inputs, 'template'));

        $installableAddons = array_keys(array_filter([
            'fluent-crm'  => Arr::get($inputs, 'install_fluentcrm', 'no') == 'yes',
            'fluent-smtp' => Arr::get($inputs, 'install_fluentsmtp', 'no') == 'yes',
            'fluent-cart' => Arr::get($inputs, 'install_fluentcart', 'no') == 'yes',
        ]));

        // Install Plugins which are checked
        OnboardingService::installAddons($installableAddons);

        // Maybe Optin User to Newsletter
        OnboardingService::maybeOptinUserToNewsletter($subscriptionSettings);

        // flash the permalinks
        flush_rewrite_rules(true);

        return [
            'message' => __('Onboarding settings have been updated.', 'fluent-community')
        ];
    }

    public function changePortalSlug(Request $request)
    {
        $newSlug = sanitize_title($request->get('new_slug', ''));

        if (!$newSlug) {
            return $this->sendError([
                'message' => __('Slug can not be empty', 'fluent-community')
            ]);
        }

        if (defined('FLUENT_COMMUNITY_PORTAL_SLUG')) {
            return $this->sendError([
                'message' => __('You can not change the slug as it is defined in the config file', 'fluent-community')
            ]);
        }

        $settings = Helper::generalSettings();

        if ($settings['slug'] != $newSlug) {
            $settings['slug'] = $newSlug;

            update_option('fluent_community_settings', $settings);
            flush_rewrite_rules(true);

            $slug = $settings['slug'];
            add_rewrite_rule('^' . $slug . '/?$', 'index.php?fcom_route=portal_home', 'top'); // For /hooks
            add_rewrite_rule('^' . $slug . '/(.+)/?', 'index.php?fcom_route=$matches[1]', 'top');
            // flush rewrite rules
            flush_rewrite_rules(true);

            delete_option('rewrite_rules');
        }

        return [
            'message' => __('Slug has been changed successfully', 'fluent-community')
        ];
    }

    public function getProfileLinkProviders(Request $request)
    {
        return [
            'providers' => ProfileHelper::socialLinkProviders()
        ];
    }

    public function updateProfileLinkProviders(Request $request)
    {
        $providerKeys = array_keys(ProfileHelper::socialLinkProviders());
        $config = $request->get('configs', []);

        $config = array_filter($config, function ($value) use ($providerKeys) {
            return in_array($value, $providerKeys);
        });

        do_action('fluent_community/update_profile_link_providers', $config);

        return [
            'message' => __('Profile link providers have been updated successfully', 'fluent-community'),
        ];
    }

    public function getAllSpaceCourses(Request $request)
    {
        return [
            'all_spaces' => BaseSpace::query()->withoutGlobalScopes()
                ->whereIn('type', ['community', 'course'])
                ->orderBy('serial', 'ASC')->get()
        ];
    }

}
