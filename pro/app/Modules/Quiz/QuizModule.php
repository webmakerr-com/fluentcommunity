<?php

namespace FluentCommunityPro\App\Modules\Quiz;

use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\App\Services\FeedsHelper;

class QuizModule
{
    public function register($app)
    {
        $app->router->group(function ($router) {
            require_once __DIR__ . '/Http/quiz_api.php';
        });

        add_filter('fluent_community/portal_vars', function ($vars) {
            $vars['question_types'] = QuizHelper::getQuestionTypes();
            return $vars;
        });

        add_filter('fluent_community/lesson/create_data', [$this, 'handleLessonCreateData'], 10, 2);

        add_filter('fluent_community/lesson/update_data', [$this, 'handleLessonUpdateData'], 10, 2);

        add_filter('fluent_community/lesson/get_public_meta', [$this, 'handleLessonPublicMeta'], 10, 2);

        add_filter('fluent_community/lesson/sanitize_meta', [$this, 'handleLessonSanitizeMeta'], 10, 2);

        add_filter('fluent_community/course_info', [$this, 'maybeAddQuizRoute'], 10, 1);

        add_filter('fluent_community/is_allowed_to_complete_lesson', [$this, 'isAllowedToCompleteLesson'], 10, 2);
    }

    public function handleLessonCreateData($data, $request)
    {
        if ($request->getSafe('type') == 'quiz') {
            $data['content_type'] = 'quiz';
        }

        return $data;
    }

    public function handleLessonUpdateData($data, $lesson)
    {
        if (!$lesson->isQuizType() || empty($data['message'])) {
            return $data;
        }

        $data['message_rendered'] = wp_kses_post(FeedsHelper::mdToHtml($data['message']));

        return $data;
    }

    public function handleLessonPublicMeta($meta, $lesson)
    {
        if (!$lesson->isQuizType() || empty($meta['quiz_questions'])) {
            return $meta;
        }

        $filteredQuestions = [];
        foreach ($meta['quiz_questions'] as $question) {
            if (!Arr::isTrue($question, 'enabled')) {
                continue;
            }
            if (!empty($question['options']) && is_array($question['options'])) {
                foreach ($question['options'] as &$option) {
                    if (isset($option['is_correct'])) {
                        unset($option['is_correct']);
                    }
                }
                unset($option);
            }
            $filteredQuestions[] = $question;
        }
        $meta['quiz_questions'] = $filteredQuestions;

        return $meta;
    }

    public function handleLessonSanitizeMeta($meta, $lesson)
    {
        if (!$lesson->isQuizType()) {
            return $meta;
        }

        $sanitizedQuestions = [];

        $quizQuestions = Arr::get($meta, 'quiz_questions', []);

        $currentQuestions = Arr::get($lesson->meta, 'quiz_questions', []);

        $textFields = ['slug', 'help_text', 'type'];
        $booleanFields = ['enabled', 'image_enabled'];

        foreach ($quizQuestions as $question) {
            $question['slug'] = QuizHelper::maybeGenerateQuestionSlug($quizQuestions, $question);

            $textValues = array_map('sanitize_text_field', Arr::only($question, $textFields));

            $booleanValues = array_map(function ($value) {
                return $value === true || $value === 'true' || $value == 1;
            }, Arr::only($question, $booleanFields));

            $sanitizedQuestion = array_merge($textValues, $booleanValues);

            $sanitizedQuestion['label'] = wp_kses_post(Arr::get($question, 'label'));

            $sanitizedQuestion['label_rendered'] = wp_kses_post(FeedsHelper::mdToHtml($sanitizedQuestion['label']));

            $sanitizedQuestion['options'] = array_map(function ($option) {
                $option['label'] = sanitize_text_field(Arr::get($option, 'label'));
                $option['is_correct'] = Arr::isTrue($option, 'is_correct');
                return $option;
            }, Arr::get($question, 'options', []));

            if (Arr::get($question, 'image_url')) {
                $currentImage = QuizHelper::getCurrentImage($currentQuestions, $question['slug']);
                $sanitizedQuestion['image_url'] = QuizHelper::handleMediaUrl($question['image_url'], $currentImage, $question['slug'], $lesson->id);
            }

            $sanitizedQuestions[] = $sanitizedQuestion;
        }

        $meta['quiz_questions'] = $sanitizedQuestions;

        return $meta;
    }

    public function maybeAddQuizRoute($course)
    {
        $quizCount = QuizModel::where('parent_id', $course->id)->count();

        if ($quizCount) {
            $course->quiz_count = $quizCount;
        }

        return $course;
    }

    public function isAllowedToCompleteLesson($isAllowed, $lesson)
    {
        if (!$lesson->isQuizType() || !$lesson->passing_score || !$lesson->is_enforce_pass) {
            return $isAllowed;
        }

        $quizResult = QuizModel::where('post_id', $lesson->id)->where('user_id', get_current_user_id())->first();

        $quizResult = $quizResult ? $quizResult->toArray() : [];

        return Arr::get($quizResult, 'score', 0) >= $lesson->passing_score;
    }
}
