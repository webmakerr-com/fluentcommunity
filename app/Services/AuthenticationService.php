<?php

namespace FluentCommunity\App\Services;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\Modules\Auth\AuthHelper;

class AuthenticationService
{
    public static function getAuthForm($view = 'login')
    {
        $settings = self::getAuthSettings();

        return Arr::get($settings, $view, []);
    }

    public static function getAuthSettings()
    {
        $siteSettings = Helper::generalSettings();

        $siteLogo = Arr::get($siteSettings, 'logo');
        $siteTitle = Arr::get($siteSettings, 'site_title');

        $defaults = [
            'login'  => [
                'banner' => [
                    'hidden'           => false,
                    'type'             => 'banner',
                    'position'         => 'left',
                    'logo'             => $siteLogo,
                    /* translators: %s is replaced by the title of the site */
                    'title'            => sprintf(__('Welcome to %s', 'fluent-community'), $siteTitle),
                    'description'      => __('Join our community and start your journey to success', 'fluent-community'),
                    'title_color'      => '#19283a',
                    'text_color'       => '#525866',
                    'background_image' => '',
                    'background_color' => '#F5F7FA'
                ],
                'form'   => [
                    'type'               => 'form',
                    'position'           => 'right',
                    /* translators: %s is replaced by the title of the site */
                    'title'              => sprintf(__('Login to %s', 'fluent-community'), $siteTitle),
                    'description'        => __('Enter your email and password to login', 'fluent-community'),
                    'title_color'        => '#19283a',
                    'text_color'         => '#525866',
                    'button_label'       => __('Login', 'fluent-community'),
                    'button_color'       => '#2B2E33',
                    'button_label_color' => '#ffffff',
                    'background_image'   => '',
                    'background_color'   => '#ffffff'
                ]
            ],
            'signup' => [
                'banner' => [
                    'hidden'           => false,
                    'type'             => 'banner',
                    'position'         => 'left',
                    'logo'             => $siteLogo,
                    /* translators: %s is replaced by the title of the site */
                    'title'            => sprintf(__('Welcome to %s', 'fluent-community'), $siteTitle),
                    'description'      => __('Join our community and start your journey to success', 'fluent-community'),
                    'title_color'      => '#19283a',
                    'text_color'       => '#525866',
                    'background_image' => '',
                    'background_color' => '#F5F7FA',
                ],
                'form'   => [
                    'type'               => 'form',
                    'position'           => 'right',
                    /* translators: %s is replaced by the title of the site */
                    'title'              => sprintf(__('Sign Up to %s', 'fluent-community'), $siteTitle),
                    'description'        => __('Create an account to get started', 'fluent-community'),
                    'button_label'       => __('Sign up', 'fluent-community'),
                    'terms_label'        => '',
                    'title_color'        => '#19283a',
                    'text_color'         => '#525866',
                    'button_color'       => '#2B2E33',
                    'button_label_color' => '#ffffff',
                    'background_image'   => '',
                    'background_color'   => '#ffffff',
                ]
            ]
        ];

        $settings = Utility::getOption('auth_settings', []);

        $authSettings = wp_parse_args($settings, $defaults);

        if (!Arr::get($authSettings, 'signup.form.fields.terms')) {
            $termsText = AuthHelper::getTermsText();
            $authSettings['signup']['form']['fields']['terms'] = [
                'disabled'     => false,
                'required'     => true,
                'type'         => 'inline_checkbox',
                'label'        => Helper::htmlToMd($termsText),
                'inline_label' => $termsText
            ];
        }

        return apply_filters('fluent_community/auth/settings', $authSettings);
    }

    public static function getFormattedAuthSettings($view = 'login')
    {
        $authSettings = self::getAuthSettings();
        $settings = Arr::get($authSettings, $view, []);

        foreach ($settings as &$setting) {
            if (Arr::get($setting, 'description_rendered')) {
                $setting['description'] = $setting['description_rendered'];
                unset($setting['description_rendered']);
            }
        }

        return $settings;
    }

    public static function formatAuthSettings($settingFields)
    {
        $currentSettings = self::getAuthSettings();

        $textFields = ['type', 'title', 'button_label', 'position', 'title_color', 'text_color', 'button_color', 'button_label_color', 'background_color'];
        $mediaFields = ['logo', 'background_image'];

        $formattedFields = [];
        foreach ($settingFields as $section => $settings) {
            foreach ($settings as $key => $setting) {
                $textValues = array_map('sanitize_text_field', Arr::only($setting, $textFields));
                $mediaUrls = array_map('sanitize_url', Arr::only($setting, $mediaFields));

                $mediaUrls = self::handleMediaUrls($mediaUrls, $currentSettings[$section][$key], $section);

                $formattedField = array_merge($textValues, $mediaUrls);

                $formattedField['description'] = wp_kses_post(wp_unslash(Arr::get($setting, 'description')));

                $formattedField['description_rendered'] = wp_kses_post(FeedsHelper::mdToHtml($formattedField['description']));

                $formattedField['hidden'] = Arr::isTrue($setting, 'hidden');

                $formattedFields[$section][$key] = $formattedField;
            }
        }

        if (Arr::get($settingFields, 'signup.form.fields.terms')) {
            $termsField = Arr::get($settingFields, 'signup.form.fields.terms');
            $termsField['disabled'] = Arr::isTrue($termsField, 'disabled');
            $termsField['required'] = Arr::isTrue($termsField, 'required');
            $termsField['label'] = wp_kses_post(wp_unslash($termsField['label']));
            $termsField['inline_label'] = wp_kses_post(FeedsHelper::mdToHtml(wp_unslash($termsField['label'])));
            $formattedFields['signup']['form']['fields']['terms'] = $termsField;
        }

        return $formattedFields;
    }

    public static function getCustomSignupPageUrl()
    {
        $portalSettings = Helper::generalSettings();

        return Arr::get($portalSettings, 'custom_signup_url');
    }

    protected static function handleMediaUrls($mediaUrls, $currentSetting, $section)
    {
        foreach ($mediaUrls as $key => $url) {
            $currentImgUrl = Arr::get($currentSetting, $key);
            if ($url) {
                $media = Helper::getMediaFromUrl($url);
                if (!$media || $media->is_active) {
                    $mediaUrls[$key] = $currentImgUrl;
                    continue;
                }

                $mediaUrls[$key] = $media->public_url;

                $media->update([
                    'is_active'     => true,
                    'user_id'       => get_current_user_id(),
                    'sub_object_id' => null,
                    'object_source' => 'auth_' . $section . '_' . $key
                ]);
            }
        }

        return $mediaUrls;
    }

}
