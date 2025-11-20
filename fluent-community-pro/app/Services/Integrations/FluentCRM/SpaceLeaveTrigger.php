<?php

namespace FluentCommunityPro\App\Services\Integrations\FluentCRM;

use FluentCommunity\App\Models\Space;
use FluentCrm\App\Services\Funnel\BaseTrigger;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Framework\Support\Arr;

class SpaceLeaveTrigger extends BaseTrigger
{
    public function __construct()
    {
        $this->triggerName = 'fluent_community/space/user_left';
        $this->priority = 21;
        $this->actionArgNum = 2;
        parent::__construct();
    }

    public function getTrigger()
    {
        return [
            'category'    => __('Community', 'fluent-community-pro'),
            'label'       => __('Left from a Space', 'fluent-community-pro'),
            'description' => __('This automation will be initiated when a user leaves a Space.', 'fluent-community-pro'),
            'icon'        => 'fc-icon-wp_new_user_signup',
        ];
    }

    public function getSettingsFields($funnel)
    {
        return [
            'title'     => __('Left from a Space', 'fluent-community-pro'),
            'sub_title' => __('This automation will be initiated when a user leaves a Space', 'fluent-community-pro'),
            'fields'    => [
                'subscription_status'      => [
                    'type'        => 'option_selectors',
                    'option_key'  => 'editable_statuses',
                    'is_multiple' => false,
                    'label'       => __('Subscription Status', 'fluent-community-pro'),
                    'placeholder' => __('Select Status', 'fluent-community-pro')
                ],
                'subscription_status_info' => [
                    'type'       => 'html',
                    'info'       => '<b>' . __('An Automated double-optin email will be sent for new subscribers', 'fluent-community-pro') . '</b>',
                    'dependency' => [
                        'depends_on' => 'subscription_status',
                        'operator'   => '=',
                        'value'      => 'pending'
                    ]
                ]
            ]
        ];
    }

    public function getFunnelSettingsDefaults()
    {
        return [
            'subscription_status' => 'subscribed'
        ];
    }

    public function getConditionFields($funnel)
    {
        $communities = Space::orderBy('title', 'ASC')->select(['id', 'title'])->get();

        return [
            'update_type'   => [
                'type'    => 'radio',
                'label'   => __('If Contact Already Exist?', 'fluent-community-pro'),
                'help'    => __('Please specify what will happen if the subscriber already exists in the database', 'fluent-community-pro'),
                'options' => FunnelHelper::getUpdateOptions()
            ],
            'community_ids' => [
                'type'        => 'multi-select',
                'is_multiple' => true,
                'label'       => __('Targeted Spaces', 'fluent-community-pro'),
                'help'        => __('Select which spcaes this automation funnel is for.', 'fluent-community-pro'),
                'placeholder' => __('Select Spaces', 'fluent-community-pro'),
                'options'     => $communities,
                'inline_help' => __('Leave blank to run for any Spaces', 'fluent-community-pro')
            ],
            'run_multiple'  => [
                'type'        => 'yes_no_check',
                'label'       => '',
                'check_label' => __('Restart automation multiple times for a contact for this event. (Enable only if you want to restart automation for the same contact.)', 'fluent-community-pro'),
                'inline_help' => __('If you enable this, it will restart the automation for a contact even if they are already in the automation. Otherwise, it will skip if the contact already exists.', 'fluent-community-pro')
            ]
        ];
    }

    public function getFunnelConditionDefaults($funnel)
    {
        return [
            'update_type'   => 'update', // skip_all_actions, skip_update_if_exist
            'community_ids' => [],
            'run_multiple'  => 'yes'
        ];
    }

    public function handle($funnel, $originalArgs)
    {
        $space = $originalArgs[0];
        $userId = $originalArgs[1];

        $user = get_user_by('ID', $userId);

        if (!$user || !$this->isProcessable($funnel, $user, $space)) {
            return false;
        }

        $subscriberData = FunnelHelper::prepareUserData($user);

        $subscriberData = wp_parse_args($subscriberData, $funnel->settings);
        $subscriberData['status'] = $subscriberData['subscription_status'];
        unset($subscriberData['subscription_status']);

        (new FunnelProcessor())->startFunnelSequence($funnel, $subscriberData, [
            'source_trigger_name' => $this->triggerName,
            'source_ref_id'       => $space->id
        ]);
    }

    private function isProcessable($funnel, $user, $space)
    {
        $conditions = $funnel->conditions;
        // check update_type
        $updateType = Arr::get($conditions, 'update_type');

        $subscriber = FunnelHelper::getSubscriber($user->user_email);
        if ($updateType == 'skip_all_if_exist' && $subscriber) {
            return false;
        }

        // check user roles
        if ($checkIds = Arr::get($conditions, 'community_ids', [])) {
            $checkIds = (array)$checkIds;
            if (!in_array($space->id, $checkIds)) {
                return false;
            }
        }

        // check run_only_one
        if ($subscriber && FunnelHelper::ifAlreadyInFunnel($funnel->id, $subscriber->id)) {
            $multipleRun = Arr::get($conditions, 'run_multiple') == 'yes';
            if ($multipleRun) {
                FunnelHelper::removeSubscribersFromFunnel($funnel->id, [$subscriber->id]);
            }
            return $multipleRun;
        }

        return true;
    }
}
