<?php

namespace FluentCommunityPro\App\Services\Integrations\FluentCRM;

use FluentCommunity\App\Models\Space;
use FluentCommunity\App\Models\SpaceUserPivot;
use FluentCommunity\App\Services\Helper;
use FluentCrm\App\Services\Funnel\BaseAction;
use FluentCrm\App\Services\Funnel\FunnelHelper;

class RemoveFromSpaceAction extends BaseAction
{
    public function __construct()
    {
        $this->actionName = 'remove_from_fluent_community';
        $this->priority = 100;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'category'    => __('Community', 'fluent-community-pro'),
            'title'       => __('Remove from Space', 'fluent-community-pro'),
            'description' => __('Remove user from the Space', 'fluent-community-pro'),
            'icon'        => 'fc-icon-apply_list',
            'settings'    => [
                'role' => null
            ]
        ];
    }

    public function getBlockFields()
    {

        $communities = Space::orderBy('title', 'ASC')->select(['id', 'title'])->get();

        return [
            'title'     => __('Remove from Space', 'fluent-community-pro'),
            'sub_title' => __('Remove user from the selected Space', 'fluent-community-pro'),
            'fields'    => [
                'community_id' => [
                    'type'    => 'select',
                    'label'   => __('Space', 'fluent-community-pro'),
                    'options' => $communities
                ],
                'role_info'    => [
                    'type' => 'html',
                    'info' => '<p><b>' . __('Only if contact is already a WordPress user and is in the selected space then this action will run.', 'fluent-community-pro') . '</b></p>',
                ]
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        if (empty($sequence->settings['community_id'])) {
            FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'skipped');
            return;
        }

        $spaceId = $sequence->settings['community_id'];

        $user = $subscriber->getWpUser();

        if (!$user) {
            $funnelMetric->status = 'skipped';
            $funnelMetric->notes = 'Skipped because no user found';
            $funnelMetric->save();

            FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'skipped');
            return;
        }

        if (Helper::isUserInSpace($user->ID, $spaceId)) {
            $space = Space::find($spaceId);

            if (!$space) {
                FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'skipped');
                return false;
            }

            SpaceUserPivot::bySpace($space->id)
                ->byUser($user->ID)
                ->delete();

            do_action('fluent_community/space/user_left', $space, $user->ID, 'automation');

            return true;
        }

        return false;
    }

}
