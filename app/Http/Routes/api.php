<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * @var $router FluentCommunity\Framework\Http\Router
 */
$router->prefix('spaces')->withPolicy('SpacePolicy')->group(function ($router) {
    $router->get('/', 'SpaceController@get');
    $router->post('/', 'SpaceController@create');
    $router->get('/{spaceSlug}/by-slug', 'SpaceController@getBySlug')->alphaNumDash('spaceSlug');
    $router->put('/{spaceSlug}/by-slug', 'SpaceController@patchBySlug')->alphaNumDash('spaceSlug');
    $router->put('/{spaceId}/by-id', 'SpaceController@patchById')->int('spaceId');
    $router->post('/{spaceSlug}/join', 'SpaceController@join')->alphaNumDash('spaceSlug');
    $router->post('/{spaceSlug}/leave', 'SpaceController@leave')->alphaNumDash('spaceSlug');

    $router->get('/{spaceSlug}/meta-settings', 'SpaceController@getMetaSettings')->alphaNumDash('spaceSlug');

    $router->get('/{spaceSlug}/members', 'SpaceController@getMembers')->alphaNumDash('spaceSlug');
    $router->post('/{spaceSlug}/members', 'SpaceController@addMember')->alphaNumDash('spaceSlug');

    $router->delete('/{spaceSlug}', 'SpaceController@deleteBySlug')->alphaNumDash('spaceSlug');
    $router->delete('/{spaceId}/by-id', 'SpaceController@deleteById')->int('spaceId');
    $router->post('/{spaceSlug}/members/remove', 'SpaceController@removeMember')->alphaNumDash('spaceSlug');

    $router->get('/{spaceSlug}/lockscreens', 'SpaceController@getLockScreenSettings')->alphaNumDash('spaceSlug');

    $router->post('/{spaceSlug}/links', 'SpaceController@updateLinks')->alphaNumDash('spaceSlug');

    $router->get('/users/search', 'SpaceController@getOtherUsers');
    $router->get('/discover', 'SpaceController@discover');
    $router->get('/space_groups', 'SpaceController@getSpaceGroups');
    $router->post('/space_groups', 'SpaceController@createSpaceGroup');
    $router->put('/space_groups/{id}', 'SpaceController@updateSpaceGroup')->int('id');
    $router->delete('/space_groups/{id}', 'SpaceController@deleteSpaceGroup')->int('id');
    $router->patch('/space_groups/re-index', 'SpaceController@updateSpaceGroupIndexes');
    $router->patch('/space_groups/re-index-spaces', 'SpaceController@updateSpaceIndexes');
    $router->patch('/space_groups/move-space', 'SpaceController@moveSpace');
});

$router->prefix('feeds')->withPolicy('PortalPolicy')->group(function ($router) {
    $router->get('/', 'FeedsController@get');
    $router->post('/', 'FeedsController@store');
    $router->post('/{feed_id}', 'FeedsController@update')->int('feed_id');
    $router->patch('/{feed_id}', 'FeedsController@patchFeed')->int('feed_id');
    $router->post('/media-upload', 'FeedsController@handleMediaUpload');

    $router->get('bookmarks', 'FeedsController@getBookmarks');
    $router->get('/{feed_slug}/by-slug', 'FeedsController@getFeedBySlug')->alphaNumDash('feed_slug');
    $router->get('/{feed_id}/by-id', 'FeedsController@getFeedById')->int('feed_id');

    $router->get('/{feed_id}/comments', 'CommentsController@getComments')->int('feed_id');
    $router->post('/{feed_id}/comments', 'CommentsController@store')->int('feed_id');
    $router->post('/{feed_id}/comments/{comment_id}', 'CommentsController@update')->int('feed_id')->int('comment_id');
    $router->post('/{feed_id}/react', 'CommentsController@addOrRemovePostReact')->int('feed_id');
    $router->delete('/{feed_id}/comments/{comment_id}', 'CommentsController@deleteComment')->int('feed_id')->int(
        'comment_id'
    );

    $router->post('/{feed_id}/comments/{comment_id}/reactions', 'CommentsController@toggleReaction')->int(
        'feed_id'
    )->int('comment_id');

    $router->delete('/{feed_id}', 'FeedsController@deleteFeed')->int('feed_id');
    $router->delete('/{feed_id}/media-preview', 'FeedsController@deleteMediaPreview')->int('feed_id');

    $router->get('ticker', 'FeedsController@getTicker');

    $router->get('oembed', 'FeedsController@getOembed');

    $router->get('links', 'FeedsController@getLinks');
    $router->post('links', 'FeedsController@updateLinks');

    $router->get('welcome-banner', 'FeedsController@getWelcomeBanner');

    $router->get('/{feed_id}/reactions', 'ReactionController@getByFeedId')->int('feed_id');

    $router->post('/{feed_id}/apps/survey-vote', 'ReactionController@castSurveyVote')->int('feed_id');

    $router->get('/{feed_id}/apps/survey-voters/{option_slug}', 'ReactionController@getSurveyVoters')->int('feed_id')->alphaNumDash('option_slug');

    $router->post('/markdown-preview', 'FeedsController@markdownToHtml');
});

$router->prefix('profile')->withPolicy('PortalPolicy')->group(function ($router) {
    $router->get('/{username}', 'ProfileController@getProfile')->alphaNumDash('username');
    $router->post('/{username}', 'ProfileController@updateProfile')->alphaNumDash('username');
    $router->put('/{username}', 'ProfileController@patchProfile')->alphaNumDash('username');
    $router->get('/{username}/spaces', 'ProfileController@getSpaces')->alphaNumDash('username');
    $router->get('/{username}/comments', 'ProfileController@getComments')->alphaNumDash('username');

    $router->get('/{username}/notification-preferences', 'ProfileController@getNotificationPreferance')->alphaNumDash('username');
    $router->post('/{username}/notification-preferences', 'ProfileController@saveNotificationPreferance')->alphaNumDash('username');
});

$router->prefix('admin')->withPolicy('AdminPolicy')->group(function ($router) {
    $router->get('/general', 'AdminController@getGeneralSettings');
    $router->post('/general', 'AdminController@saveGeneralSettings');

    $router->get('/email-settings', 'AdminController@getEmailSettings');
    $router->post('/email-settings', 'AdminController@saveEmailSettings');

    $router->get('/storage-settings', 'AdminController@getStorageSettings');
    $router->post('/storage-settings', 'AdminController@updateStorageSettings');

    $router->get('/welcome-banner', 'AdminController@getWelcomeBannerSettings');
    $router->post('/welcome-banner', 'AdminController@updateWelcomeBannerSettings');

    $router->get('/auth-settings', 'AdminController@getAuthSettings');

    $router->get('/on-boardings', 'AdminController@getOnboardingSettings');
    $router->post('/on-boardings', 'AdminController@saveOnboardingSettings');
    $router->post('/on-boardings/change-slug', 'AdminController@changePortalSlug');

    $router->get('/profile-link-providers', 'AdminController@getProfileLinkProviders');
    $router->post('/profile-link-providers', 'AdminController@updateProfileLinkProviders');

    $router->get('/all_space_courses', 'AdminController@getAllSpaceCourses');

});

$router->prefix('members')->withPolicy('PortalPolicy')->group(function ($router) {
    $router->get('/', 'MembersController@getMembers');
    $router->patch('/{user_id}', 'MembersController@patchMember')->int('user_id');
});

$router->prefix('notifications')->withPolicy('PortalPolicy')->group(function ($router) {
    $router->get('/', 'NotificationsController@getNotifications');
    $router->get('/unread', 'NotificationsController@getUnreadNotifications');
    $router->post('/mark-read/{notification_id}', 'NotificationsController@markAsRead')->int('notification_id');
    $router->post('/mark-read/{feed_id}/by-feed-id', 'NotificationsController@markAsReadByFeedId')->int('feed_id');
    $router->post('/mark-all-read', 'NotificationsController@markAllRead');
});

$router->prefix('activities')->withPolicy('PortalPolicy')->group(function ($router) {
    $router->get('/', 'ActivityController@getActivities');
});

$router->prefix('comments')->withPolicy('PortalPolicy')->group(function ($router) {
    $router->get('/{comment_id}/reactions', 'ReactionController@getByCommentId')->int('comment_id');
    $router->get('/{id}', 'CommentsController@show')->int('id');
});

$router->prefix('options')->withPolicy('PortalPolicy')->group(function ($router) {
    $router->get('/app-vars', 'OptionController@getAppVars');
    $router->get('/sidebar-menu-html', 'OptionController@getSidebarMenuHtml');
    $router->get('/menu-items', 'OptionController@getMenuItems');
});

$router->prefix('settings')->withPolicy('AdminPolicy')->group(function ($router) {
    $router->get('features', 'SettingController@getFeatures');
    $router->post('features', 'SettingController@setFeatures');
    $router->get('menu-settings', 'SettingController@getMenuSettings');
    $router->post('menu-settings', 'SettingController@saveMenuSettings');
    $router->post('install_plugin', 'SettingController@installPlugin');
    $router->get('customization-settings', 'SettingController@getCustomizationSettings');
    $router->post('customization-settings', 'SettingController@updateCustomizationSettings');
    $router->get('privacy-settings', 'SettingController@getPrivacySettings');
    $router->post('privacy-settings', 'SettingController@updatePrivacySettings');
    $router->get('color-config', 'SettingController@getColorConfig');

    $router->get('crm-tagging-config', 'SettingController@getCrmTaggingConfig');

});
