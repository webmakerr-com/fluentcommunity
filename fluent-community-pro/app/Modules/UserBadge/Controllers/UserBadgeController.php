<?php

namespace FluentCommunityPro\App\Modules\UserBadge\Controllers;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Http\Controllers\Controller;
use FluentCommunity\App\Services\CustomSanitizer;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Http\Request\Request;
use FluentCommunity\Framework\Support\Arr;

class UserBadgeController extends Controller
{
    public function getBadges(Request $request)
    {
        $badges = Utility::getOption('user_badges', []);

        if(!$badges || !is_array($badges)) {
            $badges = [];
        }

        return [
            'badges' => array_values($badges)
        ];
    }

    public function saveBadges(Request $request)
    {
        $badges = $request->get('badges', []);

        $formattedBadges = [];

        foreach ($badges as $badge) {
            if (empty($badge['title'])) {
                return $this->sendError([
                    'message' => 'Title is required for all the badges'
                ]);
            }

            $slug = sanitize_title(Arr::get($badge, 'slug'));
            if (!$slug) {
                $slug = sanitize_title(Arr::get($badge, 'title'));
            }

            $badge = $this->santizeBadge($badge);

            $badge['slug'] = $slug;

            if (empty($badge['config'])) {
                $badge['config']['emoji'] = '';
            }

            $formattedBadges[$slug] = $badge;
        }

        Utility::updateOption('user_badges', $formattedBadges);

        if (!$formattedBadges) {
            $formattedBadges = (object)$formattedBadges;
        }

        return [
            'badges'  => $formattedBadges,
            'message' => __('Badges saved successfully', 'fluent-community-pro')
        ];
    }

    private function santizeBadge($badge)
    {

        $logo = Arr::get($badge, 'config.logo');

        if ($logo) {
            $media = Helper::getMediaFromUrl($logo);
            if ($media) {
                $media->is_active = 1;
                $media->save();
                $logo = $media->public_url;
            } else {
                $logo = sanitize_url($logo);
            }
        }

        return array_filter([
            'title'            => sanitize_text_field(Arr::get($badge, 'title')),
            'config'           => array_filter([
                'shape_svg' => CustomSanitizer::sanitizeSvg(Arr::get($badge, 'config.shape_svg')),
                'emoji'     => CustomSanitizer::sanitizeEmoji(Arr::get($badge, 'config.emoji')),
                'logo'      => $logo,
            ]),
            'color'            => sanitize_text_field(Arr::get($badge, 'color')),
            'background_color' => sanitize_text_field(Arr::get($badge, 'background_color')),
            'show_label'       => Arr::get($badge, 'show_label') === 'yes' ? 'yes' : 'no',
        ]);
    }
}
