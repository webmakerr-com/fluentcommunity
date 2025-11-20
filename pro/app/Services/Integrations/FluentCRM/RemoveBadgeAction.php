<?php

namespace FluentCommunityPro\App\Services\Integrations\FluentCRM;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunityPro\App\Services\ProHelper;
use FluentCrm\App\Services\Funnel\BaseAction;
use FluentCrm\App\Services\Funnel\FunnelHelper;

class RemoveBadgeAction extends BaseAction
{
    public function __construct()
    {
        $this->actionName = 'remove_badge_from_user';
        $this->priority = 100;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'category'    => __('Community', 'fluent-community-pro'),
            'title'       => __('Remove Commubity Badge', 'fluent-community-pro'),
            'description' => __('Remove Badge from user\'s profile', 'fluent-community-pro'),
            'icon'        => 'fc-icon-apply_list',
            'settings'    => [
                'badge_slugs' => []
            ]
        ];
    }

    public function getBlockFields()
    {
        $badges = Utility::getOption('user_badges', []);
        $formattedBadges = [];
        foreach ($badges as $badge) {
            $formattedBadges[] = [
                'title' => $badge['title'] ?? $badge['slug'],
                'id'    => $badge['slug']
            ];
        }

        return [
            'title'     => __('Remove Badges from user profile', 'fluent-community-pro'),
            'sub_title' => __('Remove Badges from the user profile', 'fluent-community-pro'),
            'fields'    => [
                'badge_slugs' => [
                    'type'    => 'multi-select',
                    'label'   => __('Select Badges', 'fluent-community-pro'),
                    'options' => $formattedBadges
                ]
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        $badgeSlugs = (array)Arr::get($sequence->settings, 'badge_slugs', []);

        $allBadges = Utility::getOption('user_badges', []);

        $badgeSlugs = array_filter($badgeSlugs, function ($slug) use ($allBadges) {
            return isset($allBadges[$slug]);
        });

        $user = User::where('user_email', $subscriber->email)->first();

        if (!$user) {
            return;
        }

        $xprofile = XProfile::where('user_id', $user->ID)->first();
        if (!$xprofile) {
            $funnelMetric->status = 'failed';
            $funnelMetric->notes = __('User does not have an XProfile', 'fluent-community-pro');
            $funnelMetric->save();
            return;
        }

        $xprofile = $user->xprofile;

        $meta = $xprofile->meta;

        $existingBadges = (array)Arr::get($meta, 'badge_slug', []);
        $existingBadges = array_diff($existingBadges, $badgeSlugs);

        $meta['badge_slug'] = $existingBadges;

        $xprofile->meta = $meta;
        $xprofile->save();

        return true;
    }

}
