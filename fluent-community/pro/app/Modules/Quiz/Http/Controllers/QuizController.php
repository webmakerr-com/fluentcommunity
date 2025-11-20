<?php

namespace FluentCommunityPro\App\Modules\Quiz\Http\Controllers;

use FluentCommunity\App\Http\Controllers\Controller;
use FluentCommunity\Framework\Http\Request\Request;
use FluentCommunity\Modules\Course\Model\Course;
use FluentCommunity\Modules\Course\Model\CourseLesson;
use FluentCommunityPro\App\Modules\Quiz\QuizModel;
use FluentCommunity\Framework\Support\Arr;

class QuizController extends Controller
{
    public function getQuizResult(Request $request, $courseId, $lessonId)
    {
        $user = $this->getUser(true);

        $quiz = CourseLesson::where('id', $lessonId)->whereHas('course', function ($query) use ($courseId) {
            $query->where('id', $courseId);
        })->firstOrFail();

        $quizResult = QuizModel::where('user_id', $user->ID)->where('post_id', $quiz->id)->first();

        if ($quizResult && Arr::isTrue($quiz->meta, 'hide_result')) {
            $quizResult->message = $quizResult->hideCorrectAnswers();
        }

        return [
            'result' => $quizResult
        ];
    }

    public function getCourseQuizResults(Request $request, $courseId)
    {
        Course::findOrFail($courseId);

        $search = $request->getSafe('search');

        $filterBy = $request->getSafe('filter_by');

        if ($filterBy == 'n/a') {
            $filterBy = 'published';
        }

        $allowedStatuses = ['passed', 'failed', 'published'];

        $results = QuizModel::where('parent_id', $courseId)
            ->with('xprofile', 'lesson')
            ->when($search, function ($query, $search) {
                $query->whereHas('xprofile', function ($q) use ($search) {
                    $q->where('display_name', 'LIKE', "%$search%")
                      ->orWhere('username', 'LIKE', "%$search%");
                })
                ->orWhereHas('lesson', function ($q) use ($search) {
                    $q->where('title', 'LIKE', "%$search%");
                });
            })
            ->when(in_array($filterBy, $allowedStatuses), function ($query) use ($filterBy) {
                return $query->where('status', $filterBy);
            })
            ->paginate();

        return [
            'results' => $results
        ];
    }

    public function submitQuiz(Request $request, $courseId, $lessonId)
    {        
        $user = $this->getUser(true);

        $answers = $request->get('answers', []);

        $quiz = CourseLesson::where('id', $lessonId)->whereHas('course', function ($query) use ($courseId) {
            $query->where('id', $courseId);
        })->firstOrFail();

        if ($quiz->status !== 'published') {
            return $this->sendError([
                'message' => __('This quiz is not published yet', 'fluent-community-pro'),
            ]);
        }

        $results = [];
        $correctAnswers = 0;
        foreach ($quiz->enabled_questions as $question) {
            $slug = Arr::get($question, 'slug');
            $userAnswer = isset($answers[$slug]) ? $answers[$slug] : null;
            if (!$userAnswer) {
                continue;
            }

            if (is_array($userAnswer)) {
                $userAnswer = array_map('sanitize_text_field', $userAnswer);
            } else {
                $userAnswer = sanitize_text_field($userAnswer);
            }

            $isCorrect = QuizModel::isCorrectAnswer($question, $userAnswer);

            if ($isCorrect) {
                $correctAnswers++;
            }

            $results[$slug] = [
                'is_correct'  => $isCorrect,
                'user_answer' => $userAnswer
            ];
        }

        $totalQuestions = count($quiz->enabled_questions);

        $score = round($correctAnswers / $totalQuestions * 100);

        $attemptedQuiz = QuizModel::where('user_id', $user->ID)->where('post_id', $quiz->id)->first();

        $meta = [
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctAnswers,
            'attempts'        => $attemptedQuiz ? $attemptedQuiz->meta['attempts'] + 1 : 1
        ];

        $quizData = [
            'user_id'   => $user->ID,
            'post_id'   => $quiz->id,
            'parent_id' => $courseId,
            'meta'      => $meta,
            'score'     => $score,
            'message'   => $results
        ];

        if ($quiz->passing_score) {
            $quizData['status'] = $score >= (int)$quiz->passing_score ? 'passed' : 'failed';
        } else {
            $quizData['status'] = 'published';
        }

        if ($attemptedQuiz) {
            $attemptedQuiz->fill($quizData);
            $attemptedQuiz->save();
            $quizResult = QuizModel::find($attemptedQuiz->id);
        } else {
            $quizResult = QuizModel::create($quizData);
            $quizResult = QuizModel::find($quizResult->id);
        }

        if ($quizResult && Arr::isTrue($quiz->meta, 'hide_result')) {
            $quizResult->message = $quizResult->hideCorrectAnswers();
        }

        do_action('fluent_community/quiz/submitted', $quizResult, $user, $quiz);

        return [
            'result'  => $quizResult,
            'message' => __('Quiz has been submitted successfully', 'fluent-community-pro')
        ];
    }

    public function updateQuizResult(Request $request, $courseId, $quizId)
    {
        $quiz = QuizModel::where('id', $quizId)->where('parent_id', $courseId)->firstOrFail();

        $updateData = [];

        $status = strtolower($request->getSafe('status'));

        if (in_array($status, ['passed', 'failed'])) {
            $updateData['status'] = $status;
        }

        $quiz->fill($updateData);
        $quiz->save();

        return [
            'result' => $quiz,
            'message' => __('Quiz result updated successfully', 'fluent-community-pro')
        ];
    }
}
