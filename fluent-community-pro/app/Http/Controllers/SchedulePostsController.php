<?php

namespace FluentCommunityPro\App\Http\Controllers;

use FluentCommunity\Framework\Http\Request\Request;
use FluentCommunity\App\Services\FeedsHelper;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\App\Models\Feed;

class SchedulePostsController extends Controller
{
    public function getScheduledPosts(Request $request)
    {
        $feedsQuery = Feed::where('status', 'scheduled')
            ->where('scheduled_at', '!=', null)
            ->orderBy('scheduled_at', 'asc')
            ->with([
                'space',
                'terms'  => function ($q) {
                    $q->select(['title', 'slug'])
                        ->where('taxonomy_name', 'post_topic');
                }
            ]);

        $userId = $request->getSafe('user_id', 'intval', '');
        $currentUserId = get_current_user_id();

        if ($userId !== $currentUserId && !Helper::isSiteAdmin($currentUserId)) {
            return $this->sendError([
                'message' => __('You do not have permission to perform this action', 'fluent-community-pro'),
            ]);
        }

        $feedsQuery = $feedsQuery->where('user_id', $userId);

        $feeds = $feedsQuery->paginate();

        $feeds->getCollection()->each(function ($feed) {
            FeedsHelper::transformFeed($feed);
        });

        return [
            'feeds' => $feeds
        ];
    }

    public function publishPost(Request $request, $feedId)
    {
        $feed = Feed::findOrFail($feedId);
        $user = $this->getUser(true);

        $isAuthor = $feed->user_id == $user->ID;
        $hasModerator = $user->hasPermissionOrInCurrentSpace('community_moderator', $feed->space);

        if (!$hasModerator && !$isAuthor) {
            return $this->sendError([
                'message' => __('You do not have permission to perform this action', 'fluent-community-pro')
            ]);
        }

        if ($feed->status !== 'scheduled') {
            return $this->sendError([
                'message' => __('This is not scheduled post', 'fluent-community-pro'),
            ]);
        }

        \as_unschedule_all_actions('fluent_community/feed/scheduled_publish', [$feed->id], 'fluent-community');

        $feed->scheduled_at = null;
        $feed->status = 'published';
        $feed->created_at = current_datetime()->format('Y-m-d H:i:s');
        $feed->save();

        do_action('fluent_community/feed/created', $feed);

        if ($feed->space_id) {
            do_action('fluent_community/space_feed/created', $feed);
        }

        return [
            'feed'    => $feed,
            'message' => __('Post has been published', 'fluent-community-pro')
        ];
    }

    public function reschedulePost(Request $request, $feedId)
    {
        $feed = Feed::findOrFail($feedId);
        $user = $this->getUser(true);

        $isAuthor = $feed->user_id == $user->ID;
        $hasModerator = $user->hasPermissionOrInCurrentSpace('community_moderator', $feed->space);

        if (!$hasModerator && !$isAuthor) {
            return $this->sendError([
                'message' => __('You do not have permission to perform this action', 'fluent-community-pro')
            ]);
        }
        
        if ($feed->status !== 'scheduled') {
            return $this->sendError([
                'message' => __('This is not scheduled post', 'fluent-community-pro'),
            ]);
        }

        $scheduledAt = $request->getSafe('scheduled_at', 'sanitize_text_field', '');
        if (!$scheduledAt) {
            return $this->sendError([
                'message' => __('Scheduled time is required', 'fluent-community-pro'),
            ]);
        }

        $scheduledAt = new \DateTime($scheduledAt, wp_timezone());

        if (($scheduledAt->getTimestamp() - current_datetime()->getTimestamp()) <= (60 * 30)) {
            return $this->sendError([
                'message' => __('Scheduled time must be at least 30 minutes from now.', 'fluent-community-pro'),
            ]);
        }

        $formattedScheduledAt = $scheduledAt->format('Y-m-d H:i:s');
        $scheduleUtcTime = $scheduledAt->setTimezone(new \DateTimeZone('UTC'));
        $feed->scheduled_at = $formattedScheduledAt;
        $feed->save();

        \as_unschedule_all_actions('fluent_community/feed/scheduled_publish', [$feed->id], 'fluent-community');
        \as_schedule_single_action($scheduleUtcTime->getTimestamp(), 'fluent_community/feed/scheduled_publish', [$feed->id], 'fluent-community');

        do_action('fluent_community/feed/rescheduled', $feed);

        // translators: %s is the scheduled time
        $message = sprintf(__('Post has been rescheduled at %s', 'fluent-community-pro'), $formattedScheduledAt);

        return [
            'feed'    => $feed,
            'message' => $message
        ];
    }
}
