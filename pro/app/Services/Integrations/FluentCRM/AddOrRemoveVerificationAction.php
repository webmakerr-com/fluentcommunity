<?php

namespace FluentCommunityPro\App\Services\Integrations\FluentCRM;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunityPro\App\Services\ProHelper;
use FluentCrm\App\Services\Funnel\BaseAction;
use FluentCrm\App\Services\Funnel\FunnelHelper;

class AddOrRemoveVerificationAction extends BaseAction
{
    public function __construct()
    {
        $this->actionName = 'add_or_remove_community_verification';
        $this->priority = 101;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'category'    => __('Community', 'fluent-community-pro'),
            'title'       => __('Add or Remove Verification Sign (Blue Badge) To User', 'fluent-community-pro'),
            'description' => __('Add or Remove Verification Sign (Blue Badge) user\'s profile', 'fluent-community-pro'),
            'icon'        => 'fc-icon-apply_list',
            'settings'    => [
                'verification_status'   => 'yes',
                'send_wp_welcome_email' => 'no'
            ]
        ];
    }

    public function getBlockFields()
    {
        return [
            'title'     => __('Add Badges to user profile', 'fluent-community-pro'),
            'sub_title' => __('Add Badges to the user profile', 'fluent-community-pro'),
            'fields'    => [
                'verification_status'   => [
                    'type'    => 'radio',
                    'label'   => 'Select Verificaton Badge',
                    'options' => [
                        [
                            'id'    => 'yes',
                            'title' => __('Add Verification Sign (Blue Badge)', 'fluent-community-pro'),
                        ],
                        [
                            'id'    => 'no',
                            'title' => __('Remove Verification Sign (Blue Badge)', 'fluent-community-pro'),
                        ]
                    ],
                ],
                'role_info'             => [
                    'type' => 'html',
                    'info' => '<p><b>' . __('A new WordPress user will be created if the contact does not have a connect WP User.', 'fluent-community-pro') . '</b></p>',
                ],
                'send_wp_welcome_email' => [
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
        $verificationStatus = Arr::get($sequence->settings, 'verification_status', '');

        if (!$verificationStatus) {
            return;
        }

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

        if (!$xprofile) {
            $funnelMetric->status = 'failed';
            $funnelMetric->notes = __('User does not have an XProfile', 'fluent-community-pro');
            $funnelMetric->save();
            return;
        }

        $xprofile = $user->xprofile;
        $xprofile->is_verified = ($verificationStatus === 'yes') ? 1 : 0;
        $xprofile->save();

        return true;
    }

}
