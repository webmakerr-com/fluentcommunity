<?php

namespace FluentCommunityPro\App\Services\Integrations\FluentCRM;

use FluentCommunity\App\Models\User;
use FluentCommunity\Modules\Course\Model\Course;
use FluentCommunity\Modules\Course\Services\CourseHelper;
use FluentCrm\App\Services\Funnel\BaseAction;
use FluentCrm\App\Services\Funnel\FunnelHelper;

class RemoveFromCourseAction extends BaseAction
{
    public function __construct()
    {
        $this->actionName = 'remove_from_fluent_community_course';
        $this->priority = 100;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'category'    => __('Community', 'fluent-community-pro'),
            'title'       => __('Remove Courses', 'fluent-community-pro'),
            'description' => __('Remove user from the selected courses', 'fluent-community-pro'),
            'icon'        => 'fc-icon-apply_list',
            'settings'    => [
                'course_ids' => []
            ]
        ];
    }

    public function getBlockFields()
    {

        $courses = Course::orderBy('title', 'ASC')->select(['id', 'title'])->get();

        return [
            'title'     => __('Remove from Courses', 'fluent-community-pro'),
            'sub_title' => __('Remove user to the selected Courses', 'fluent-community-pro'),
            'fields'    => [
                'course_ids' => [
                    'type'    => 'multi-select',
                    'label'   => __('Select Courses', 'fluent-community-pro'),
                    'options' => $courses
                ]
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        if (empty($sequence->settings['course_ids'])) {
            FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'skipped');
            return;
        }

        $courseIds = $sequence->settings['course_ids'];

        $user = User::where('user_email', $subscriber->email)->first();

        if (!$user) {
            $funnelMetric->status = 'skipped';
            $funnelMetric->notes = 'Skipped because no user found';
            $funnelMetric->save();
            FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'skipped');
            return;
        }

        $results = CourseHelper::leaveCourses($courseIds, $user->ID, 'by_admin');

        if (!$results) {
            $funnelMetric->status = 'skipped';
            $funnelMetric->notes = 'Skipped because no course found or the user does not have the enrollments';
            $funnelMetric->save();
            FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'skipped');
            return;
        }

        return true;
    }

}
