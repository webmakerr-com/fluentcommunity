<?php

namespace FluentCommunityPro\App\Services\Integrations\FluentCRM;

use FluentCommunity\Modules\Course\Model\Course;
use FluentCrm\App\Services\Funnel\BaseTrigger;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Framework\Support\Arr;

class CourseLeaveTrigger extends BaseTrigger
{
    public function __construct()
    {
        $this->triggerName = 'fluent_community/course/student_left';
        $this->priority = 50;
        $this->actionArgNum = 2;
        parent::__construct();
    }

    public function getTrigger()
    {
        return [
            'category'    => __('Community', 'fluent-community-pro'),
            'label'       => __('Unenrolled from a course', 'fluent-community-pro'),
            'description' => __('This automation will be initiated when a user unenroll from a course.', 'fluent-community-pro'),
            'icon'        => 'fc-icon-wp_new_user_signup',
        ];
    }

    public function getSettingsFields($funnel)
    {
        return [
            'title'     => __('Unenrolled from a course', 'fluent-community-pro'),
            'sub_title' => __('This automation will be initiated when a user unenroll from a course.', 'fluent-community-pro'),
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
                    'info'       => '<b>' . __('An Automated double-opt-in email will be sent for new subscribers', 'fluent-community-pro') . '</b>',
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
        $courses = Course::orderBy('title', 'ASC')->select(['id', 'title'])->get();

        return [
            'update_type'  => [
                'type'    => 'radio',
                'label'   => __('If Contact Already Exist?', 'fluent-community-pro'),
                'help'    => __('Please specify what will happen if the subscriber already exists in the database', 'fluent-community-pro'),
                'options' => FunnelHelper::getUpdateOptions()
            ],
            'course_ids'   => [
                'type'        => 'multi-select',
                'is_multiple' => true,
                'label'       => __('Targeted Courses', 'fluent-community-pro'),
                'help'        => __('Select which courses this automation is for.', 'fluent-community-pro'),
                'placeholder' => __('Select Communities', 'fluent-community-pro'),
                'options'     => $courses,
                'inline_help' => __('Leave blank to run for any courses', 'fluent-community-pro')
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
            'course_ids'   => [],
            'run_multiple' => 'yes'
        ];
    }

    public function handle($funnel, $originalArgs)
    {
        $course = $originalArgs[0];
        $userId = $originalArgs[1];

        $user = get_user_by('ID', $userId);

        if (!$user || !$this->isProcessable($funnel, $user, $course)) {
            return false;
        }

        $subscriberData = FunnelHelper::prepareUserData($user);

        $subscriberData = wp_parse_args($subscriberData, $funnel->settings);
        $subscriberData['status'] = $subscriberData['subscription_status'];
        unset($subscriberData['subscription_status']);

        (new FunnelProcessor())->startFunnelSequence($funnel, $subscriberData, [
            'source_trigger_name' => $this->triggerName,
            'source_ref_id'       => $course->id
        ]);
    }

    private function isProcessable($funnel, $user, $course)
    {
        $conditions = $funnel->conditions;
        // check update_type
        $updateType = Arr::get($conditions, 'update_type');

        $subscriber = FunnelHelper::getSubscriber($user->user_email);
        if ($updateType == 'skip_all_if_exist' && $subscriber) {
            return false;
        }

        // check user roles
        if ($checkIds = Arr::get($conditions, 'course_ids', [])) {
            if (!in_array($course->id, $checkIds)) {
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
