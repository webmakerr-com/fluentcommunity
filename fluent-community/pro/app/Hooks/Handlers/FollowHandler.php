<?php

namespace FluentCommunityPro\App\Hooks\Handlers;

use FluentCommunityPro\App\Modules\Followers\FollowerHelper;
use FluentCommunityPro\App\Models\Follow;
use FluentCommunity\App\Services\FeedsHelper;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\App\Models\Feed;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\Notification;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Services\ProfileHelper;
use FluentCommunity\App\Services\Libs\Mailer;

class FollowHandler
{
    private $maxRunTime = 0;

    public function register()
    {
        // Handle Migrations
        add_action('fluent_community/after_sync_bp_users', [$this, 'handleBPMigrations'], 10, 1);

        if (!Helper::isFeatureEnabled('followers_module')) {
            return;
        }

        add_filter('fluent_community/profile_view_data', [$this, 'getFollowersCount'], 10, 2);
        add_filter('fluent_community/post_order_options', [$this, 'getPostOrderOptions'], 10, 2);
        add_action('fluent_community/feeds_query', [$this, 'maybeFollowMemberInFeeds'], 10, 3);

        add_filter('fluent_community/feed_api_response', [$this, 'addFollowInFeedsApiResponse'], 10, 1);
        add_filter('fluent_community/feeds_api_response', [$this, 'addFollowInFeedsApiResponse'], 10, 1);
        add_filter('fluent_community/reactions_api_response', [$this, 'addFollowInUserCollection'], 10, 2);

        add_filter('fluent_community/leaderboard_api_response', [$this, 'addFollowInUserCollection'], 10, 2);
        add_filter('fluent_community/space_members_api_response', [$this, 'addFollowInUserCollection'], 10, 2);
        add_filter('fluent_community/members_api_response', [$this, 'addFollowInUserCollection'], 10, 2);

        // App Notification for new follow
        add_action('fluent_community/followed_user', [$this, 'sendAppNotification'], 10, 1);
        add_action('fluent_community/blocked_user', [$this, 'maybeDeleteAppNotification'], 10, 1);
        add_action('fluent_community/before_unfollowing_user', [$this, 'maybeDeleteAppNotification'], 10, 1);

        // Emailing notification for new profile post to followers
        add_action('fluent_community/profile_feed/created', [$this, 'maybeSendNotification'], 10, 1);
        add_action('fluent_community/notify_profile_feed_new_post', [$this, 'sendPostNotification'], 10, 2);
        // email notification handler. We are adding this sub query to fetch users from followers of the feed author
        add_action('fluent_community/space_feed/email_notify_sub_query', function (&$query, $feed) {
            $query->orWhereHas('follows', function ($q) use ($feed) {
                $q->where('followed_id', $feed->user_id)
                    ->where('level', 2);
            });
        }, 10, 2);
    }

    public function getFollowersCount($profileData, $xprofile)
    {
        $currentUserId = get_current_user_id();
        if ($currentUserId) {
            $follow = Follow::where('followed_id', $xprofile->user_id)->where('follower_id', get_current_user_id())->first();
            if ($follow) {
                $profileData['follow'] = $follow->level;
            }
        }

        $canViewFollowers = FollowerHelper::canViewFollowers($xprofile->user_id);
        if ($canViewFollowers) {
            $profileData['followers_count'] = $xprofile->followers()->where('level', '>', 0)->count();
        }

        $canViewFollowings = FollowerHelper::canViewFollowings($xprofile->user_id);
        if ($canViewFollowings) {
            $profileData['followings_count'] = $xprofile->follows()->where('level', '>', 0)->count();
        }

        return $profileData;
    }

    public function getPostOrderOptions($options, $context)
    {
        if ($context !== 'feed' || !is_user_logged_in()) {
            return $options;
        }

        $options['following'] = __('Following', 'fluent-community-pro');

        return $options;
    }

    public function maybeFollowMemberInFeeds(&$query, $requestData, $args)
    {
        $userId = get_current_user_id();
        if (!$userId) {
            return $query;
        }

        if (empty($args['user_id']) && !Helper::isModerator()) {
            $hasBlockList = Follow::where('follower_id', $userId)
                ->where('level', 0)
                ->exists();

            if ($hasBlockList) {
                $query->whereDoesntHave('user', function ($q) use ($userId) {
                    $q->whereHas('followers', function ($q2) use ($userId) {
                        $q2->where('follower_id', $userId)
                            ->where('level', 0);
                    });
                });
            }
        }

        $followingType = Arr::get($requestData, 'order_by_type') == 'following';
        if (!$followingType) {
            return;
        }

        $query->byFollowing();
    }

    public function sendAppNotification($follow)
    {
        $followerProfile = XProfile::where('user_id', $follow->follower_id)->first();
        if (!$followerProfile) {
            return;
        }

        $followerName = '<b class="fcom_nudn">' . $followerProfile->display_name . '</b>';
        $notificationContent = sprintf(
            /* translators: %1$s is the follower name */
            __('%1$s followed you', 'fluent-community-pro'),
            $followerName
        );

        $notification = [
            'feed_id'         => $follow->id,
            'object_id'       => $follow->followed_id,
            'src_user_id'     => $follow->follower_id,
            'src_object_type' => 'follow',
            'action'          => 'follow_added',
            'content'         => $notificationContent,
            'route'           => $followerProfile->getJsRoute(),
        ];

        $notification = Notification::create($notification);

        $notification->subscribe([$follow->followed_id]);
    }

    public function maybeDeleteAppNotification($follow)
    {
        $notification = Notification::where('feed_id', $follow->id)
            ->where('object_id', $follow->followed_id)
            ->where('src_user_id', $follow->follower_id)
            ->where('src_object_type', 'follow')
            ->where('action', 'follow_added')
            ->first();

        if (!$notification) {
            return;
        }

        $notification->delete();
    }

    public function maybeSendNotification($feed)
    {
        $hasFollowers = Follow::where('followed_id', $feed->user_id)
            ->where('level', 2)
            ->exists();

        if (!$hasFollowers) {
            return;
        }

        as_schedule_single_action(time() + 120, 'fluent_community/notify_profile_feed_new_post', [
            $feed->id,
            0
        ], 'fluent-community');
    }

    public function sendPostNotification($feedId, $lastUserId = 0)
    {
        $this->maxRunTime = $this->maxRunTime ?: Utility::getMaxRunTime();

        $feed = Feed::find($feedId);
        if (!$feed || !$feed->user) {
            return;
        }

        $mentionedUserIds = Arr::get($feed->meta, 'mentioned_user_ids', []);

        $followerIds = Follow::query()
            ->where('followed_id', $feed->user_id)
            ->where('level', 2)
            ->when($mentionedUserIds, function ($q) use ($mentionedUserIds) {
                $q->whereNotIn('follower_id', $mentionedUserIds);
            })
            ->when($lastUserId, function ($q) use ($lastUserId) {
                $q->where('follower_id', '>', $lastUserId);
            })
            ->limit(100)
            ->orderBy('follower_id', 'ASC')
            ->pluck('follower_id')
            ->toArray();

        if (empty($followerIds)) {
            return;
        }

        $lastUserId = $this->sendNotification($feed, $followerIds);
        if (!$lastUserId) {
            return;
        }

        if (count($followerIds) < 100) {
            // we are done
            return;
        }

        return $this->sendProfilePostNotification($feedId, $lastUserId);
    }

    private function sendNotification($feed, $userIds = [])
    {
        $users = User::query()
            ->whereIn('ID', $userIds)
            ->orderBy('ID', 'ASC')
            ->get();

        $emailSubject = \sprintf(
        /* translators: for send email to all followers: %1$s is the feed title, %2$s is the author name and %3$s space name */
            __('%1$s - %2$s [%3$s]', 'fluent-community-pro'),
            $feed->getHumanExcerpt(30),
            $feed->user->display_name,
            $feed->space->title
        );

        $emailBody = $feed->getFeedHtml(true);
        $feedPermalink = $feed->getPermalink();

        $startTime = microtime(true);
        $maxSendPerSecond = 10;

        $lastUserId = 0;
        foreach ($users as $index => $user) {
            $lastUserId = $user->ID;

            $newEmailBody = str_replace([
                '##feed_permalink##',
                '##email_notification_url##'
            ], [
                ProfileHelper::signUserUrlWithAuthHash($feedPermalink, $user->ID),
                ProfileHelper::getSignedNotificationPrefUrl($user->ID)
            ], $emailBody);

            $mailer = new Mailer('', $emailSubject, $newEmailBody);
            $mailer->to($user->user_email, $user->display_name);
            $mailer->send();

            if (microtime(true) - FLUENT_COMMUNITY_START_TIME > $this->maxRunTime) {
                as_schedule_single_action(time(), 'fluent_community/notify_followers_new_post', [$feed->id, $lastUserId], 'fluent-community');
                return 0;
            }

            if (($index + 1) % $maxSendPerSecond == 0) {
                $timeTaken = microtime(true) - $startTime;
                if ($timeTaken < 1) {
                    usleep((int)(1000000 - ($timeTaken * 1000000)));
                }
                $startTime = microtime(true);
            }
        }

        return $lastUserId;
    }

    public function addFollowInFeedsApiResponse($data)
    {
        $currentUserId = get_current_user_id();
        if (!$currentUserId) {
            return $data;
        }

        $currentUserIds = FeedsHelper::getCurrentRelatedUserIds();

        $follows = FollowerHelper::getCurrentUserFollows($currentUserIds);

        if ($follows) {
            $data['current_user_follows'] = $follows;
        }

        return $data;
    }

    public function addFollowInUserCollection($data, $users)
    {
        $userIds = $users->pluck('user_id')->toArray();
        $follows = FollowerHelper::getCurrentUserFollows($userIds);

        if ($follows) {
            $data['current_user_follows'] = $follows;
        }

        return $data;
    }

    public function handleBPMigrations($users)
    {
        $features = Utility::getOption('fluent_community_features', []);
        if (Arr::get($features, 'followers_module') !== 'yes') {
            return;
        }

        $userIds = $users->pluck('ID')->toArray();

        $friendships = fluentCommunityApp('db')->table('bp_friends')
            ->where('is_confirmed', 1)
            ->where(function ($query) use ($userIds) {
                $query->whereIn('initiator_user_id', $userIds)
                    ->orWhereIn('friend_user_id', $userIds);
            })
            ->get();

        foreach ($friendships as $friendship) {
            $followerId = (int)$friendship->initiator_user_id;
            $followedId = (int)$friendship->friend_user_id;
            if ($followerId === $followedId) {
                continue;
            }

            $exists = Follow::where('follower_id', $followerId)
                ->where('followed_id', $followedId)
                ->exists();

            if ($exists) {
                continue;
            }

            Follow::create([
                'follower_id' => $followerId,
                'followed_id' => $followedId,
                'level'       => 1
            ]);
        }

        return;
    }
}
