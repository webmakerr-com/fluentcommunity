<?php

namespace FluentCommunityPro\App\Modules\LeaderBoard;

use FluentCommunity\App\Models\Space;
use FluentCommunityPro\App\Modules\LeaderBoard\Services\LeaderBoardHelper;
use FluentCrm\App\Services\Funnel\BaseTrigger;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Framework\Support\Arr;

class UserLevelUpgradedTrigger extends BaseTrigger
{
    public function __construct()
    {
        $this->triggerName = 'fluent_community/user_level_upgraded';
        $this->priority = 22;
        $this->actionArgNum = 2;
        parent::__construct();
    }

    public function getTrigger()
    {
        return [
            'category'    => __('Community', 'fluent-community-pro'),
            'label'       => __('User Level (Leaderboard) Upgraded', 'fluent-community-pro'),
            'description' => __('This Funnel will be initiated when a user upgraded to a level in the leaderboard', 'fluent-community-pro'),
            'icon'        => 'fc-icon-wp_new_user_signup',
        ];
    }

    public function getSettingsFields($funnel)
    {
        return [
            'title'     => __('User Level (Leaderboard) Upgraded', 'fluent-community-pro'),
            'sub_title' => __('This Funnel will be initiated when a user upgraded to a level in the leaderboard', 'fluent-community-pro'),
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
        $levels = LeaderBoardHelper::getDynamicLevels();

        $formattedLevels = [];

        foreach ($levels as $level) {
            $formattedLevels[] = [
                'id'    => $level['slug'],
                'title' => $level['title']
            ];
        }

        return [
            'update_type'  => [
                'type'    => 'radio',
                'label'   => __('If Contact Already Exist?', 'fluent-community-pro'),
                'help'    => __('Please specify what will happen if the subscriber already exists in the database', 'fluent-community-pro'),
                'options' => FunnelHelper::getUpdateOptions()
            ],
            'level_ids'    => [
                'type'        => 'multi-select',
                'is_multiple' => true,
                'label'       => __('Targeted Levels', 'fluent-community-pro'),
                'help'        => __('Select for which levels the automation will run for', 'fluent-community-pro'),
                'placeholder' => __('Select Level', 'fluent-community-pro'),
                'options'     => $formattedLevels,
                'inline_help' => __('Leave blank to run for any level upgrade', 'fluent-community-pro')
            ],
            'run_multiple' => [
                'type'        => 'yes_no_check',
                'label'       => '',
                'check_label' => __('Restart the automation multiple times for a contact for this event. (Only enable this if you want to restart the automation for the same contact)', 'fluent-community-pro'),
                'inline_help' => __('If you enable this, it will restart the automation for a contact even if they are already in the automation. Otherwise, it will skip if the contact already exists.', 'fluent-community-pro')
            ]
        ];
    }

    public function getFunnelConditionDefaults($funnel)
    {
        return [
            'update_type'  => 'update', // skip_all_actions, skip_update_if_exist
            'level_ids'    => [],
            'run_multiple' => 'yes'
        ];
    }

    public function handle($funnel, $originalArgs)
    {
        $xprofile = $originalArgs[0];
        $newLevel = $originalArgs[1];

        $user = $xprofile->user;

        if (!$user || !$this->isProcessable($funnel, $user, $newLevel)) {
            return false;
        }

        $subscriberData = FunnelHelper::prepareUserData($user);

        $subscriberData = wp_parse_args($subscriberData, $funnel->settings);
        $subscriberData['status'] = $subscriberData['subscription_status'];
        unset($subscriberData['subscription_status']);

        (new FunnelProcessor())->startFunnelSequence($funnel, $subscriberData, [
            'source_trigger_name' => $this->triggerName,
            'source_ref_id'       => (int)str_replace('level_', '', $newLevel['slug'])
        ]);
    }

    private function isProcessable($funnel, $user, $level)
    {
        $conditions = $funnel->conditions;
        // check update_type
        $updateType = Arr::get($conditions, 'update_type');

        $subscriber = FunnelHelper::getSubscriber($user->user_email);
        if ($updateType == 'skip_all_if_exist' && $subscriber) {
            return false;
        }

        // check user levels
        if ($checkIds = Arr::get($conditions, 'level_ids', [])) {
            $checkIds = (array)$checkIds;
            if (!in_array($level['slug'], $checkIds)) {
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
