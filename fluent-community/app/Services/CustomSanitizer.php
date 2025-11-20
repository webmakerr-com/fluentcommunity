<?php

namespace FluentCommunity\App\Services;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\Space;
use FluentCommunity\Framework\Support\Arr;

class CustomSanitizer
{
    public static function sanitizeMenuLink($item)
    {
        $validKeys = ['title', 'enabled', 'permalink', 'new_tab', 'link_classes', 'shape_svg', 'emoji', 'icon_image', 'is_custom', 'is_system', 'is_locked', 'is_unavailable', 'slug', 'privacy', 'membership_ids'];
        $item = array_filter(Arr::only($item, $validKeys));

        $yesNoItems = ['enabled', 'is_custom', 'is_system', 'is_locked', 'is_unavailable'];
        foreach ($yesNoItems as $key) {
            if (isset($item[$key])) {
                $item[$key] = $item[$key] === 'yes' ? 'yes' : 'no';
            }
        }

        $textTypes = ['title', 'new_tab', 'link_classes', 'slug', 'privacy'];
        foreach ($textTypes as $key) {
            if (isset($item[$key])) {
                $item[$key] = sanitize_text_field($item[$key]);
            }
        }

        $item['permalink'] = sanitize_url($item['permalink']);

        if (!empty($item['shape_svg'])) {
            $item['shape_svg'] = self::sanitizeSvg($item['shape_svg']);
        }

        if (!empty($item['emoji'])) {
            $item['emoji'] = self::sanitizeEmoji($item['emoji']);
        }

        if (!empty($item['icon_image'])) {
            $media = Helper::getMediaFromUrl($item['icon_image']);
            if ($media) {
                $item['icon_image'] = $media->public_url;
                $media->update([
                    'is_active'     => true,
                    'user_id'       => get_current_user_id(),
                    'object_source' => 'general'
                ]);
            } else {
                $item['icon_image'] = sanitize_url($item['icon_image']);
            }
        }

        if (Arr::get($item, 'privacy') == 'members_only') {
            $item['membership_ids'] = array_map('sanitize_text_field', (array)Arr::get($item, 'membership_ids', []));
        }

        return $item;
    }

    public static function sanitizeSvg($svg_content)
    {
        if (!$svg_content) {
            return '';
        }

        if (current_user_can('unfiltered_html')) {
            return $svg_content;
        }

        // Remove any comments
        $svg_content = preg_replace('/<!--(.|\s)*?-->/', '', $svg_content);

        // Remove XML or DOCTYPE declarations
        $svg_content = preg_replace('/<\?xml(.|\s)*?\?>/', '', $svg_content);
        $svg_content = preg_replace('/<!DOCTYPE(.|\s)*?>/i', '', $svg_content);

        // Remove embedded scripts, iframes, or event handlers
        $svg_content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $svg_content);
        $svg_content = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $svg_content);
        $svg_content = preg_replace('/on\w+="[^"]*"/i', '', $svg_content);

        $allowed_tags = [
            'svg'            => ['width' => true, 'height' => true, 'viewBox' => true, 'version' => true, 'xmlns' => true, 'xmlns:xlink' => true, 'xml:space' => true, 'style' => true, 'preserveAspectRatio' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'color' => true],
            'g'              => ['fill' => true, 'fill-rule' => true, 'stroke' => true, 'stroke-width' => true, 'clip-path' => true, 'transform' => true],
            'path'           => ['d' => true, 'opacity' => true, 'stroke-linecap' => true, 'fill' => true, 'fill-rule' => true, 'stroke' => true, 'stroke-width' => true, 'style' => true, 'transform' => true],
            'rect'           => ['width' => true, 'height' => true, 'x' => true, 'y' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'transform' => true, 'style' => true],
            'circle'         => ['cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'transform' => true],
            'ellipse'        => ['cx' => true, 'cy' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'transform' => true],
            'line'           => ['x1' => true, 'x2' => true, 'y1' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true, 'transform' => true],
            'polyline'       => ['points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'transform' => true],
            'polygon'        => ['points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'transform' => true],
            'text'           => ['x' => true, 'y' => true, 'font-size' => true, 'font-family' => true, 'text-anchor' => true, 'fill' => true, 'transform' => true],
            'tspan'          => ['x' => true, 'y' => true, 'font-size' => true, 'font-family' => true, 'text-anchor' => true, 'fill' => true],
            'defs'           => [],
            'clipPath'       => ['id' => true, 'clipPathUnits' => true],
            'stop'           => ['offset' => true, 'stop-color' => true, 'stop-opacity' => true],
            'linearGradient' => ['id' => true, 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'gradientUnits' => true, 'gradientTransform' => true],
            'radialGradient' => ['id' => true, 'cx' => true, 'cy' => true, 'r' => true, 'fx' => true, 'fy' => true, 'gradientUnits' => true, 'gradientTransform' => true],
            'mask'           => ['id' => true, 'maskUnits' => true, 'maskContentUnits' => true, 'x' => true, 'y' => true, 'width' => true, 'height' => true],
            'use'            => ['xlink:href' => true, 'x' => true, 'y' => true, 'width' => true, 'height' => true],
            'title'          => [],
            'desc'           => [],
        ];

        // Load the SVG string into a DOMDocument and discard errors for malformed XML
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadXML($svg_content);
        libxml_clear_errors();

        if ($dom->documentElement) {
            // Sanitize by removing unwanted tags and attributes
            self::sanitizeNode($dom->documentElement, $allowed_tags);
        }

        return $dom->saveXML($dom->documentElement);
    }

    private static function sanitizeNode(\DOMNode $node, array $allowed_tags)
    {
        if ($node->nodeType === XML_ELEMENT_NODE) {
            if (!isset($allowed_tags[$node->nodeName])) {
                $node->parentNode->removeChild($node);
                return;
            }

            // Check attributes
            $attributes = $node->attributes;
            $length = $attributes->length;
            for ($i = $length - 1; $i >= 0; $i--) {
                $attr = $attributes->item($i);
                $attr_name = $attr->nodeName;
                if (!isset($allowed_tags[$node->nodeName][$attr_name])) {
                    $node->removeAttribute($attr_name);
                } else {
                    // Sanitize attribute values
                    // FILTER_SANITIZE_STRING is deprecated in PHP 8.1
                    $sanitized_value = htmlspecialchars($attr->nodeValue, ENT_QUOTES, 'UTF-8');
                    $node->setAttribute($attr_name, $sanitized_value);
                }
            }
        }

        // Recursively sanitize child nodes
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            self::sanitizeNode($node->childNodes->item($i), $allowed_tags);
        }
    }

    public static function sanitizeEmoji($emoji, $single = true)
    {
        $emoji = (string)$emoji;
        $emoji = trim($emoji);

        if (!$emoji) {
            return '';
        }

        if ($single && function_exists('\mb_substr')) {
            $emoji = \mb_substr($emoji, 0, 4, 'UTF-8');
        }

        $isEmoji = preg_match('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{2B50}\x{2B55}\x{2934}\x{2935}\x{3297}\x{3299}\x{20E3}\x{23E9}-\x{23FA}\x{25B6}\x{25C0}\x{FE0F}]/u', $emoji);

        if ($isEmoji) {
            return $emoji;
        }
        return '';
    }

    public static function sanitizeWelcomeBannerSettings($settings)
    {
        $rules = [
            'title'       => 'sanitize_text_field',
            'description' => 'wp_kses_post',
            'mediaType'   => 'sanitize_text_field',
            'allowClose'  => 'sanitize_text_field',
            'enabled'     => 'sanitize_text_field',
        ];

        $sanitizedSettings = [];
        foreach (['login', 'logout'] as $type) {
            $typeSettings = Arr::get($settings, $type, []);
            if (empty($typeSettings)) {
                continue;
            }

            $bannerVideo = Arr::get($typeSettings, 'bannerVideo', []);
            $bannerImage = Arr::get($typeSettings, 'bannerImage', '');
            $ctaButtons = Arr::get($typeSettings, 'ctaButtons', []);

            $sanitizedSettings[$type]['bannerVideo'] = self::sanitizeBannerVideo($bannerVideo);
            $sanitizedSettings[$type]['bannerImage'] = self::sanitizeBannerImage($bannerImage);
            $sanitizedSettings[$type]['ctaButtons'] = self::sanitizeCtaButtons($ctaButtons);
            $sanitizedSettings[$type]['description'] = Arr::get($typeSettings, 'description');

            foreach ($typeSettings as $key => $value) {
                if (isset($rules[$key]) && !in_array($key, ['bannerVideo', 'bannerImage', 'ctaButtons'])) {
                    $sanitizedSettings[$type][$key] = call_user_func($rules[$key], $value);
                }
            }
        }

        return $sanitizedSettings;
    }

    private static function sanitizeBannerVideo($video)
    {
        if (empty($video)) {
            return [];
        }

        return array_filter([
            'type'         => sanitize_text_field(Arr::get($video, 'type', '')),
            'url'          => sanitize_url(Arr::get($video, 'url', '')),
            'content_type' => sanitize_text_field(Arr::get($video, 'content_type', '')),
            'provider'     => sanitize_url(Arr::get($video, 'provider', '')),
            'title'        => sanitize_text_field(Arr::get($video, 'title', '')),
            'author_name'  => sanitize_text_field(Arr::get($video, 'author_name', '')),
            'html'         => self::sanitizeRichText(Arr::get($video, 'html', '')),
        ]);
    }

    private static function sanitizeBannerImage($imageUrl)
    {
        if (empty($imageUrl)) {
            return '';
        }

        $media = Helper::getMediaFromUrl($imageUrl);
        if ($media) {
            $media->update([
                'is_active'     => true,
                'user_id'       => get_current_user_id(),
                'object_source' => 'general'
            ]);
            return $media->public_url;
        }

        return sanitize_url($imageUrl);
    }

    private static function sanitizeCtaButtons($ctaButtons)
    {
        if (empty($ctaButtons)) {
            return [];
        }

        $sanitizerMap = [
            'label'  => 'sanitize_text_field',
            'link'   => 'sanitize_url',
            'type'   => 'sanitize_text_field',
            'newTab' => 'sanitize_text_field'
        ];

        foreach ($ctaButtons as $btnKey => $btnValue) {
            foreach ($btnValue as $key => $value) {
                if (isset($sanitizerMap[$key])) {
                    $ctaButtons[$btnKey][$key] = call_user_func($sanitizerMap[$key], $value);
                }
            }
        }

        return $ctaButtons;
    }

    public static function santizeLinkItem($item)
    {
        $validKeys = ['title', 'enabled', 'new_tab', 'emoji', 'icon_image', 'shape_svg', 'title', 'permalink', 'slug', 'privacy', 'membership_ids'];
        $item = array_filter(Arr::only($item, $validKeys));

        $yesNoItems = ['enabled', 'new_tab', 'is_locked', 'is_unavailable'];
        foreach ($yesNoItems as $key) {
            if (isset($item[$key])) {
                $item[$key] = $item[$key] === 'yes' ? 'yes' : 'no';
            }
        }

        $item['emoji'] = self::sanitizeEmoji(Arr::get($item, 'emoji'));

        if (empty($item['slug'])) {
            $item['slug'] = sanitize_title($item['title']);
        } else {
            $item['slug'] = sanitize_title($item['slug']);
        }

        $textTypes = ['title'];
        foreach ($textTypes as $key) {
            if (isset($item[$key])) {
                $item[$key] = sanitize_text_field($item[$key]);
            }
        }
        $item['permalink'] = sanitize_url($item['permalink']);


        if (!empty($item['icon_image'])) {
            $media = Helper::getMediaFromUrl($item['icon_image']);
            if ($media) {
                $media->update([
                    'is_active'     => true,
                    'user_id'       => get_current_user_id(),
                    'object_source' => 'general'
                ]);
                $item['icon_image'] = $media->public_url;
            } else {
                $item['icon_image'] = sanitize_text_field($item['icon_image']);
            }
        }

        if (!empty($item['icon_svg'])) {
            $item['icon_svg'] = self::sanitizeSvg($item['icon_svg']);
        }
        
        if (Arr::get($item, 'privacy') == 'members_only') {
            $item['membership_ids'] = array_map('sanitize_text_field', (array)Arr::get($item, 'membership_ids', []));
        }

        return array_filter($item);
    }

    public static function sanitizeRichText($content, $print = false)
    {
        if ($print) {
            echo self::sanitizeHtml($content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        return self::sanitizeHtml($content);
    }

    public static function sanitizeHtml($html)
    {
        if (current_user_can('unfiltered_html')) {
            return $html;
        }

        if (!$html) {
            return $html;
        }

        // Return $html if it's just a plain text
        if (!preg_match('/<[^>]*>/', $html)) {
            return $html;
        }

        $tags = wp_kses_allowed_html('post');
        $tags['style'] = [
            'types' => [],
        ];

        // iframe
        $tags['iframe'] = [
            'width'           => [],
            'height'          => [],
            'src'             => [],
            'srcdoc'          => [],
            'title'           => [],
            'frameborder'     => [],
            'allow'           => [],
            'class'           => [],
            'id'              => [],
            'allowfullscreen' => [],
            'referrerpolicy'  => [],
            'style'           => [],
        ];

        $tags = apply_filters('fluent_community/allowed_html_tags', $tags);

        return wp_kses($html, $tags);
    }

    public static function santizeEmailSettings($settings)
    {
        $prevSettings = Utility::getEmailNotificationSettings();
        $settings = Arr::only($settings, array_keys($prevSettings));

        $yesNoFields = ['com_my_post_mail', 'reply_my_com_mail', 'mention_mail', 'digest_email_status', 'disable_powered_by'];
        $textFields = ['send_from_name', 'reply_to_name'];
        $emailFields = ['send_from_email', 'reply_to_email'];

        foreach ($yesNoFields as $field) {
            if (isset($settings[$field])) {
                $settings[$field] = $settings[$field] === 'yes' ? 'yes' : 'no';
            }
        }

        foreach ($textFields as $field) {
            if (isset($settings[$field])) {
                $settings[$field] = sanitize_text_field($settings[$field]);
            }
        }

        foreach ($emailFields as $field) {
            if (isset($settings[$field])) {
                $settings[$field] = sanitize_email($settings[$field]);
            }
        }

        $time = Arr::get($settings, 'daily_digest_time');

        if ($time) {
            $time = sanitize_text_field($time);

            // check time is valid or not
            if (!preg_match('/^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
                $time = '09:00';
            }
        } else {
            $time = '09:00';
        }

        $emailDay = Arr::get($settings, 'digest_mail_day');

        if ($emailDay) {
            $emailDay = sanitize_text_field($emailDay);
            if (!in_array($emailDay, ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'])) {
                $emailDay = 'tue';
            }
        } else {
            $emailDay = 'tue';
        }

        $settings['digest_mail_day'] = $emailDay;
        $settings['daily_digest_time'] = $time;
        $settings['email_footer'] = wp_kses_post(self::unslashMarkdown(Arr::get($settings, 'email_footer')));
        $settings['email_footer_rendered'] = FeedsHelper::mdToHtml($settings['email_footer']);

        if (!empty($settings['logo'])) {
            $settings['logo'] = sanitize_url($settings['logo']);
        }

        return $settings;
    }

    public static function sanitizeUserName($username)
    {
        $username = strtolower($username);
        // check of @ symbol
        if (strpos($username, '@') !== false) {
            $username = explode('@', $username)[0];
        }

        $username = sanitize_user($username);
        $username = preg_replace('/[^a-zA-Z0-9_]/', '', $username);
        return $username;
    }

    public static function unslashMarkdown($markdown)
    {
        $replaceMaps = [
            "\\\n" => PHP_EOL,
            '\@'   => '@',
            '\\_'  => '_',
            '\\&'  => '&',
            '\\*'  => '*',
            '\\~'  => '~',
            '\\:'  => ':',
            '\\.'  => '.'
        ];

        return str_replace(array_keys($replaceMaps), array_values($replaceMaps), $markdown);
    }

    public static function santizeSpaceSettings($settings = [], $privacy = 'public')
    {
        $yesNotFields = [
            'restricted_post_only',
            'can_request_join',
            'show_paywalls',
            'show_sidebar',
            'hide_members_count',
            'document_library',
            'disable_post_sort_by'
        ];

        $settings = Arr::only($settings, array_keys((new Space())->defaultSettings()));

        foreach ($yesNotFields as $field) {
            $settings[$field] = Arr::get($settings, $field) === 'yes' ? 'yes' : 'no';
        }

        $settings['shape_svg'] = self::sanitizeSvg(Arr::get($settings, 'shape_svg', ''));
        if (empty($settings['shape_svg'])) {
            $settings['emoji'] = self::sanitizeEmoji(Arr::get($settings, 'emoji', ''));
        } else {
            $settings['emoji'] = '';
        }

        $lockScreenType = Arr::get($settings, 'custom_lock_screen');
        if (!in_array($lockScreenType, ['yes', 'no', 'redirect']) || $privacy !== 'private') {
            $lockScreenType = 'no';
        }
        $settings['custom_lock_screen'] = $lockScreenType;


        if ($lockScreenType === 'redirect') {
            $redirectUrl = Arr::get($settings, 'onboard_redirect_url');
            if (!$redirectUrl || !filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
                return new \WP_Error('invalid_redirect_url', __('Invalid redirect URL.', 'fluent-community'));
            }
            $settings['onboard_redirect_url'] = sanitize_url($redirectUrl);
        }

        $validOrderOptions = array_keys(Helper::getPostOrderOptions());
        $defaultOrder = Arr::get($settings, 'default_post_sort_by', '');
        $settings['default_post_sort_by'] = in_array($defaultOrder, $validOrderOptions) ? $defaultOrder : '';

        return $settings;
    }

    public static function santizeEditorBody($body)
    {
        if (current_user_can('unfiltered_html')) {
            return $body;
        }

        return wp_kses_post($body);
    }
}
