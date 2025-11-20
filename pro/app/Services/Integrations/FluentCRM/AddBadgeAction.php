<?php

namespace FluentCommunityPro\App\Services\Integrations\FluentCRM;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunityPro\App\Services\ProHelper;
use FluentCrm\App\Services\Funnel\BaseAction;
use FluentCrm\App\Services\Funnel\FunnelHelper;

class AddBadgeAction extends BaseAction
{
    public function __construct()
    {
        $this->actionName = 'add_badge_to_user';
        $this->priority = 100;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'category'    => __('Community', 'fluent-community-pro'),
            'title'       => __('Add Badge To User', 'fluent-community-pro'),
            'description' => __('Add Badge to user\'s profile', 'fluent-community-pro'),
            'icon'        => 'fc-icon-apply_list',
            'settings'    => [
                'badge_slugs'             => [],
                'replace_existing_badges' => 'no',
                'send_wp_welcome_email'   => 'no'
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
            'title'     => __('Add Badges to user profile', 'fluent-community-pro'),
            'sub_title' => __('Add Badges to the user profile', 'fluent-community-pro'),
            'fields'    => [
                'badge_slugs'             => [
                    'type'    => 'multi-select',
                    'label'   => __('Select Badges', 'fluent-community-pro'),
                    'options' => $formattedBadges
                ],
                'replace_existing_badges' => [
                    'type'        => 'yes_no_check',
                    'label'       => '',
                    'check_label' => __('Replace existing badges', 'fluent-community-pro'),
                    'inline_help' => __('If you enable this, existing badges will be replaced from the user profile', 'fluent-community-pro')
                ],
                'role_info'               => [
                    'type' => 'html',
                    'info' => '<p><b>' . __('A new WordPress user will be created if the contact does not have a connect WP User.', 'fluent-community-pro') . '</b></p>',
                ],
                'send_wp_welcome_email'   => [
                    'type'        => 'yes_no_check',
                    'label'       => '',
                    'check_label' => __('Send WordPress Welcome Email for new WP Users', 'fluent-community-pro'),
                    'inline_help' => __('If you enable this, . The newly created user will get the welcome email send by WordPress to with the login info & password reset link', 'fluent-community-pro')
                ]
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        $badgeSlugs = (array) Arr::get($sequence->settings, 'badge_slugs', []);

        $allBadges = Utility::getOption('user_badges', []);

        $badgeSlugs = array_filter($badgeSlugs, function ($slug) use ($allBadges) {
            return isset($allBadges[$slug]);
        });

        $user = User::where('user_email', $subscriber->email)->first();

        if (!$user) {
            // let's create the user and xprofile
            $userId = ProHelper::createUserFromCrmContact($subscriber);

            if (is_wp_error($userId)) {
                $funnelMetric->status = 'failed';
                $funnelMetric->notes = $userId->get_error_message();
                $funnelMetric->save();
                FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'failed');
                return;
            }

            if (Arr::get($sequence->settings, 'send_wp_welcome_email') == 'yes') {
                wp_new_user_notification($userId, null, 'user');
            }

            $subscriber->getWpUser();
            $user = User::find($userId);
        }

        $user->syncXProfile(false); // just in case we need to sync the xprofile
        $xprofile = XProfile::where('user_id', $user->ID)->first();

        if(!$xprofile) {
            $funnelMetric->status = 'failed';
            $funnelMetric->notes = __('User does not have an XProfile', 'fluent-community-pro');
            $funnelMetric->save();
            return;
        }

        $xprofile = $user->xprofile;

        $meta = $xprofile->meta;

        $existingBadges = (array) Arr::get($meta, 'badge_slug', []);
        if (Arr::get($sequence->settings, 'replace_existing_badges') == 'yes') {
            $existingBadges = [];
        }
        
        $meta['badge_slug'] = array_unique(array_merge($existingBadges, $badgeSlugs));

        $xprofile->meta = $meta;
        $xprofile->save();


        return true;
    }

}
