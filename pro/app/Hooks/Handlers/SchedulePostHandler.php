<?php

namespace FluentCommunityPro\App\Hooks\Handlers;

use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\App\Models\Feed;

class SchedulePostHandler
{
    public function register()
    {
        add_filter('fluent_community/feed/new_feed_data', [$this, 'maybeSchedulePost'], 10, 2);
        add_action('fluent_community/feed/before_deleted', [$this, 'unschedulePost'], 10, 1);
        add_filter('fluent_community/profile_view_data', [$this, 'getScheduledPost'], 10, 2);
        add_action('fluent_community/feed/scheduled_publish', [$this, 'publishScheduledPost'], 10, 1);
    }

    public function maybeSchedulePost($data, $requestData)
    {
        if (!Arr::isTrue($requestData, 'is_admin', false)) {
            return $data;
        }

        $scheduledAt = sanitize_text_field(Arr::get($requestData, 'scheduled_at', null));
        if (!$scheduledAt) {
            return $data;
        }

        $scheduleTime = new \DateTime($scheduledAt, wp_timezone());

        if (($scheduleTime->getTimestamp() - current_datetime()->getTimestamp()) <= (60 * 30)) {
            return new \WP_Error(
                'schedule_time_too_soon', __('Scheduled time must be at least 30 minutes from now.', 'fluent-community-pro'),
                ['status' => 422]
            );
        }

        $data['status'] = 'scheduled';
        $data['scheduled_at'] = $scheduleTime->format('Y-m-d H:i:s');

        add_action('fluent_community/feed/scheduled', function ($feed) use ($scheduleTime) {
            $scheduleUtcTime = $scheduleTime->setTimezone(new \DateTimeZone('UTC'));
            \as_schedule_single_action($scheduleUtcTime->getTimestamp(), 'fluent_community/feed/scheduled_publish', [$feed->id], 'fluent-community');
        }, 10, 1);

        return $data;
    }

    public function getScheduledPost($profileData, $xprofile)
    {
        $currentUserId = get_current_user_id();

        if(!$currentUserId) {
            return $profileData;
        }

        $isOwn = $xprofile->user_id == $currentUserId;
        $isAdmin = Helper::isSiteAdmin($currentUserId);

        if (!$isOwn && !$isAdmin) {
            return $profileData;
        }

        $scheduledPostsCount = $xprofile->scheduledPosts()->count();

        if ($scheduledPostsCount < 1) {
            return $profileData;
        }

        $profileData['scheduled_posts_count'] = $scheduledPostsCount;

        return $profileData;
    }

    public function unschedulePost($feed)
    {
        \as_unschedule_all_actions('fluent_community/feed/scheduled_publish', [$feed->id], 'fluent-community');
    }

    public function publishScheduledPost($feedId)
    {
        $feed = Feed::find($feedId);
        if (!$feed || $feed->status !== 'scheduled') {
            return;
        }

        $feed->status = 'published';
        $feed->created_at = current_datetime()->format('Y-m-d H:i:s');
        $feed->save();

        do_action('fluent_community/feed/created', $feed);

        if ($feed->space_id) {
            do_action('fluent_community/space_feed/created', $feed);
        }
    }
}
