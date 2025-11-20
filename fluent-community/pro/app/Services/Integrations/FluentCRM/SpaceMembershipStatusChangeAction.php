<?php

namespace FluentCommunityPro\App\Services\Integrations\FluentCRM;

use FluentCommunity\App\Models\User;
use FluentCrm\App\Services\Funnel\BaseAction;
use FluentCrm\App\Services\Funnel\FunnelHelper;

class SpaceMembershipStatusChangeAction extends BaseAction
{
    public function __construct()
    {
        $this->actionName = 'fluent_community_membership_status_change';
        $this->priority = 100;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'category'    => __('Community', 'fluent-community-pro'),
            'title'       => __('Change Space Membership Status', 'fluent-community-pro'),
            'description' => __('Activate or Block a user from a Space', 'fluent-community-pro'),
            'icon'        => 'fc-icon-apply_list',
            'settings'    => [
                'new_status' => 'active'
            ]
        ];
    }

    public function getBlockFields()
    {
        return [
            'title'     => __('Add to Space', 'fluent-community-pro'),
            'sub_title' => __('Add user to the selected Space', 'fluent-community-pro'),
            'fields'    => [
                'new_status' => [
                    'type'    => 'select',
                    'label'   => __('New Status', 'fluent-community-pro'),
                    'options' => [
                        [
                            'id'    => 'active',
                            'title' => __('Active (Can access to the portal)', 'fluent-community-pro')
                        ],
                        [
                            'id'    => 'pending',
                            'title' => __('Pending (Require Admin Approval)', 'fluent-community-pro')
                        ],
                        [
                            'id'    => 'blocked',
                            'title' => __('Blocked (Can not access to the portal)', 'fluent-community-pro')
                        ]
                    ]
                ]
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        if (empty($sequence->settings['new_status'])) {
            FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'skipped');
            return;
        }

        $newStatus = $sequence->settings['new_status'];

        $userModel = User::where('user_email', $subscriber->email)->first();

        if (!$userModel) {
            $funnelMetric->status = 'skipped';
            $funnelMetric->notes = 'Skipped because no user found';
            $funnelMetric->save();

            FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'skipped');
            return;
        }

        $syncedXProfile = $userModel->syncXProfile();
        if (!$syncedXProfile) {
            return false;
        }

        $xProfile = $userModel->xprofile;
        $xProfile->status = $newStatus;
        $xProfile->save();

        return true;
    }
}