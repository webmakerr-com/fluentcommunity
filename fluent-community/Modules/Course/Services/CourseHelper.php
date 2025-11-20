<?php

namespace FluentCommunity\Modules\Course\Services;

use FluentCommunity\App\Models\Activity;
use FluentCommunity\App\Models\Meta;
use FluentCommunity\App\Models\Reaction;
use FluentCommunity\App\Models\SpaceUserPivot;
use FluentCommunity\App\Models\Term;
use FluentCommunity\App\Services\CustomSanitizer;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\App\Services\SmartCodeParser;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\Modules\Course\Model\Course;
use FluentCommunity\Modules\Course\Model\CourseLesson;
use FluentCommunity\Modules\Course\Model\CourseTopic;

class CourseHelper
{
    public static function getCourseProgressTrack($courseId, $userId = null)
    {
        $isEnrolled = self::isEnrolled($courseId, $userId);

        return [
            'completed_lessons' => $isEnrolled ? self::getCompletedLessonIds($courseId, $userId) : [],
            'isEnrolled'        => $isEnrolled,
            'progress'          => $isEnrolled ? self::getCourseProgress($courseId, $userId) : 0,
        ];
    }

    public static function isEnrolled($courseId, $userId = null)
    {
        if (!$userId) {
            $userId = get_current_user_id();
        }

        if (!$userId) {
            return false;
        }

        return SpaceUserPivot::where('space_id', $courseId)
            ->where('user_id', $userId)
            ->exists();
    }

    public static function getCourseEnrollment($courseId, $userId = null)
    {
        if (!$userId) {
            $userId = get_current_user_id();
        }

        if (!$userId) {
            return null;
        }

        return SpaceUserPivot::where('space_id', $courseId)
            ->where('user_id', $userId)
            ->first();

    }

    public static function getCoursePublishedLessonIds($courseId)
    {
        static $counts = [];

        if (isset($counts[$courseId])) {
            return $counts[$courseId];
        }

        $counts[$courseId] = CourseLesson::where('space_id', $courseId)
            ->where('status', 'published')
            ->pluck('id')
            ->toArray();

        return $counts[$courseId];
    }

    public static function getCourseProgress($courseId, $userId = null)
    {
        if (!$userId) {
            $userId = get_current_user_id();
        }

        if (!$userId) {
            return 0;
        }

        $lessonIds = self::getCoursePublishedLessonIds($courseId);

        if (!$lessonIds) {
            return 0;
        }

        $completedLessons = Reaction::where('user_id', $userId)
            ->whereIn('object_id', $lessonIds)
            ->where('object_type', 'lesson_completed')
            ->where('type', 'completed')
            ->count();

        if (!$completedLessons) {
            return 0;
        }

        $result = floor(($completedLessons / count($lessonIds)) * 100);

        if (!$result) {
            return 0;
        }

        if ($result > 100) {
            return 100;
        }

        return $result;

    }

    public static function getCompletedLessonIds($courseId, $userId = null)
    {
        if (!$userId) {
            $userId = get_current_user_id();
        }

        if (!$userId) {
            return [];
        }

        return Reaction::where('user_id', $userId)
            ->where('parent_id', $courseId)
            ->where('object_type', 'lesson_completed')
            ->where('type', 'completed')
            ->pluck('object_id')
            ->toArray();
    }

    public static function overallCourseProgressAverage($course)
    {
        $lessonsCount = CourseLesson::where('space_id', $course->id)
            ->where('status', 'published')
            ->count();

        if (!$lessonsCount) {
            return 0;
        }

        $allCompletedCount = Reaction::where('object_type', 'lesson_completed')
            ->where('type', 'completed')
            ->where('parent_id', $course->id)
            ->count();

        $studentsCount = $course->students_count ? $course->students_count : $course->students()->count();

        if (!$studentsCount) {
            return 0;
        }

        // calculate the average of all students
        return ceil(($allCompletedCount / ($studentsCount * $lessonsCount)) * 100);
    }

    public static function updateLessonCompletion($lesson, $userId, $state = 'completed')
    {
        $reaction = Reaction::where('user_id', $userId)
            ->where('object_id', $lesson->id)
            ->where('object_type', 'lesson_completed')
            ->first();

        if ($reaction) {
            if ($state != 'completed') {
                $reaction->type = 'incomplete';
                $reaction->save();
            } else {
                $reaction->type = 'completed';
                $reaction->save();
            }
            return true;
        }

        Reaction::create([
            'user_id'     => $userId,
            'object_id'   => $lesson->id,
            'object_type' => 'lesson_completed',
            'parent_id'   => $lesson->space_id,
            'type'        => 'completed'
        ]);

        do_action('fluent_community/course/lesson_completed', $lesson, $userId);

        // check if the whole module is completed
        $allTopicLessonIds = CourseLesson::where('space_id', $lesson->space_id)
            ->where('parent_id', $lesson->parent_id)
            ->where('status', 'published')
            ->pluck('id')
            ->toArray();

        $completedLessonCount = Reaction::where('user_id', $userId)
            ->whereIn('object_id', $allTopicLessonIds)
            ->where('object_type', 'lesson_completed')
            ->where('type', 'completed')
            ->count();

        if (count($allTopicLessonIds) == $completedLessonCount) {
            $topic = CourseTopic::find($lesson->parent_id);
            do_action('fluent_community/course/topic_completed', $topic, $userId, $lesson);
        }

        return true;
    }

    public static function getCourseMeta($key, $default = false, $withModel = false)
    {
        $meta = Meta::where('meta_key', $key)
            ->where('object_type', 'course')
            ->first();

        if ($withModel) {
            return $meta;
        }

        return $meta ? $meta->value : $default;
    }

    public static function updateCourseMeta($key, $value)
    {
        $meta = Meta::where('meta_key', $key)
            ->where('object_type', 'course')
            ->first();

        if ($meta) {
            $meta->update([
                'value' => $value
            ]);
            return $meta;
        }

        return Meta::create([
            'meta_key'    => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'value'       => $value,
            'object_type' => 'course'
        ]);
    }

    public static function completeCourse($course, $lesson, $userId)
    {
        $activity = Activity::where('feed_id', $course->id)
            ->where('user_id', $userId)
            ->where('action_name', 'course_completed')
            ->first();

        if ($activity) {
            if (strtotime($lesson->scheduled_at) > strtotime($activity->updated_at)) {
                $activity->updated_at = current_time('mysql');
                $activity->save();
                do_action('fluent_community/course/completed', $course, $userId);
                return true;
            }
            return false;
        }

        Activity::create([
            'feed_id'     => $course->id,
            'user_id'     => $userId,
            'action_name' => 'course_completed'
        ]);

        do_action('fluent_community/course/completed', $course, $userId);

        return true;
    }

    public static function enrollCourse($course, $userId = null, $by = 'self')
    {
        return Helper::addToSpace($course, $userId, 'student', $by);
    }

    public static function enrollCourses($courseIds, $userId = null, $by = 'self')
    {
        if (!$userId) {
            $userId = get_current_user_id();
        }

        $enrolledCourseIds = [];

        foreach ($courseIds as $courseId) {
            $course = Course::find($courseId);
            if ($course) {
                if (self::enrollCourse($course, $userId, $by)) {
                    $enrolledCourseIds[] = $courseId;
                }
            }
        }

        return $enrolledCourseIds;
    }

    public static function leaveCourse($course, $userId = null, $by = 'self')
    {
        if (!$userId) {
            $userId = get_current_user_id();
        }

        if (!$userId) {
            return false;
        }

        return Helper::removeFromSpace($course, $userId, $by);
    }

    public static function leaveCourses($courseIds, $userId = null, $by = 'self')
    {
        if (!$userId) {
            $userId = get_current_user_id();
        }

        $processedCourseIds = [];

        foreach ($courseIds as $courseId) {
            $course = Course::find($courseId);
            if ($course) {
                if (self::leaveCourse($course, $userId, $by)) {
                    $processedCourseIds[] = $courseId;
                }
            }

        }

        return $processedCourseIds;
    }

    public static function getEnrolledCourseIds($userId = null)
    {
        if (!$userId) {
            $userId = get_current_user_id();
        }

        if (!$userId) {
            return [];
        }

        return SpaceUserPivot::where('user_id', $userId)
            ->where('role', 'student')
            ->pluck('space_id')
            ->toArray();

    }

    public static function getSectionAccessDate($section, $courseType, $enrollment = null)
    {
        if ($courseType == 'slef_paced') {
            return null;
        }

        if ($courseType == 'scheduled') {
            return $section->scheduled_at;
        }

        // This is for structured course
        if (!$enrollment) {
            return null;
        }

        $accessAfterEnrollment = $section->reactions_count;

        return gmdate('Y-m-d H:i:s', strtotime($enrollment->created_at) + $accessAfterEnrollment * 86400);
    }

    public static function sanitizeLessonMeta($meta, $lesson)
    {
        $validFields = [
            'enable_comments',
            'enable_media',
            'media',
            'video_length',
            'featured_image_id',
            'free_preview_lesson'
        ];

        if ($lesson->isQuizType()) {
            $quizFields = ['quiz_questions', 'passing_score', 'enable_passing_score', 'enforce_passing_score', 'hide_result'];
            $validFields = wp_parse_args($validFields, $quizFields);
        }

        $meta = Arr::only($meta, $validFields);

        $yesNoFields = ['enable_comments', 'enable_media', 'free_preview_lesson', 'enable_passing_score', 'enforce_passing_score', 'hide_result'];

        foreach ($yesNoFields as $field) {
            $meta[$field] = Arr::get($meta, $field, 'no') == 'yes' ? 'yes' : 'no';
        }

        if (Arr::get($meta, 'enable_media') == 'yes') {
            if ($media = Arr::get($meta, 'media', [])) {
                $meta['media'] = self::sanitizeMedia($media);
            }
        } else {
            $meta['media'] = [
                'provider' => ''
            ];
        }

        $numericFields = ['video_length', 'passing_score'];

        foreach ($numericFields as $field) {
            $meta[$field] = absint(Arr::get($meta, $field, 0));
        }

        return apply_filters('fluent_community/lesson/sanitize_meta', $meta, $lesson);
    }

    public static function sanitizeMedia($media)
    {
        return array_filter([
            'type'         => sanitize_text_field(Arr::get($media, 'type', '')),
            'url'          => sanitize_url(Arr::get($media, 'url', '')),
            'content_type' => sanitize_text_field(Arr::get($media, 'content_type', '')),
            'provider'     => sanitize_url(Arr::get($media, 'provider', '')),
            'title'        => sanitize_text_field(Arr::get($media, 'title', '')),
            'author_name'  => sanitize_text_field(Arr::get($media, 'author_name', '')),
            'html'         => CustomSanitizer::sanitizeRichText(Arr::get($media, 'html', '')),
            'image'        => sanitize_url(Arr::get($media, 'image', ''))
        ]);
    }

    public static function getCourseCategories()
    {
        $terms = Term::whereHas('base_spaces', function ($q) {
            $q->where('type', 'course');
        })->get();

        $formattedTerms = [];

        foreach ($terms as $term) {
            $formattedTerms[] = [
                'id'    => $term->id,
                'title' => $term->title,
                'slug'  => $term->slug
            ];
        }

        return $formattedTerms;
    }

    public static function getUserCourses($userId = null)
    {
        if (!$userId) {
            $userId = get_current_user_id();
        }

        if (!$userId) {
            return null;
        }

        return Course::whereHas('students', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            ->with('enrollment', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->get();
    }

    public static function santizeLessonBody($body)
    {
        if (current_user_can('unfiltered_html')) {
            return $body;
        }

        return wp_kses_post($body);
    }

    public static function formatLessonData($course, $lesson, $user = null, $config = [])
    {
        $canViewLesson = Arr::get($config, 'can_view', false);
        $parseContent = Arr::get($config, 'parse_content', true);

        if ($parseContent && $canViewLesson) {
            $content = $lesson->message_rendered;
            if (!$content && $lesson->message) {
                $content = apply_filters('the_content', $lesson->message); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            }
            if (!$content) {
                $content = '';
            }
            $content = (new SmartCodeParser())->parse($content, $user);
        } else {
            $content = '';
        }

        $formattedLesson = [
            'id'             => $lesson->id,
            'title'          => $lesson->title,
            'content'        => $content,
            'slug'           => $lesson->slug,
            'course_id'      => $lesson->space_id,
            'section_id'     => $lesson->parent_id,
            'created_at'     => $lesson->created_at->format('Y-m-d H:i:s'),
            'content_type'   => $lesson->content_type,
            'featured_image' => $lesson->featured_image,
            'meta'           => $lesson->getPublicLessonMeta($canViewLesson),
            'comments_count' => $lesson->comments_count,
            'is_locked'      => Arr::get($config, 'is_locked', false),
            'unclock_date'   => Arr::get($config, 'unclock_date', ''),
            'can_view'       => $canViewLesson
        ];

        if (!$canViewLesson) {
            $formattedLesson['access_message'] = static::getAccessMessage($course, $lesson, $config);
        }

        return $formattedLesson;
    }

    public static function getAccessMessage($course, $lesson, $config)
    {
        $isLocked = Arr::get($config, 'is_locked', false);
        $unlockDate = Arr::get($config, 'unclock_date', '');
        $courseLessonsUrl = Helper::baseUrl('/course/' . $course->slug . '/lessons');

        $headerMessage = __('This lesson is currently locked', 'fluent-community');
        $bodyMessage = __('Please enroll in this course to access this lesson', 'fluent-community');
        $backToCourseText = __('Back to Course', 'fluent-community');

        if ($isLocked && $unlockDate) {
            $headerMessage = __('This lesson is not published for you yet', 'fluent-community');
            /* translators: %s is replaced by the date */
            $bodyMessage = sprintf(__('It will be available to you on %s', 'fluent-community'),
                date_i18n(get_option('date_format'), strtotime($unlockDate))
            );
        }

        /* translators: %s is replaced by the header message, %s is replaced by the body message, %s is replaced by the course lessons url, %s is replaced by the back to course text */
        $accessMessage = sprintf('<div class="fcom_locker"><h1>%s</h1><p>%s</p><a href="%s" class="el-button el-button--info">%s</a></div>',
            $headerMessage,
            $bodyMessage,
            $courseLessonsUrl,
            $backToCourseText
        );

        return apply_filters('fluent_community/course/access_message_html', $accessMessage, $course, $lesson, $config);
    }

    public static function getParsedEmailSubject($text, $section, $user)
    {
        $parsedSubject = (new SmartCodeParser())->parse($text, $user, $section, false);

        return $parsedSubject;
    }

    public static function getParsedEmailBody($text, $section, $user)
    {
        $parsedBody = (new SmartCodeParser())->parse($text, $user, $section);

        $emailComposer = new \FluentCommunity\App\Services\Libs\EmailComposer();

        $emailComposer->addBlock('html_content', $parsedBody);
        $emailComposer->setDefaultLogo();
        $emailComposer->setDefaultFooter();

        return $emailComposer->getHtml();
    }
}
