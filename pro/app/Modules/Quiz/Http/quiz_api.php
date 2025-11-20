<?php

/**
 * @var $router FluentCommunity\Framework\Http\Router
 */

$router->prefix('courses')->withPolicy(\FluentCommunity\App\Http\Policies\PortalPolicy::class)->group(function ($router) {
    $router->post('/{course_id}/lessons/{lesson_id}/quiz/submit', [\FluentCommunityPro\App\Modules\Quiz\Http\Controllers\QuizController::class, 'submitQuiz'])->int('course_id')->int('lesson_id');
    $router->get('/{course_id}/lessons/{lesson_id}/quiz/result', [\FluentCommunityPro\App\Modules\Quiz\Http\Controllers\QuizController::class, 'getQuizResult'])->int('course_id')->int('lesson_id');
});

$router->prefix('admin/courses')->withPolicy(\FluentCommunity\Modules\Course\Http\Policies\CourseAdminPolicy::class)->group(function ($router) {
    $router->get('/{course_id}/quiz-results', [\FluentCommunityPro\App\Modules\Quiz\Http\Controllers\QuizController::class, 'getCourseQuizResults'])->int('course_id');
    $router->post('/{course_id}/quiz-results/{quiz_id}', [\FluentCommunityPro\App\Modules\Quiz\Http\Controllers\QuizController::class, 'updateQuizResult'])->int('course_id')->int('quiz_id');
});