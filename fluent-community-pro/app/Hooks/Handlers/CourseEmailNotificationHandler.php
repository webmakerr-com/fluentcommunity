<?php

namespace FluentCommunityPro\App\Hooks\Handlers;

use FluentCommunity\App\Models\User;
use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Services\Libs\Mailer;
use FluentCommunity\App\Services\FeedsHelper;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\Modules\Course\Services\CourseHelper;
use FluentCommunity\Modules\Course\Model\CourseTopic;
use FluentCommunityPro\App\Services\ProHelper;
use FluentCommunity\App\Services\CustomSanitizer;

class CourseEmailNotificationHandler
{
    private $maxRunTime = 0;

    public function register()
    {
        add_filter('fluent_community/portal_vars', [$this, 'addPortalVars'], 10, 1);
        add_filter('fluent_community/section/update_data', [$this, 'maybeUpdateNotificationData'], 10, 4);

        add_action('fluent_community/course/updated', [$this, 'maybeUpdatedCourseType'], 10, 3);

        add_action('fluent_community/section/before_deleted', [$this, 'unscheduleNotification'], 10, 1);

        add_action('fluent_community/course/enrolled', [$this, 'initEnrolledNotification'], 10, 2);

        add_action('fluent_community/course/student_left', [$this, 'unscheduleEnrolledNotification'], 10, 2);

        add_action('fluent_community/section/scheduled_at_updated', [$this, 'resetScheduledNotification'], 10, 2);
        add_action('fluent_community/section/reactions_count_updated', [$this, 'resetStructuredNotification'], 10, 2);

        add_action('fluent_community/course/scheduled/init_notification', [$this, 'initScheduledNotification'], 10, 2);
        add_action('fluent_community/course/scheduled/unschedule_notification', [$this, 'unscheduleScheduledNotification'], 10, 2);

        add_action('fluent_community/course/structured/init_notification', [$this, 'initStructuredNotification'], 10, 2);
        add_action('fluent_community/course/structured/unschedule_notification', [$this, 'unscheduleStructuredNotification'], 10, 2);

        add_action('fluent_community/course/scheduled/send_notification_async', [$this, 'sendScheduledNotificationAsync'], 10, 1);
        add_action('fluent_community/course/structured/send_notification_async', [$this, 'sendStructuredNotificationAsync'], 10, 2);
    }

    public function addPortalVars($vars)
    {
        $vars['default_course_email'] = ProHelper::getDefaultCourseNotification();
        $vars['course_smart_codes'] = ProHelper::getCourseSmartCodes();
        return $vars;
    }

    public function maybeUpdateNotificationData($sectionData, $course, $section, $requestData)
    {
        if (!$section || !Arr::get($requestData, 'meta')) {
            return $sectionData;
        }

        $prevStatus = Arr::get($section->meta, 'email_enabled') === 'yes' ? 'yes' : 'no';
        $newStatus = Arr::get($requestData, 'meta.email_enabled') === 'yes' ? 'yes' : 'no';
        $emailBody = wp_kses_post(CustomSanitizer::unslashMarkdown(Arr::get($requestData, 'meta.email_body', '')));

        $sectionData['meta']['email_enabled'] = $newStatus;
        $sectionData['meta']['email_subject'] = sanitize_text_field(Arr::get($requestData, 'meta.email_subject', ''));
        $sectionData['meta']['email_body'] = $emailBody;
        $sectionData['meta']['email_body_rendered'] = FeedsHelper::mdToHtml($emailBody);

        if ($prevStatus == $newStatus) {
            return $sectionData;
        }

        $courseType = $course->getCourseType();

        $isEnabled = $newStatus === 'yes';

        if ($isEnabled && (
                ($courseType === 'scheduled' && empty($section->scheduled_at)) ||
                ($courseType === 'structured' && empty($section->reactions_count))
            )) {
            return new \WP_Error(
                'scheduled_at_required',
                __('Schedule date is required to enable notification.', 'fluent-community-pro')
            );
        }

        $action = $isEnabled
            ? "fluent_community/course/{$courseType}/init_notification"
            : "fluent_community/course/{$courseType}/unschedule_notification";


        do_action($action, $course, $section);

        return $sectionData;
    }

    public function maybeUpdatedCourseType($course, $updatedFields, $prevCourse)
    {
        $prevType = Arr::get($prevCourse->settings, 'course_type');
        $newType = Arr::get($course->settings, 'course_type');
        if ($prevType == $newType) {
            return;
        }

        $sections = $course->course_topics;

        foreach ($sections as $section) {
            $meta = $section->meta;
            if (Arr::get($meta, 'email_enabled') == 'no') {
                continue;
            }
            $meta['email_enabled'] = 'no';
            $section->meta = $meta;
            $section->save();

            do_action('fluent_community/course/' . $prevType . '/unschedule_notification', $course, $section);
        }
    }

    public function unscheduleNotification($section)
    {
        $course = $section->course;

        $courseType = $course->getCourseType();

        do_action('fluent_community/course/' . $courseType . '/unschedule_notification', $course, $section);
    }

    public function initEnrolledNotification($course, $userId)
    {
        $courseType = Arr::get($course->settings, 'course_type', 'self_paced');
        if ($courseType != 'structured') {
            return;
        }

        $enrollment = $course->student_enrollments()->where('user_id', $userId)->first();
        if (!$enrollment) {
            return;
        }

        foreach ($course->course_topics as $section) {
            if (Arr::get($section->meta, 'email_enabled') != 'yes') {
                continue;
            }

            $scheduleTime = CourseHelper::getSectionAccessDate($section, 'structured', $enrollment);
            $scheduleWpTime = new \DateTime($scheduleTime, wp_timezone());
            if ((!$scheduleWpTime) || ($scheduleWpTime->getTimestamp() <= current_datetime()->getTimestamp())) {
                continue;
            }

            $scheduleUtcTime = $scheduleWpTime->setTimezone(new \DateTimeZone('UTC'));
            $scheduleUtcTime = $scheduleUtcTime->getTimestamp();

            \as_schedule_single_action($scheduleUtcTime, 'fluent_community/course/structured/send_notification_async', [$section->id, (int)$userId], 'fluent-community');
        }
    }

    public function unscheduleEnrolledNotification($course, $userId)
    {
        $courseType = Arr::get($course->settings, 'course_type', 'self_paced');
        if ($courseType != 'structured') {
            return;
        }

        foreach ($course->course_topics as $section) {
            if (Arr::get($section->meta, 'email_enabled') != 'yes') {
                continue;
            }

            \as_unschedule_all_actions('fluent_community/course/structured/send_notification_async', [$section->id, (int)$userId], 'fluent-community');
        }
    }

    public function initScheduledNotification($course, $section)
    {
        $scheduledAt = new \DateTime($section->scheduled_at, wp_timezone());
        if (($scheduledAt->getTimestamp() <= current_datetime()->getTimestamp())) {
            return;
        }

        $scheduleUtcTime = $scheduledAt->setTimezone(new \DateTimeZone('UTC'));
        $scheduleUtcTime = $scheduleUtcTime->getTimestamp();

        \as_schedule_single_action($scheduleUtcTime, 'fluent_community/course/scheduled/send_notification_async', [$section->id], 'fluent-community');
    }

    public function initStructuredNotification($course, $section)
    {
        $enrolledStudents = $course->student_enrollments;

        foreach ($enrolledStudents as $enrollment) {
            $scheduleTime = CourseHelper::getSectionAccessDate($section, 'structured', $enrollment);
            $scheduleWpTime = new \DateTime($scheduleTime, wp_timezone());
            if ((!$scheduleWpTime) || ($scheduleWpTime->getTimestamp() <= current_datetime()->getTimestamp())) {
                continue;
            }

            $scheduleUtcTime = $scheduleWpTime->setTimezone(new \DateTimeZone('UTC'));
            $scheduleUtcTime = $scheduleUtcTime->getTimestamp();

            \as_schedule_single_action($scheduleUtcTime, 'fluent_community/course/structured/send_notification_async', [$section->id, (int)$enrollment->user_id], 'fluent-community');
        }
    }

    public function unscheduleScheduledNotification($course, $section)
    {
        \as_unschedule_all_actions('fluent_community/course/scheduled/send_notification_async', [$section->id], 'fluent-community');
    }

    public function unscheduleStructuredNotification($course, $section)
    {
        $enrolledStudents = $course->student_enrollments;

        foreach ($enrolledStudents as $enrollment) {
            \as_unschedule_all_actions('fluent_community/course/structured/send_notification_async', [$section->id, (int)$enrollment->user_id], 'fluent-community');
        }
    }

    public function resetScheduledNotification($course, $section)
    {
        if (Arr::get($section->meta, 'email_enabled') != 'yes') {
            return;
        }

        do_action('fluent_community/course/scheduled/unschedule_notification', $course, $section);
        do_action('fluent_community/course/scheduled/init_notification', $course, $section);
    }

    public function resetStructuredNotification($course, $section)
    {
        if (Arr::get($section->meta, 'email_enabled') != 'yes') {
            return;
        }

        do_action('fluent_community/course/structured/unschedule_notification', $course, $section);
        do_action('fluent_community/course/structured/init_notification', $course, $section);
    }

    public function sendScheduledNotificationAsync($sectionId)
    {
        $this->maxRunTime = $this->maxRunTime ?: Utility::getMaxRunTime();

        $section = CourseTopic::where('id', $sectionId)
            ->whereHas('course', function ($query) {
                $query->where('status', 'published');
            })
            ->with('course')
            ->first();

        $course = $section->course;
        $isEnabled = Arr::get($section->meta, 'email_enabled') == 'yes';
        if (!$section || !$course || !$isEnabled) {
            return;
        }

        $lastSendUserId = $section->getCustomMeta('last_send_user_id', 0);

        $activeStudents = $course->students()
            ->whereHas('xprofile', function ($query) {
                $query->where('status', 'active');
            })
            ->when($lastSendUserId, function ($q) use ($lastSendUserId) {
                $q->where('users.ID', '>', $lastSendUserId);
            })
            ->orderBy('ID', 'ASC')
            ->limit(100)
            ->get();

        if ($activeStudents->isEmpty()) {
            return;
        }

        $emailSubject = Arr::get($section->meta, 'email_subject');
        $emailBody = Arr::get($section->meta, 'email_body_rendered');

        $startTime = microtime(true);
        $maxSendPerSecond = 10; // max 10 emails per second

        foreach ($activeStudents as $index => $student) {
            $lastSendUserId = $student->ID;

            $parsedSubject = CourseHelper::getParsedEmailSubject($emailSubject, $section, $student);
            $parsedBody = CourseHelper::getParsedEmailBody($emailBody, $section, $student);

            $mailer = new Mailer('', $parsedSubject, $parsedBody);
            $mailer->to($student->user_email, $student->display_name);
            $mailer->send();

            if (($index + 1) % $maxSendPerSecond == 0) {
                $timeTaken = microtime(true) - $startTime;
                if ($timeTaken < 1) {
                    usleep((int)(1000000 - ($timeTaken * 1000000)));
                }
                $startTime = microtime(true);
            }
        }

        $section->updateCustomMeta('last_send_user_id', $lastSendUserId);

        if (microtime(true) - FLUENT_COMMUNITY_START_TIME > $this->maxRunTime) {
            as_schedule_single_action(time(), 'fluent_community/course/scheduled/send_notification_async', [$sectionId], 'fluent-community');
            return;
        }

        return $this->sendScheduledNotificationAsync($sectionId);
    }

    public function sendStructuredNotificationAsync($sectionId, $userId)
    {
        $section = CourseTopic::where('id', $sectionId)
            ->whereHas('course', function ($query) {
                $query->where('status', 'published');
            })
            ->with('course')
            ->first();

        $course = $section->course;
        $isEnabled = Arr::get($section->meta, 'email_enabled') == 'yes';
        if (!$section || !$course || !$isEnabled) {
            return;
        }

        $student = User::find($userId);
        if (!$student) {
            return;
        }

        $emailSubject = Arr::get($section->meta, 'email_subject');
        $emailBody = Arr::get($section->meta, 'email_body_rendered');

        $parsedSubject = CourseHelper::getParsedEmailSubject($emailSubject, $section, $student);
        $parsedBody = CourseHelper::getParsedEmailBody($emailBody, $section, $student);

        $mailer = new Mailer('', $parsedSubject, $parsedBody);
        $mailer->to($student->user_email, $student->display_name);
        $mailer->send();
    }
}
