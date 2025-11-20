<?php

/**
 * @var $router FluentCommunity\Framework\Http\Router
 */

$router->prefix('courses')->namespace('\FluentCommunity\Modules\Course\Http\Controllers')->withPolicy(\FluentCommunity\App\Http\Policies\PortalPolicy::class)->group(function ($router) {
    $router->get('/', 'CourseController@getCourses');
    $router->get('/{course_id}', 'CourseController@getCourse')->int('course_id');
    $router->get('/{course_slug}/by-slug', 'CourseController@getCourseBySlug')->alphaNumDash('course_slug');
    $router->get('/{course_slug}/lessons/{lesson_slug}/by-slug', 'CourseController@getLessonBySlug')->alphaNumDash('course_slug')->alphaNumDash('lesson_slug');
    $router->post('/{course_id}/enroll', 'CourseController@enrollCourse')->int('course_id');
    $router->put('/{course_id}/lessons/{lesson_id}/completion', 'CourseController@updateCompletionLesson')->int('course_id')->int('lesson_id');
});

$router->prefix('admin/courses')->namespace('\FluentCommunity\Modules\Course\Http\Controllers')->withPolicy(\FluentCommunity\Modules\Course\Http\Policies\CourseAdminPolicy::class)->group(function ($router) {
    $router->get('/', 'CourseAdminController@getCourses');
    $router->post('/', 'CourseAdminController@createCourse');
    $router->get('/{course_id}', 'CourseAdminController@findCourse')->int('course_id');
    $router->put('/{course_id}', 'CourseAdminController@updateCourse')->int('course_id');
    $router->post('/{course_id}/duplicate', 'CourseAdminController@duplicateCourse')->int('course_id');
    $router->delete('/{course_id}', 'CourseAdminController@deleteCourse')->int('course_id');
    $router->get('/{course_id}/comments', 'CourseAdminController@getCourseComments')->int('course_id');
    $router->get('/{course_id}/students', 'CourseAdminController@getCourseStudents')->int('course_id');
    $router->post('/{course_id}/students', 'CourseAdminController@addStudent')->int('course_id');
    $router->delete('/{course_id}/students/{student_id}', 'CourseAdminController@removeStudent')->int('course_id')->int('student_id');

    $router->get('/{course_id}/users/search', 'CourseAdminController@getOtherUsers')->int('course_id');

    $router->post('/{course_id}/links', 'CourseAdminController@updateLinks')->int('course_id');

    $router->get('/{course_id}/meta-settings', 'CourseAdminController@getMetaSettings')->int('course_id');

    $router->get('/{course_id}/instructors/search', 'CourseAdminController@getOtherInstructors')->int('course_id');

    $router->get('/{course_id}/sections', 'CourseAdminController@getSections')->int('course_id');
    $router->post('/{course_id}/sections', 'CourseAdminController@createSection')->int('course_id');
    $router->patch('/{course_id}/sections/indexes', 'CourseAdminController@resetSectionIndexes')->int('course_id');
    $router->get('/{course_id}/sections/{section_id}', 'CourseAdminController@getSection')->int('course_id')->int('section_id');
    $router->put('/{course_id}/sections/{section_id}', 'CourseAdminController@updateSection')->int('course_id')->int('section_id');
    $router->patch('/{course_id}/sections/{section_id}', 'CourseAdminController@patchSection')->int('course_id')->int('section_id');
    $router->delete('/{course_id}/sections/{section_id}', 'CourseAdminController@deleteSection')->int('course_id')->int('section_id');
    $router->patch('/{course_id}/sections/{section_id}/indexes', 'CourseAdminController@resetLessonIndexes')->int('course_id')->int('section_id');

    $router->get('/{course_id}/lessons', 'CourseAdminController@getLessons')->int('course_id');
    $router->post('/{course_id}/lessons', 'CourseAdminController@createLesson')->int('course_id');
    $router->put('/{course_id}/copy-section', 'CourseAdminController@copySection')->int('course_id');
    $router->put('/{course_id}/move-lesson', 'CourseAdminController@moveLesson')->int('course_id');
    $router->get('/{course_id}/lessons/{lesson_id}', 'CourseAdminController@getLesson')->int('course_id')->int('lesson_id');
    $router->put('/{course_id}/lessons/{lesson_id}', 'CourseAdminController@updateLesson')->int('course_id')->int('lesson_id');
    $router->patch('/{course_id}/lessons/{lesson_id}', 'CourseAdminController@patchLesson')->int('course_id')->int('lesson_id');
    $router->delete('/{course_id}/lessons/{lesson_id}', 'CourseAdminController@deleteLesson')->int('course_id')->int('lesson_id');
});

