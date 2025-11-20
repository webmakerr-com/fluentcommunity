<?php

namespace FluentCommunityPro\App\Services\Integrations\FluentCRM;

use FluentCommunity\App\Models\Space;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Services\Helper;
use FluentCommunityPro\App\Services\ProHelper;
use FluentCommunity\Framework\Support\Arr;
use FluentCrm\App\Services\Funnel\BaseAction;
use FluentCrm\App\Services\Funnel\FunnelHelper;

class AddToSpaceAction extends BaseAction
{
    public function __construct()
    {
        $this->actionName = 'add_to_fluent_community';
        $this->priority = 100;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'category'    => __('Community', 'fluent-community-pro'),
            'title'       => __('Add to Space', 'fluent-community-pro'),
            'description' => __('Add user to a space', 'fluent-community-pro'),
            'icon'        => 'fc-icon-apply_list',
            'settings'    => [
                'community_id'          => null,
                'send_wp_welcome_email' => 'yes'
            ]
        ];
    }

    public function getBlockFields()
    {

        $spaces = Space::orderBy('title', 'ASC')->select(['id', 'title', 'parent_id'])
            ->with(['group'])
            ->get();

        $formattedSpaces = [];

        foreach ($spaces as $space) {
            $title = $space->title;

            if($space->group) {
                $title .= ' (' . $space->group->title . ')';
            }

            $formattedSpaces[] = [
                'id'    => (string) $space->id,
                'title' => $title
            ];
        }

        return [
            'title'     => __('Add to Space', 'fluent-community-pro'),
            'sub_title' => __('Add user to the selected spaces', 'fluent-community-pro'),
            'fields'    => [
                'community_id'          => [
                    'type'    => 'multi-select',
                    'label'   => __('Select Spaces', 'fluent-community-pro'),
                    'options' => $formattedSpaces
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
        if (empty($sequence->settings['community_id'])) {
            FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'skipped');
            return;
        }

        $spaceIds = (array)$sequence->settings['community_id'];

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

        foreach ($spaceIds as $spaceId) {
            Helper::addToSpace($spaceId, $user->ID, 'member', 'by_admin');
        }

        return true;
    }

}
