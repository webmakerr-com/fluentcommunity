<?php

namespace FluentCommunity\Modules\Integrations\FluentCRM;

use FluentCommunity\Modules\Course\Model\Course;
use FluentCommunity\Modules\Course\Model\CourseTopic;
use FluentCrm\App\Services\Funnel\BaseTrigger;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Framework\Support\Arr;

class CourseTopicCompletedTrigger extends BaseTrigger
{
    public function __construct()
    {
        $this->triggerName = 'fluent_community/course/topic_completed';
        $this->priority = 50;
        $this->actionArgNum = 2;
        parent::__construct();
    }

    public function getTrigger()
    {
        return [
            'category'    => __('Community', 'fluent-community'),
            'label'       => __('Section/Topic Completed', 'fluent-community'),
            'icon'        => 'fc-icon-wp_new_user_signup',
            'description' => __('This Automation runs when a student completes a Section/Topic', 'fluent-community')
        ];
    }

    public function getFunnelSettingsDefaults()
    {
        return [
            'subscription_status' => 'subscribed'
        ];
    }

    public function getSettingsFields($funnel)
    {
        return [
            'title'     => __('Student completes a Section/Topic in FluentCommunity', 'fluent-community'),
            'sub_title' => __('This Funnel will start when a student completes a Section/Topic', 'fluent-community'),
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

    public function getFunnelConditionDefaults($funnel)
    {
        return [
            'update_type' => 'update', // skip_all_actions, skip_update_if_exist
            'topic_ids'   => []
        ];
    }

    public function getConditionFields($funnel)
    {
        return [
            'update_type'  => [
                'type'    => 'radio',
                'label'   => __('If Contact Already Exist?', 'fluent-community'),
                'help'    => __('Please specify what will happen if the subscriber already exist in the database', 'fluent-community'),
                'options' => FunnelHelper::getUpdateOptions()
            ],
            'topic_ids'    => [
                'type'        => 'grouped-select',
                'label'       => __('Target Course Sections', 'fluent-community'),
                'help'        => __('Select for which sections this automation will run', 'fluent-community'),
                'options'     => $this->getTopicsByCourseGroup(),
                'is_multiple' => true,
                'inline_help' => __('Keep it blank to run to any sectoions', 'fluent-community')
            ],
            'run_multiple' => [
                'type'        => 'yes_no_check',
                'label'       => '',
                'check_label' => __('Restart the Automation Multiple times for a contact for this event. (Only enable if you want to restart automation for the same contact)', 'fluent-community'),
                'inline_help' => __('If you enable, then it will restart the automation for a contact if the contact already in the automation. Otherwise, It will just skip if already exist', 'fluent-community')
            ]
        ];
    }

    public function handle($funnel, $originalArgs)
    {
        $topic = $originalArgs[0];
        $userId = $originalArgs[1];

        $subscriberData = FunnelHelper::prepareUserData($userId);

        $subscriberData['source'] = 'fluent-community';

        if (empty($subscriberData['email'])) {
            return;
        }

        $willProcess = $this->isProcessable($funnel, $topic->id, $subscriberData);

        $willProcess = apply_filters('fluentcrm_funnel_will_process_' . $this->triggerName, $willProcess, $funnel, $subscriberData, $originalArgs);
        if (!$willProcess) {
            return;
        }

        $subscriberData = wp_parse_args($subscriberData, $funnel->settings);

        $subscriberData['status'] = $subscriberData['subscription_status'];
        unset($subscriberData['subscription_status']);

        (new FunnelProcessor())->startFunnelSequence($funnel, $subscriberData, [
            'source_trigger_name' => $this->triggerName,
            'source_ref_id'       => $topic->id
        ]);

    }

    private function isProcessable($funnel, $topicId, $subscriberData)
    {
        $conditions = $funnel->conditions;
        // check update_type
        $updateType = Arr::get($conditions, 'update_type');

        $subscriber = FunnelHelper::getSubscriber($subscriberData['email']);
        if ($subscriber && $updateType == 'skip_all_if_exist') {
            return false;
        }

        // check the products ids
        if ($conditions['topic_ids']) {
            return in_array($topicId, $conditions['topic_ids']);
        }

        // check run_only_one
        if ($subscriber && FunnelHelper::ifAlreadyInFunnel($funnel->id, $subscriber->id)) {
            $multipleRun = Arr::get($conditions, 'run_multiple') == 'yes';
            if ($multipleRun) {
                FunnelHelper::removeSubscribersFromFunnel($funnel->id, [$subscriber->id]);
            } else {
                return false;
            }
        }

        return true;
    }

    public function getTopicsByCourseGroup()
    {
        $courses = Course::query()->orderBy('title', 'ASC')->get();

        $groups = [];
        foreach ($courses as $course) {
            $group = [
                'title'   => $course->title,
                'slug'    => $course->slug,
                'options' => []
            ];

            $sections = CourseTopic::where('space_id', $course->id)
                ->orderBy('priority', 'ASC')
                ->with(['lessons'])
                ->get();


            foreach ($sections as $section) {
                $group['options'][] = [
                    'id'    => (string)$section->id,
                    'title' => $section->title
                ];
            }
            $groups[] = $group;
        }
        return $groups;
    }
}
