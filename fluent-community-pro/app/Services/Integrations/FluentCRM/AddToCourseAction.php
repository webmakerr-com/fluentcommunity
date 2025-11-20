<?php

namespace FluentCommunityPro\App\Services\Integrations\FluentCRM;

use FluentCommunity\App\Models\User;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\Modules\Course\Model\Course;
use FluentCommunity\Modules\Course\Services\CourseHelper;
use FluentCommunityPro\App\Services\ProHelper;
use FluentCrm\App\Services\Funnel\BaseAction;
use FluentCrm\App\Services\Funnel\FunnelHelper;

class AddToCourseAction extends BaseAction
{
    public function __construct()
    {
        $this->actionName = 'add_to_fluent_community_course';
        $this->priority = 100;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'category'    => __('Community', 'fluent-community-pro'),
            'title'       => __('Enroll to Courses', 'fluent-community-pro'),
            'description' => __('Add user to the selected courses', 'fluent-community-pro'),
            'icon'        => 'fc-icon-apply_list',
            'settings'    => [
                'course_ids'            => [],
                'send_wp_welcome_email' => 'yes'
            ]
        ];
    }

    public function getBlockFields()
    {

        $courses = Course::orderBy('title', 'ASC')->select(['id', 'title'])->get();

        return [
            'title'     => __('Enroll to Courses', 'fluent-community-pro'),
            'sub_title' => __('Add user to the selected Courses', 'fluent-community-pro'),
            'fields'    => [
                'course_ids'            => [
                    'type'    => 'multi-select',
                    'label'   => __('Select Courses', 'fluent-community-pro'),
                    'options' => $courses
                ],
                'role_info'             => [
                    'type' => 'html',
                    'info' => '<p><b>' . __('A new WordPress user will be created if the contact does not have a connect WP User.', 'fluent-community-pro') . '</b></p>',
                ],
                'send_wp_welcome_email' => [
                    'type'        => 'yes_no_check',
                    'label' => '',
                    'check_label' => __('Send WordPress Welcome Email for new WP Users', 'fluent-community-pro'),
                    'inline_help' => __('If you enable this, . The newly created user will get the welcome email send by WordPress to with the login info & password reset link', 'fluent-community-pro')
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

        $courseIds = (array) $sequence->settings['course_ids'];
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

            if(Arr::get($sequence->settings, 'send_wp_welcome_email') === 'yes') {
                wp_new_user_notification($userId, null, 'user');
            }

            $subscriber->getWpUser();
            $user = User::find($userId);
        }

        $user->syncXProfile(false); // just in case we need to sync the xprofile
        $results = CourseHelper::enrollCourses($courseIds, $user->ID, 'by_admin');

        if (!$results) {
            $funnelMetric->status = 'skipped';
            $funnelMetric->notes = 'Skipped because no course found or the user is already enrolled';
            $funnelMetric->save();
            FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'skipped');
            return;
        }

        return true;
    }

}
