<?php

namespace FluentCommunity\App\Services;

use FluentCommunity\App\Models\BaseSpace;
use FluentCommunity\Framework\Support\Arr;

class LockscreenService
{
    public static function getLockscreenSettings(BaseSpace $space, $viewOnly = false)
    {
        $defaultSettings = [
            [
                'hidden'            => false,
                'type'              => 'image',
                'label'             => 'Banner',
                'name'              => 'banner',
                'heading'           => 'Banner Heading',
                'heading_color'     => '#FFFFFF',
                'description'       => 'Banner Description',
                'text_color'        => '#FFFFFF',
                'button_text'       => 'Buy Now',
                'button_link'       => '',
                'button_color'      => '#2B2E33',
                'button_text_color' => '#FFFFFF',
                'background_image'  => '',
                'overlay_color'     => '#798398',
                'new_tab'           => 'no'
            ],
            [
                'hidden'  => false,
                'type'    => 'block',
                'label'   => 'Description',
                'name'    => 'description',
                'content' => '<!-- wp:paragraph --><p>Description</p><!-- /wp:paragraph -->',
            ]
        ];

        if ($space->isCourseSpace()) {
            $defaultSettings[] = [
                'hidden' => true,
                'type'   => 'lesson',
                'label'  => 'Lessons',
                'name'   => 'lesson'
            ];
        }

        $defaultSettings[] = [
            'hidden'            => false,
            'type'              => 'image',
            'label'             => 'Call to action',
            'name'              => 'action',
            'heading'           => 'Call to Action Heading',
            'heading_color'     => '#FFFFFF',
            'description'       => 'Call to Action Description',
            'text_color'        => '#FFFFFF',
            'button_text'       => 'Buy Now',
            'button_link'       => '',
            'button_color'      => '#2B2E33',
            'button_text_color' => '#FFFFFF',
            'background_image'  => '',
            'overlay_color'     => '#798398',
            'new_tab'           => 'no'
        ];

        $settings = (array) $space->getCustomMeta('lockscreen_settings', $defaultSettings);

        foreach ($settings as &$setting) {
            if ($viewOnly && $setting['type'] === 'block' && !empty($setting['content'])) {
                $user = Helper::getCurrentUser();
                $content = apply_filters('the_content', $setting['content']); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                $setting['content'] = (new SmartCodeParser())->parse($content, $user);
            }

            if ($setting['type'] == 'image' && empty($setting['new_tab'])) {
                $setting['new_tab'] = 'no';
            }
        }

        return apply_filters('fluent_community/lockscreen_fields', $settings, $space);
    }

    public static function formatLockscreenFields($settingFields, $space)
    {
        $currentSettings = self::getLockscreenSettings($space);

        $textFields = ['type', 'name', 'label', 'heading', 'description', 'button_text', 'heading_color', 'text_color', 'button_color', 'button_text_color', 'overlay_color', 'new_tab', 'background_color'];
        $urlFields = ['button_link'];

        $formattedFields = [];

        foreach ($settingFields as $value) {
            $textValues = array_map('sanitize_text_field', Arr::only($value, $textFields));
            $urlValues = array_map('sanitize_url', Arr::only($value, $urlFields));

            $formattedField = array_merge($textValues, $urlValues);

            $formattedField['hidden'] = Arr::isTrue($value, 'hidden');

            if ($value['type'] == 'block') {
                $formattedField['content'] = CustomSanitizer::santizeEditorBody(Arr::get($value, 'content'));
            }

            if (isset($value['background_image'])) {
                $bgImage = sanitize_url($value['background_image']);
                $fieldName = Arr::get($value, 'name');
                $currentImage = self::getCurrentImage($currentSettings, $fieldName);
                $bgImageUrl = self::handleMediaUrl($bgImage, $currentImage, $fieldName, $space->id);
                $formattedField['background_image'] = $bgImageUrl;
            }

            $formattedFields[] = apply_filters('fluent_community/lockscreen_formatted_field', $formattedField, $value, $space);
        }

        return $formattedFields;
    }

    public static function getLockscreenConfig(BaseSpace $space, $membership = null, $viewOnly = false)
    {
        if ($space->privacy != 'private') {
            return null;
        }

        if($membership) {
            if($membership->pivot->status) {
                if($membership->pivot->status == 'pending') {
                    return [
                        'is_pending' => true
                    ];
                }
                return null;
            }
        }

        $showCustom = Arr::get($space->settings, 'custom_lock_screen', 'no') === 'yes';
        $showPaywalls = Arr::get($space->settings, 'show_paywalls', 'no') === 'yes' && defined('FLUENTCART_VERSION');
        $canSendRequest = Arr::get($space->settings, 'can_request_join', 'no') === 'yes';

        $isRedirect = Arr::get($space->settings, 'custom_lock_screen', 'no') == 'redirect' && Arr::get($space->settings, 'onboard_redirect_url', false);
        $redirectUrl = '';
        if($isRedirect) {
            $redirectUrl = Arr::get($space->settings, 'onboard_redirect_url', false);
        }

        return [
            'showCustom'     => $showCustom,
            'showPaywalls'   => $showPaywalls,
            'canSendRequest' => $canSendRequest,
            'lockScreen'     => $showCustom ? self::getLockscreenSettings($space, $viewOnly) : null,
            'redirect_url'   => $redirectUrl
        ];
    }

    protected static function getCurrentImage($currentSettings, $fieldName)
    {
        foreach ($currentSettings as $setting) {
            if (Arr::get($setting, 'name') === $fieldName) {
                return Arr::get($setting, 'background_image');
            }
        }
        return null;
    }

    protected static function handleMediaUrl($url, $currentImage, $fieldName, $spaceId)
    {
        $deleteMediaUrls = [];

        if ($url) {
            $media = Helper::getMediaFromUrl($url);
            if (!$media || $media->is_active) {
                return $currentImage;
            }

            $url = $media->public_url;

            $media->update([
                'is_active'     => true,
                'user_id'       => get_current_user_id(),
                'sub_object_id' => $spaceId,
                'object_source' => 'lockscreen_' . $fieldName
            ]);
        }

        if ($currentImage) {
            $deleteMediaUrls[] = $currentImage;
        }

        do_action('fluent_community/remove_medias_by_url', $deleteMediaUrls, [
            'sub_object_id' => $spaceId,
        ]);

        return $url;
    }
}
