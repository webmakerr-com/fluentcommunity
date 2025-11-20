<?php

/**
 * @var $app FluentCommunity\Framework\Foundation\Application
 */

// Init the integrations
(new \FluentCommunityPro\App\Services\Integrations\Integrations())->register();

// Independent modules
(new \FluentCommunityPro\App\Hooks\Handlers\ShortCodeHandler())->register();

// Pro action
(new \FluentCommunityPro\App\Hooks\Handlers\ProActionHanders())->register();

// Access Management CRM
(new \FluentCommunityPro\App\Hooks\Handlers\AccessManagementCrmHandler())->register();

// Report Content
(new \FluentCommunityPro\App\Hooks\Handlers\ModerationHandler())->register();

// Schedule Post
(new \FluentCommunityPro\App\Hooks\Handlers\SchedulePostHandler())->register();

// Followers
(new \FluentCommunityPro\App\Hooks\Handlers\FollowHandler())->register();

// Course Email Notification
(new \FluentCommunityPro\App\Hooks\Handlers\CourseEmailNotificationHandler())->register();