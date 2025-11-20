<?php

namespace FluentCommunityPro\App\Modules\Quiz;

use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Support\Arr;

class QuizHelper
{
    public static function getQuestionTypes()
    {
        return apply_filters('fluent_community/question_types', [
            [
                'value' => 'single_choice',
                'label' => __('Single Choice', 'fluent-community-pro')
            ],
            [
                'value' => 'multiple_choice',
                'label' => __('Multiple Choice', 'fluent-community-pro')
            ]
        ]);
    }

    public static function getCurrentImage($currentQuestions, $slug)
    {
        foreach ($currentQuestions as $question) {
            if (Arr::get($question, 'slug') == $slug) {
                return Arr::get($question, 'image_url', null);
            }
        }

        return null;
    }

    public static function maybeGenerateQuestionSlug($quizQuestions, $question)
    {
        if (Arr::get($question, 'slug')) {
            return $question['slug'];
        }

        $type = trim(Arr::get($question, 'type', ''));
        $slug = sanitize_title($type);

        $matched = 0;
        foreach ($quizQuestions as $q) {
            if (strpos($q['slug'], $slug) !== false) {
                $matched++;
            }
        }

        if ($matched > 0) {
            $slug .= '_' . $matched;
        }

        return $slug;
    }

    public static function handleMediaUrl($url, $currentImage, $slug, $lessonId)
    {
        $deleteMediaUrls = [];

        if ($url) {
            $media = Helper::getMediaFromUrl($url);
            if (!$media || $media->is_active) {
                return $currentImage;
            }

            $url = $media->public_url;

            $media->update([
                'is_active'     => true,
                'user_id'       => get_current_user_id(),
                'feed_id'       => $lessonId,
                'sub_object_id' => $lessonId,
                'object_source' => 'quiz_question_' . $slug
            ]);
        }

        if ($currentImage) {
            $deleteMediaUrls[] = $currentImage;
        }

        do_action('fluent_community/remove_medias_by_url', $deleteMediaUrls, [
            'sub_object_id' => $lessonId,
        ]);

        return $url;
    }
}
