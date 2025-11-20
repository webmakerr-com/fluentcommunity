<?php

namespace FluentCommunity\Modules\Course\Http\Controllers;

use FluentCommunity\App\Http\Controllers\Controller;
use FluentCommunity\App\Models\SpaceUserPivot;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\App\Services\LockscreenService;
use FluentCommunity\Framework\Http\Request\Request;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\Modules\Course\Model\Course;
use FluentCommunity\Modules\Course\Model\CourseLesson;
use FluentCommunity\Modules\Course\Model\CourseTopic;
use FluentCommunity\App\Services\FeedsHelper;
use FluentCommunity\Modules\Course\Services\CourseHelper;

class CourseController extends Controller
{
    public function getCourses(Request $request)
    {
        $user = $this->getUser();
        $isCourseCreator = $user && $user->hasCourseCreatorAccess();
        $enrolledIds = CourseHelper::getEnrolledCourseIds();

        $search = $request->getSafe('search');
        $topicSlug = $request->getSafe('topic_slug');
        $isEnrolled = $request->getSafe('type') == 'enrolled';
        $sortBy = $request->getSafe('sort_by', 'sanitize_text_field', 'alphabetical');

        $courses = Course::searchBy($search)
            ->when(!$isCourseCreator || $isEnrolled, function ($q) {
                $q->where('status', 'published');
            })
            ->byPostTopic($topicSlug)
            ->where(function ($q) use ($enrolledIds) {
                $q->whereIn('privacy', ['public', 'private']);
                $q->orWhereIn('id', $enrolledIds);
            })
            ->when($isEnrolled, function ($q) use ($enrolledIds) {
                $q->whereIn('id', $enrolledIds);
            })
            ->when($sortBy == 'alphabetical', function ($q) {
                $q->orderBy('title', 'ASC');
            })
            ->when($sortBy == 'oldest', function ($q) {
                $q->orderBy('created_at', 'ASC');
            })
            ->when($sortBy == 'latest', function ($q) {
                $q->orderBy('created_at', 'DESC');
            })
            ->paginate();

        foreach ($courses as $course) {
            $course->isEnrolled = CourseHelper::isEnrolled($course->id);
            if ($course->isEnrolled) {
                $course->progress = CourseHelper::getCourseProgress($course->id);
            }

            if (!$course->cover_photo) {
                $course->cover_photo = FLUENT_COMMUNITY_PLUGIN_URL . 'assets/images/course-placeholder.jpg';
            }

            $course->sectionsCount = CourseTopic::where('space_id', $course->id)->count();
            $course->lessonsCount = CourseLesson::where('space_id', $course->id)->count();
            if (Arr::get($course->settings, 'hide_members_count') != 'yes') {
                $course->studentsCount = SpaceUserPivot::where('space_id', $course->id)->count();
            } else {
                $course->studentsCount = 0;
            }
        }

        return [
            'courses'           => $courses,
            'course_categories' => $request->get('with_categories') ? CourseHelper::getCourseCategories() : []
        ];
    }

    public function getCourse(Request $request, $courseId)
    {
        $user = $this->getUser();
        $isCourseCreator = $user && $user->hasCourseCreatorAccess();

        $course = Course::when(!$isCourseCreator, function ($q) {
            $q->where('status', 'published');
        })->find($courseId);

        if (!$course) {
            return $this->sendError([
                'message' => __('Course not found. maybe the course is not published yet!', 'fluent-community')
            ]);
        }

        $intendtedLessonSlug = $request->get('intended_lesson_slug');

        return $this->processCourse($course, $isCourseCreator, $intendtedLessonSlug);
    }

    public function getCourseBySlug(Request $request, $slug)
    {
        $user = $this->getUser();
        $isCourseCreator = $user && $user->hasCourseCreatorAccess();

        $course = Course::when(!$isCourseCreator, function ($q) {
                $q->where('status', 'published');
            })
            ->where('slug', $slug)
            ->firstOrFail();

        if (!$course) {
            return $this->sendError([
                'message' => __('Course not found. maybe the course is not published yet!', 'fluent-community')
            ]);
        }

        $hideInstructorView = Arr::get($course->settings, 'hide_instructor_view') == 'yes';
        $showStudentCount = Arr::get($course->settings, 'show_instructor_students_count') == 'yes';
        if (!$hideInstructorView) {
            $course->load(['creator']);
            if ($course->creator) {
                $creatorCourseIds = Course::where('created_by', $course->creator->user_id)->pluck('id');
                $course->creator->total_courses = count($creatorCourseIds);
                if ($showStudentCount) {
                    $course->creator->total_students = SpaceUserPivot::whereIn('space_id', $creatorCourseIds)->distinct('user_id')->count('user_id');
                }
                if ($course->creator->short_description) {
                    $course->creator->short_description_rendered = wp_kses_post(FeedsHelper::mdToHtml($course->creator->short_description));
                }
            }
        }

        $intendtedLessonSlug = $request->get('intended_lesson_slug');

        return $this->processCourse($course, $isCourseCreator, $intendtedLessonSlug);
    }

    public function getLessonBySlug(Request $request, $courseSlug, $lessonSlug)
    {
        $user = $this->getUser();
        $isCourseCreator = $user && $user->hasCourseCreatorAccess();

        $course = Course::when(!$isCourseCreator, function ($q) {
            $q->where('status', 'published');
        })->where('slug', $courseSlug)->firstOrFail();

        if (!$course) {
            return $this->sendError([
                'message' => __('Course not found. maybe the course is not published yet!', 'fluent-community')
            ]);
        }

        $lesson = CourseLesson::where('space_id', $course->id)
            ->where('slug', $lessonSlug)
            ->when(!$isCourseCreator, function ($q) {
                $q->where('status', 'published');
            })
            ->with(['topic'])
            ->firstOrFail();

        if (!$lesson || !$lesson->topic) {
            return $this->sendError([
                'message' => __('Lesson not found. maybe the lesson is not published yet!', 'fluent-community')
            ]);
        }

        $enrollment = CourseHelper::getCourseEnrollment($course->id);

        if ($course->privacy == 'secret' && !$enrollment && !$isCourseCreator) {
            return $this->sendError([
                'message' => __('You must need to be invited to view this course', 'fluent-community')
            ]);
        }

        $section = $lesson->topic;
        $hasAcessSectionAcess = !!$enrollment;
        $unlockDate = '';
        $courseType = $course->getCourseType();
        $hasPublicViewAccess = $course->privacy == 'public' && Arr::get($course->settings, 'public_lesson_view') == 'yes' && $courseType == 'self_paced';
        if ($hasAcessSectionAcess) {
            $unlockDate = CourseHelper::getSectionAccessDate($section, $courseType, $enrollment);
            if (!$unlockDate || strtotime($unlockDate) > current_time('timestamp')) {
                $hasAcessSectionAcess = false;
            } else {
                $unlockDate = null;
            }
        }

        $canViewLesson = $hasAcessSectionAcess || $isCourseCreator || $hasPublicViewAccess;
        $canViewLesson = apply_filters('fluent_community/course/can_view_lesson', $canViewLesson, $lesson, $course, $this->getUser());

        $formattedLesson = CourseHelper::formatLessonData($course, $lesson, $user, [
            'can_view'      => $canViewLesson,
            'parse_content' => $canViewLesson,
            'is_locked'     => !$hasAcessSectionAcess && !$isCourseCreator,
            'unclock_date'  => $unlockDate
        ]);

        return [
            'lesson' => $formattedLesson
        ];
    }

    private function processCourse(Course $course, $isCourseCreator, $intendtedLessonSlug = '')
    {
        $sections = CourseTopic::where('space_id', $course->id)
            ->orderBy('priority', 'ASC')
            ->with(['lessons' => function ($query) use ($isCourseCreator) {
                if (!$isCourseCreator) {
                    $query->where('status', 'published');
                }
            }])
            ->get();

        $enrollment = CourseHelper::getCourseEnrollment($course->id);

        $currentUser = Helper::getCurrentUser();

        if ($course->privacy == 'private' && !$enrollment) {
            $course->lockscreen_config = LockscreenService::getLockscreenConfig($course, null, true);
        }

        if ($course->privacy == 'secret' && !$enrollment && !$isCourseCreator) {
            return $this->sendError([
                'message' => __('You must need to be invited to view this course', 'fluent-community')
            ]);
        }

        $courseSettings = $course->settings;
        if (Arr::get($courseSettings, 'course_details')) {
            $courseSettings['course_details_rendered'] = wp_kses_post(FeedsHelper::mdToHtml($courseSettings['course_details']));
            $course->settings = $courseSettings;
        }

        $courseType = $course->getCourseType();
        $hasPublicViewAccess = $course->privacy == 'public' && Arr::get($course->settings, 'public_lesson_view') == 'yes' && $courseType == 'self_paced';

        $formattedSections = [];

        foreach ($sections as $section) {
            if ($section->lessons->isEmpty()) {
                continue;
            }

            $hasAcessSectionAcess = !!$enrollment;
            $unlockDate = '';

            if ($hasAcessSectionAcess) {
                $unlockDate = CourseHelper::getSectionAccessDate($section, $courseType, $enrollment);
                if (!$unlockDate || strtotime($unlockDate) > current_time('timestamp')) {
                    $hasAcessSectionAcess = false;
                } else {
                    $unlockDate = null;
                }
            }

            $formattedLessons = [];
            foreach ($section->lessons as $lesson) {
                $canViewLesson = $hasAcessSectionAcess || $isCourseCreator || $hasPublicViewAccess;
                $canViewLesson = apply_filters('fluent_community/course/can_view_lesson', $canViewLesson, $lesson, $course, $this->getUser());

                $parseContent = $intendtedLessonSlug === $lesson->slug && $canViewLesson;

                $formattedLesson = CourseHelper::formatLessonData($course, $lesson, $currentUser, [
                    'can_view'      => $canViewLesson,
                    'parse_content' => $parseContent,
                    'is_locked'     => !$hasAcessSectionAcess && !$isCourseCreator,
                    'unclock_date'  => $unlockDate
                ]);

                $isLazyLoad = !$parseContent;
                if(!$canViewLesson) {
                    $isLazyLoad = false;
                }

                $formattedLesson['lazy_load'] = $isLazyLoad;

                $formattedLessons[] = $formattedLesson;
            }

            $formattedSections[] = [
                'id'          => $section->id,
                'title'       => $section->title,
                'lessons'     => $formattedLessons,
                'created_at'  => $section->created_at,
                'slug'        => $section->slug,
                'type'        => 'section',
                'is_locked'   => !$hasAcessSectionAcess && !$isCourseCreator,
                'unlock_date' => $unlockDate
            ];
        }

        return [
            'course'   => $course,
            'sections' => $formattedSections,
            'track'    => CourseHelper::getCourseProgressTrack($course->id)
        ];
    }

    public function enrollCourse(Request $request, $courseId)
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->sendError([
                'message' => __('You need to login to enroll in this course.', 'fluent-community')
            ]);
        }

        $isCourseCreator = $user->hasCourseCreatorAccess();
        $course = Course::when(!$isCourseCreator, function ($q) {
            $q->where('status', 'published');
        })->findOrFail($courseId);


        if (!$isCourseCreator && $course->privacy != 'public') {
            return $this->sendError([
                'message' => __('You are not allowed to enroll in this course.', 'fluent-community')
            ]);
        }

        $enrolled = CourseHelper::enrollCourse($course, get_current_user_id());

        if (!$enrolled) {
            return [
                'message' => __('You are already enrolled in this course.', 'fluent-community'),
                'track'   => CourseHelper::getCourseProgressTrack($courseId)
            ];
        }

        return [
            'message' => __('You have been enrolled in this course.', 'fluent-community'),
            'track'   => CourseHelper::getCourseProgressTrack($courseId)
        ];
    }

    public function updateCompletionLesson(Request $request, $courseId, $lessonId)
    {
        $course = Course::findOrFail($courseId);

        if ($course->status != 'published') {
            return $this->sendError([
                'message' => __('Course is not in published state.', 'fluent-community')
            ]);
        }


        if (!CourseHelper::isEnrolled($courseId)) {
            return $this->sendError([
                'message' => __('Please enroll this course first', 'fluent-community')
            ]);
        }

        $lesson = CourseLesson::where('space_id', $courseId)
            ->where('status', 'published')
            ->where('id', $lessonId)
            ->first();

        if (!$lesson) {
            return $this->sendError([
                'message' => __('Lesson is not on published state.', 'fluent-community')
            ]);
        }

        $state = $request->get('state');

        if (!in_array($state, ['completed', 'incomplete'])) {
            return $this->sendError([
                'message' => __('Invalid state.', 'fluent-community')
            ]);
        }

        $isAllowed = apply_filters('fluent_community/is_allowed_to_complete_lesson', true, $lesson);

        if (!$isAllowed) {
            return $this->sendError([
                'message' => __('You are not allowed to complete this lesson.', 'fluent-community')
            ]);
        }

        CourseHelper::updateLessonCompletion($lesson, get_current_user_id(), $state);
        $track = CourseHelper::getCourseProgressTrack($courseId);
        $isCompleted = $track['progress'] == 100;

        if ($isCompleted) {
            CourseHelper::completeCourse($course, $lesson, get_current_user_id());
        }

        return [
            'message'      => __('Lesson completion state updated.', 'fluent-community'),
            'track'        => $track,
            'is_completed' => $isCompleted
        ];
    }

}
