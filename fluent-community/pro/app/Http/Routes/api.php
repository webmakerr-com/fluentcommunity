<?php

/**
 * @var $router FluentCommunity\Framework\Http\Router
 */

use FluentCommunity\App\Services\Helper;

$router->prefix('admin')->withPolicy(\FluentCommunity\App\Http\Policies\AdminPolicy::class)->group(function ($router) {
    $router->get('/managers', 'ProAdminController@getManagers');
    $router->post('/managers', 'ProAdminController@addOrUpdateManager');
    $router->delete('/managers/{user_id}', 'ProAdminController@deleteManager')->int('user_id');
    $router->get('/users', 'ProAdminController@getUsers');

    $router->post('/auth-settings', 'ProAdminController@saveAuthSettings');

    $router->get('/license', 'LicenseController@getStatus');
    $router->post('/license', 'LicenseController@saveLicense');
    $router->delete('/license', 'LicenseController@deactivateLicense');

    $router->get('/messaging-setting', 'ProAdminController@getMessagingSettings');
    $router->post('/messaging-setting', 'ProAdminController@updateMessagingSettings');

});

$router->prefix('settings')->withPolicy(\FluentCommunity\App\Http\Policies\AdminPolicy::class)->group(function ($router) {
    $router->post('/color-config', [\FluentCommunityPro\App\Http\Controllers\ProAdminController::class, 'saveColorConfig']);
    $router->post('/crm-tagging-config', [\FluentCommunityPro\App\Http\Controllers\ProAdminController::class, 'saveCrmTaggingConfig']);
    $router->get('/snippets-settings', [\FluentCommunityPro\App\Http\Controllers\ProAdminController::class, 'getSnippetsSettings']);
    $router->post('/snippets-settings', [\FluentCommunityPro\App\Http\Controllers\ProAdminController::class, 'updateSnippetsSettings']);
    $router->post('/moderation-config', [\FluentCommunityPro\App\Http\Controllers\ModerationController::class, 'saveModerationConfig']);

    $router->get('/followers/config', [\FluentCommunityPro\App\Http\Controllers\ProAdminController::class, 'getFollowersSettings']);
    $router->post('/followers/config', [\FluentCommunityPro\App\Http\Controllers\ProAdminController::class, 'saveFollowersSettings']);

});

$router->prefix('admin')->withPolicy(\FluentCommunityPro\App\Http\Policies\TopicPolicy::class)->group(function ($router) {
    $router->get('/topics', [\FluentCommunityPro\App\Http\Controllers\ProAdminController::class, 'getTopics']);
    $router->post('/topics', [\FluentCommunityPro\App\Http\Controllers\ProAdminController::class, 'saveTopics']);
    $router->post('/topics/config', [\FluentCommunityPro\App\Http\Controllers\ProAdminController::class, 'updateTopicConfig']);
    $router->delete('/topics/{topic_id}', [\FluentCommunityPro\App\Http\Controllers\ProAdminController::class, 'deleteTopic'])->int('topic_id');

    $router->get('/webhooks', [\FluentCommunityPro\App\Http\Controllers\ProAdminController::class, 'getWebhooks']);
    $router->post('/webhooks', [\FluentCommunityPro\App\Http\Controllers\ProAdminController::class, 'saveWebhook']);
    $router->delete('/webhooks/{id}', [\FluentCommunityPro\App\Http\Controllers\ProAdminController::class, 'deleteWebhook'])->int('id');

    $router->post('/links', [\FluentCommunityPro\App\Http\Controllers\ProAdminController::class, 'saveSidebarLink']);
    $router->delete('/links/{id}', [\FluentCommunityPro\App\Http\Controllers\ProAdminController::class, 'deleteSidebarLink'])->int('id');
});

$router->prefix('spaces')->withPolicy(\FluentCommunity\App\Http\Policies\PortalPolicy::class)->group(function ($router) {
    $router->put('/{spaceSlug}/lockscreens', [\FluentCommunityPro\App\Http\Controllers\ProAdminController::class, 'updateSpaceLockscreenSettings'])->alphaNumDash('spaceSlug');
});

$router->prefix('admin/courses')->withPolicy(\FluentCommunity\Modules\Course\Http\Policies\CourseAdminPolicy::class)->group(function ($router) {
    $router->put('/{course_id}/lockscreens', [\FluentCommunityPro\App\Http\Controllers\ProAdminController::class, 'updateCourseLockscreenSettings'])->int('course_id');
});

$router->prefix('analytics')->withPolicy(\FluentCommunity\App\Http\Policies\AdminPolicy::class)->group(function ($router) {

    $router->prefix('overview')->group(function ($router) {
        $router->get('/widget', [\FluentCommunityPro\App\Http\Controllers\ReportsController::class, 'getOverviewWidget']);
        $router->get('/activity', [\FluentCommunityPro\App\Http\Controllers\ReportsController::class, 'activityReport']);
        $router->get('/popular-day-time', [\FluentCommunityPro\App\Http\Controllers\ReportsController::class, 'popularDayTimeReport']);
    });

    $router->prefix('members')->group(function ($router) {
        $router->get('/widget', [\FluentCommunityPro\App\Http\Controllers\MembersReportsController::class, 'widget']);
        $router->get('/activity', [\FluentCommunityPro\App\Http\Controllers\MembersReportsController::class, 'activity']);
        $router->get('/top-members', [\FluentCommunityPro\App\Http\Controllers\MembersReportsController::class, 'getTopMembers']);
        $router->get('/top-post-starters', [\FluentCommunityPro\App\Http\Controllers\MembersReportsController::class, 'topPostStarter']);
        $router->get('/top-commenters', [\FluentCommunityPro\App\Http\Controllers\MembersReportsController::class, 'topCommenters']);
    });

    $router->prefix('spaces')->group(function ($router) {
        $router->get('/widget', [\FluentCommunityPro\App\Http\Controllers\SpacesReportsController::class, 'widget']);
        $router->get('/activity', [\FluentCommunityPro\App\Http\Controllers\SpacesReportsController::class, 'activity']);
        $router->get('/popular', [\FluentCommunityPro\App\Http\Controllers\SpacesReportsController::class, 'getTopSpaces']);
        $router->get('/search', [\FluentCommunityPro\App\Http\Controllers\SpacesReportsController::class, 'searchSpace']);
    });
});

$router->prefix('moderation')->withPolicy(\FluentCommunity\App\Http\Policies\PortalPolicy::class)->group(function ($router) {
    $router->post('/report', [\FluentCommunityPro\App\Http\Controllers\ModerationController::class, 'create']);
});

$router->prefix('moderation')->withPolicy(\FluentCommunityPro\App\Http\Policies\ModerationPolicy::class)->group(function ($router) {
    if (Helper::isFeatureEnabled('content_moderation')) {
        $router->get('/reports', [\FluentCommunityPro\App\Http\Controllers\ModerationController::class, 'get']);
        $router->put('/reports/{report_id}', [\FluentCommunityPro\App\Http\Controllers\ModerationController::class, 'update'])->int('report_id');
        $router->delete('/reports/{report_id}', [\FluentCommunityPro\App\Http\Controllers\ModerationController::class, 'delete'])->int('report_id');
    }

    $router->post('/config', [\FluentCommunityPro\App\Http\Controllers\ModerationController::class, 'saveConfig']);
});

$router->prefix('scheduled-posts')->withPolicy(\FluentCommunity\App\Http\Policies\PortalPolicy::class)->group(function ($router) {
    $router->get('/', [\FluentCommunityPro\App\Http\Controllers\SchedulePostsController::class, 'getScheduledPosts']);
    $router->put('/{feed_id}', [\FluentCommunityPro\App\Http\Controllers\SchedulePostsController::class, 'reschedulePost'])->int('feed_id');
    $router->post('/publish/{feed_id}', [\FluentCommunityPro\App\Http\Controllers\SchedulePostsController::class, 'publishPost'])->int('feed_id');
});

if (Helper::isFeatureEnabled('followers_module')) {
    $router->prefix('profile')->withPolicy(\FluentCommunity\App\Http\Policies\PortalPolicy::class)->group(function ($router) {
        $router->get('/{username}/followers', [\FluentCommunityPro\App\Http\Controllers\FollowController::class, 'getFollowers'])->alphaNumDash('username');
        $router->get('/{username}/followings', [\FluentCommunityPro\App\Http\Controllers\FollowController::class, 'getFollowings'])->alphaNumDash('username');
        $router->get('/{username}/blocked-users', [\FluentCommunityPro\App\Http\Controllers\FollowController::class, 'getBlockedUsers'])->alphaNumDash('username');
        $router->post('/{username}/follow', [\FluentCommunityPro\App\Http\Controllers\FollowController::class, 'follow'])->alphaNumDash('username');
        $router->post('/{username}/unfollow', [\FluentCommunityPro\App\Http\Controllers\FollowController::class, 'unfollow'])->alphaNumDash('username');
        $router->post('/{username}/block', [\FluentCommunityPro\App\Http\Controllers\FollowController::class, 'block'])->alphaNumDash('username');
        $router->post('/{username}/unblock', [\FluentCommunityPro\App\Http\Controllers\FollowController::class, 'unblock'])->alphaNumDash('username');
        $router->post('/{username}/notification', [\FluentCommunityPro\App\Http\Controllers\FollowController::class, 'toggleNotification'])->alphaNumDash('username');
    });
}