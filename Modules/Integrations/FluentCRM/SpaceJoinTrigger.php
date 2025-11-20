<?php

namespace FluentCommunity\Modules\Integrations\FluentCRM;

use FluentCommunity\App\Models\Space;
use FluentCrm\App\Services\Funnel\BaseTrigger;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Framework\Support\Arr;

class SpaceJoinTrigger extends BaseTrigger
{
    public function __construct()
    {
        $this->triggerName = 'fluent_community/space/joined';
        $this->priority = 20;
        $this->actionArgNum = 2;
        parent::__construct();
    }

    public function getTrigger()
    {
        return [
            'category'    => __('Community', 'fluent-community'),
            'label'       => __('Joined in a Space', 'fluent-community'),
            'description' => __('This automation will be initiated when a user joins a space.', 'fluent-community'),
            'icon'        => 'fc-icon-wp_new_user_signup',
        ];
    }

    public function getSettingsFields($funnel)
    {
        return [
            'title'     => __('Joined in a Space', 'fluent-community'),
            'sub_title' => __('This automation will be initiated when a user join in a Space', 'fluent-community'),
            'fields'    => [
                'subscription_status'      => [
                    'type'        => 'option_selectors',
                    'option_key'  => 'editable_statuses',
                    'is_multiple' => false,
                    'label'       => __('Subscription Status', 'fluent-community'),
                    'placeholder' => __('Select Status', 'fluent-community')
                ],
                'subscription_status_info' => [
                    'type'       => 'html',
                    'info'       => '<b>' . __('An Automated double-optin email will be sent for new subscribers', 'fluent-community') . '</b>',
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
                'label'   => __('If Contact Already Exist?', 'fluent-community'),
                'help'    => __('Please specify what will happen if the subscriber already exists in the database', 'fluent-community'),
                'options' => FunnelHelper::getUpdateOptions()
            ],
            'community_ids' => [
                'type'        => 'multi-select',
                'is_multiple' => true,
                'label'       => __('Targeted Spaces', 'fluent-community'),
                'help'        => __('Select which spaces this automation funnel is for.', 'fluent-community'),
                'placeholder' => __('Select Spaces', 'fluent-community'),
                'options'     => $communities,
                'inline_help' => __('Leave blank to run for all Spaces', 'fluent-community')
            ],
            'run_multiple'  => [
                'type'        => 'yes_no_check',
                'label'       => '',
                'check_label' => __('Restart automation multiple times for a contact for this event. (Enable only if you want to restart automation for the same contact.)', 'fluent-community'),
                'inline_help' => __('If you enable this, it will restart the automation for a contact even if they are already in the automation. Otherwise, it will skip if the contact already exists.', 'fluent-community')
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
            $checkIds = (array) $checkIds;
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
