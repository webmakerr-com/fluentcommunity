<?php

namespace FluentCommunity\App\Hooks\Handlers;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\Comment;
use FluentCommunity\App\Models\Feed;
use FluentCommunity\App\Models\Notification;
use FluentCommunity\App\Models\BaseSpace;
use FluentCommunity\App\Models\SpaceUserPivot;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\App\Services\Libs\DailyDigest;
use FluentCommunity\App\Services\Libs\Mailer;
use FluentCommunity\App\Services\NotificationPref;
use FluentCommunity\App\Services\ProfileHelper;
use FluentCommunity\Framework\Support\Arr;

class EmailNotificationHandler
{
    private $maxRunTime = 0;

    public function register()
    {
        add_action('fluent_community/space_feed/created', [$this, 'handleSpaceFeedCreated'], 20, 1);
        add_action('fluent_community/email_notify_new_posts', [$this, 'notifyOnPostCreatedAsync'], 10, 1);

        add_action('fluent_community/comment_added', [$this, 'handleNewCommentEvent'], 30, 2);
        add_action('fluent_community/comment_added_async', [$this, 'handleNewCommentNotificationAsync'], 10, 2);

        /*
         * This is an async request
         */
        add_action('fluent_community/email_notify_users_everyone_tag', [$this, 'emailNotifyUsersForEveryoneTag'], 10, 2);

        add_action('fluent_community_send_daily_digest', [$this, 'maybeSendDailyDigest'], 10);
        add_action('fluent_community/space/join_requested', [$this, 'handleCommunityJoinRequest'], 10, 2);
    }

    public function handleSpaceFeedCreated($feed)
    {

        if (did_action('fluent_community/feed/scheduling_everyone_tag')) {
            return;
        }

        $space = $feed->space;
        if (!$space) {
            return false;
        }

        $types = ['np_by_member_mail'];
        $spaceRole = $feed->user->getSpaceRole($feed->space);
        if (!in_array($spaceRole, ['admin', 'moderator'])) {
            $types[] = 'np_by_admin_mail';
        }


        $hasSubscribers = User::query()->where(function ($query) use ($types, $space, $feed) {
            $query->whereHas('notificationSubscriptions', function ($query) use ($types, $space) {
                $query->whereIn('notification_type', $types)
                    ->where('object_id', $space->id)
                    ->where('is_read', 1);
            });

            do_action_ref_array('fluent_community/space_feed/email_notify_sub_query', [&$query, $feed, $space, $types]);

            return $query;
        })->exists();

        if ($hasSubscribers || Arr::get($feed->meta, 'mentioned_user_ids', [])) {
            // We are scheduling this after 2 minutes of the post publish for performance
            as_schedule_single_action(time() + 120, 'fluent_community/email_notify_new_posts', [
                $feed->id
            ], 'fluent-community');
        }
    }

    public function notifyOnPostCreatedAsync($feedId)
    {
        if (!$this->maxRunTime) {
            $this->maxRunTime = Utility::getMaxRunTime();
        }

        if (is_numeric($feedId)) {
            $feed = Feed::find($feedId);
        } else {
            $feed = $feedId;
        }

        if (!$feed || !$feed instanceof Feed) {
            return;
        }

        $space = $feed->space;
        if (!$space || !$feed->user) {
            return;
        }

        $types = ['np_by_member_mail'];
        $spaceRole = $feed->user->getSpaceRole($feed->space);
        if (in_array($spaceRole, ['admin', 'moderator'])) {
            $types[] = 'np_by_admin_mail';
        }

        $lastSendUserId = (int)$feed->getCustomMeta('_last_email_user_id', 0);
        $usersQuery = User::query()->where(function ($query) use ($types, $space, $feed) {
            $query->whereHas('notificationSubscriptions', function ($query) use ($types, $space) {
                $query->whereIn('notification_type', $types)
                    ->where('object_id', $space->id)
                    ->where('is_read', 1);
            });

            $mentionedUserIds = Arr::get($feed->meta, 'mentioned_user_ids', []);

            if ($mentionedUserIds) {
                $query->orWhereIn('ID', $mentionedUserIds);
            }

            do_action_ref_array('fluent_community/space_feed/email_notify_sub_query', [&$query, $feed, $space, $types]);

            return $query;
        })
            ->whereHas('space_pivot', function ($query) use ($space) {
                $query->where('space_id', $space->id)
                    ->where('status', 'active');
            })
            ->when($lastSendUserId, function ($q) use ($lastSendUserId) {
                $q->where('ID', '>', $lastSendUserId);
            })
            ->whereHas('xprofile', function ($query) {
                return $query->where('status', 'active');
            })
            ->orderBy('ID', 'ASC')
            ->limit(60);

        $users = $usersQuery->get();

        if ($users->isEmpty()) {
            return; // It's done
        }

        $emailSubject = \sprintf(
        /* translators: %1$s is the author name and %2$s is the post excerpt (max 30 chars) */
            __('New Post By %1$s: %2$s', 'fluent-community'),
            $feed->user->getDisplayName(),
            $feed->getHumanExcerpt(30)
        );

        $emailBody = $feed->getFeedHtml(true);

        /*
         * must need to replace these two strings
         * ##feed_permalink##
         * ##email_notification_url##
         */
        $feedLink = $feed->getPermalink();

        $startTime = microtime(true);
        $maxSendPerSecond = 10; // max 10 emails per second

        foreach ($users as $index => $user) {
            $feed->updateCustomMeta('_last_email_user_id', $user->ID);
            if ($user->ID == $feed->user_id) {
                continue;
            }

            $newEmailBody = str_replace([
                '##feed_permalink##',
                '##email_notification_url##'
            ], [
                ProfileHelper::signUserUrlWithAuthHash($feedLink, $user->ID),
                ProfileHelper::getSignedNotificationPrefUrl($user->ID)
            ], $emailBody);

            $notificationBagde = $this->getNotificationBadges($user->ID);
            if ($notificationBagde) {
                $newEmailBody = str_replace('<!--before_footer_section-->', $notificationBagde, $newEmailBody);
            }

            $hooksSections = apply_filters('fluent_community/new_feed_notification/email_sections', [
                'before_content' => '',
                'after_content'  => ''
            ], $user, $feed);

            if (!empty($hooksSections['before_content'])) {
                $newEmailBody = str_replace('<!--email_content_before-->', $hooksSections['before_content'], $newEmailBody);
            }

            if (!empty($hooksSections['after_content'])) {
                $newEmailBody = str_replace('<!--email_content_after-->', $hooksSections['after_content'], $newEmailBody);
            }

            $mailer = new Mailer('', $emailSubject, $newEmailBody);
            $mailer->to($user->user_email, $user->display_name);
            $mailer->send();

            if (microtime(true) - FLUENT_COMMUNITY_START_TIME > $this->maxRunTime) {
                // It's been 45 seconds, let's stop and schedule the next one and schedule a new one
                as_schedule_single_action(time(), 'fluent_community/email_notify_new_posts', [$feed->id], 'fluent-community');
                return true;
            }

            if (($index + 1) % $maxSendPerSecond == 0) {
                $timeTaken = microtime(true) - $startTime;
                if ($timeTaken < 1) {
                    usleep((int)(1000000 - ($timeTaken * 1000000)));
                }
                $startTime = microtime(true);
            }
        }

        return $this->notifyOnPostCreatedAsync($feed);
    }

    public function handleNewCommentEvent(Comment $comment, $feed)
    {
        // Check the comment mentioned users or not
        if (Arr::get($comment->meta, 'mentioned_user_ids', [])) {
            as_schedule_single_action(time(), 'fluent_community/comment_added_async', [$comment->id, 0], 'fluent-community');
            return;
        }

        if ($comment->parent_id) {
            $globalCommentStatus = $this->isEnabled('reply_my_com_mail');
        } else {
            $globalCommentStatus = $this->isEnabled('com_my_post_mail');
        }

        $notificationUserIds = [];
        if ($comment->user_id != $feed->user_id && NotificationPref::willGetCommentEmail($feed->user_id, $globalCommentStatus)) {
            as_schedule_single_action(time(), 'fluent_community/comment_added_async', [$comment->id, 0], 'fluent-community');
            return true;
        }

        if ($comment->parent_id) {
            $notificationUserIds = $comment->getCommentParentUserIds();
            $notificationUserIds = array_filter($notificationUserIds, function ($userId) use ($globalCommentStatus) {
                return NotificationPref::willGetCommentReplyEmail($userId, $globalCommentStatus);
            });
        }

        if (!$notificationUserIds) {
            return false;
        }

        as_schedule_single_action(time(), 'fluent_community/comment_added_async', [$comment->id, 0], 'fluent-community');
        return true;
    }

    /*
     * This is for the async notification for new comment
     */
    public function handleNewCommentNotificationAsync($commentId, $lastUserId = 0)
    {
        $comment = Comment::find($commentId);
        if (!$comment) {
            return true;
        }

        $feed = $comment->post;
        if (!$feed) {
            return;
        }

        if ($comment->parent_id) {
            $globalCommentStatus = $this->isEnabled('reply_my_com_mail');
        } else {
            $globalCommentStatus = $this->isEnabled('com_my_post_mail');
        }

        $notificationUserIds = $comment->getCommentParentUserIds($lastUserId);
        $notificationUserIds = array_filter($notificationUserIds, function ($userId) use ($globalCommentStatus) {
            return NotificationPref::willGetCommentReplyEmail($userId, $globalCommentStatus);
        });

        $notificationUserIds = array_diff($notificationUserIds, [$comment->user_id]);
        if ($comment->user_id != $feed->user_id && NotificationPref::willGetCommentEmail($feed->user_id, $globalCommentStatus)) {
            // Add at the first
            $notificationUserIds[] = $feed->user_id;
        }

        // the mentioned user ids
        if ($mentionedUserIds = Arr::get($comment->meta, 'mentioned_user_ids', [])) {
            foreach ($mentionedUserIds as $mentionedUserId) {
                if (NotificationPref::willGetMentionEmail($mentionedUserId, $this->isEnabled('mention_mail'))) {
                    $notificationUserIds[] = $mentionedUserId;
                }
            }
        }

        if (!$notificationUserIds) {
            return;
        }

        $notificationUserIds = array_unique($notificationUserIds);

        $users = User::query()->whereIn('ID', $notificationUserIds)
            ->whereHas('xprofile', function ($query) {
                return $query->where('status', 'active');
            })
            ->when($lastUserId, function ($q) use ($lastUserId) {
                $q->where('ID', '>', $lastUserId);
            })
            ->orderBy('ID', 'ASC')
            ->get();

        if ($users->isEmpty()) {
            return; // it's done
        }

        $emailBody = $comment->getCommentHtml(true);
        $emailSubject = $comment->getEmailSubject($feed);

        $feedPermalik = $feed->getPermalink() . '?comment_id=' . $comment->id;
        $usersCount = count($users);

        $startTime = microtime(true);
        $maxSendPerSecond = 10; // max 10 emails per second
        foreach ($users as $index => $user) {
            $lastUserId = $user->ID;
            if ($user->ID == $comment->user_id) {
                continue;
            }

            $newEmailBody = str_replace([
                '##feed_permalink##',
                '##email_notification_url##'
            ], [
                ProfileHelper::signUserUrlWithAuthHash($feedPermalik, $user->ID),
                ProfileHelper::getSignedNotificationPrefUrl($user->ID)
            ], $emailBody);

            $notificationBagde = $this->getNotificationBadges($user->ID);
            if ($notificationBagde) {
                $newEmailBody = str_replace('<!--before_footer_section-->', $notificationBagde, $newEmailBody);
            }

            $hooksSections = apply_filters('fluent_community/comment_notification/email_sections', [
                'before_content' => '',
                'after_content'  => ''
            ], $user, $comment);

            if (!empty($hooksSections['before_content'])) {
                $newEmailBody = str_replace('<!--email_content_before-->', $hooksSections['before_content'], $newEmailBody);
            }

            if (!empty($hooksSections['after_content'])) {
                $newEmailBody = str_replace('<!--email_content_after-->', $hooksSections['after_content'], $newEmailBody);
            }

            $mailer = new Mailer('', $emailSubject, $newEmailBody);
            $mailer->to($user->user_email, $user->display_name);
            $mailer->send();

            if (microtime(true) - FLUENT_COMMUNITY_START_TIME > 50 && $index < ($usersCount - 1)) {
                // It's been 45 seconds, let's stop and schedule the next one and schedule a new one
                as_schedule_single_action(time(), 'fluent_community/comment_added_async', [$comment->id, $lastUserId], 'fluent-community');
                return true;
            }

            if (($index + 1) % $maxSendPerSecond == 0) {
                $timeTaken = microtime(true) - $startTime;
                if ($timeTaken < 1) {
                    usleep((int)(1000000 - ($timeTaken * 1000000)));
                }
                $startTime = microtime(true);
            }
        }

        // It's done
        return true;
    }

    public function emailNotifyUsersForEveryoneTag($feedId, $lastSendUserId = 0)
    {
        if (!$this->maxRunTime) {
            $this->maxRunTime = Utility::getMaxRunTime();
        }

        // Let's try to send email to all users of this space for this post
        $feed = Feed::find($feedId);

        if (!$feed || !$feed->space) {
            return true;
        }

        if (!$feed->isEnabledForEveryoneTag()) {
            return true;
        }

        $notification = Notification::where('action', 'space_feed/created')
            ->where('feed_id', $feed->id)
            ->first();

        if (!$notification) {
            return true;
        }

        $users = User::whereDoesntHave('notificationSubscriptions', function ($query) {
            $query->where('notification_type', 'mention_mail')
                ->where('is_read', 0);
        })
            ->whereHas('space_pivot', function ($query) use ($feed) {
                $query->where('space_id', $feed->space_id)
                    ->where('status', 'active');
            })
            ->whereHas('xprofile', function ($query) {
                return $query->where('status', 'active');
            })
            ->orderBy('ID', 'ASC')
            ->when($lastSendUserId, function ($q) use ($lastSendUserId) {
                $q->where('ID', '>', $lastSendUserId);
            })
            ->limit(100)
            ->get();

        if ($users->isEmpty()) {
            return true; // it's done
        }

        $author = $feed->user;

        $emailSubject = \sprintf(
        /* translators: for admin post to send email all space members: %1$s is the feed title, %2$s is the author name and %3$s space name */
            __('%1$s - %2$s [%3$s]', 'fluent-community'),
            $feed->getHumanExcerpt(30),
            $author->display_name,
            $feed->space->title
        );

        $emailBody = $feed->getFeedHtml(true);
        $feedPermalink = $feed->getPermalink();

        $startTime = microtime(true);
        $maxSendPerSecond = 10; // max 10 emails per second

        foreach ($users as $index => $user) {
            $lastSendUserId = $user->ID;
            if ($user->ID == $author->ID) {
                continue;
            }

            $newEmailBody = str_replace([
                '##feed_permalink##',
                '##email_notification_url##'
            ], [
                ProfileHelper::signUserUrlWithAuthHash($feedPermalink, $user->ID),
                ProfileHelper::getSignedNotificationPrefUrl($user->ID)
            ], $emailBody);

            $notificationBagde = $this->getNotificationBadges($user->ID);
            if ($notificationBagde) {
                $newEmailBody = str_replace('<!--before_footer_section-->', $notificationBagde, $newEmailBody);
            }

            $hooksSections = apply_filters('fluent_community/new_feed_everybody_notification/email_sections', [
                'before_content' => '',
                'after_content'  => ''
            ], $user, $feed);

            if (!empty($hooksSections['before_content'])) {
                $newEmailBody = str_replace('<!--email_content_before-->', $hooksSections['before_content'], $newEmailBody);
            }

            if (!empty($hooksSections['after_content'])) {
                $newEmailBody = str_replace('<!--email_content_after-->', $hooksSections['after_content'], $newEmailBody);
            }

            $mailer = new Mailer('', $emailSubject, $newEmailBody);
            $mailer->to($user->user_email, $user->display_name);
            $mailer->send();

            if (microtime(true) - FLUENT_COMMUNITY_START_TIME > $this->maxRunTime) {
                // It's been 45 seconds, let's stop and schedule the next one and schedule a new one
                as_schedule_single_action(time(), 'fluent_community/email_notify_users_everyone_tag', [$feedId, $lastSendUserId], 'fluent-community');
                return true;
            }

            if (($index + 1) % $maxSendPerSecond == 0) {
                $timeTaken = microtime(true) - $startTime;
                if ($timeTaken < 1) {
                    usleep((int)(1000000 - ($timeTaken * 1000000)));
                }
                $startTime = microtime(true);
            }
        }

        sleep(1); // sleeping for 1 second
        return $this->emailNotifyUsersForEveryoneTag($feedId, $lastSendUserId);
    }

    public function maybeSendDailyDigest()
    {
        if (!$this->maxRunTime) {
            $this->maxRunTime = Utility::getMaxRunTime();
        }

        $settings = Utility::getEmailNotificationSettings();

        $digestEmailDay = Arr::get($settings, 'digest_mail_day');
        if (strtolower(gmdate('D', current_time('timestamp'))) != $digestEmailDay) {
            return false;
        }

        $lastSentDate = Utility::getOption('last_digest_sent_date');
        if ($lastSentDate && gmdate('Y-m-d', strtotime($lastSentDate)) == gmdate('Y-m-d', current_time('timestamp'))) {
            return false; // already completed
        }

        $globalEnabled = Arr::get($settings, 'digest_email_status') === 'yes';
        $lastSentUserId = Utility::getOption('last_digest_sent_user_id');

        if ($globalEnabled) {
            $users = User::whereDoesntHave('notification_records', function ($query) {
                $query->where('notification_type', 'digest_mail')
                    ->where('is_read', 0);
            })
                ->whereHas('xprofile', function ($query) {
                    $query->where('status', 'active');
                })
                ->when($lastSentUserId, function ($q) use ($lastSentUserId) {
                    $q->where('ID', '>', $lastSentUserId);
                })
                ->limit(100)
                ->orderBy('ID', 'ASC')
                ->get();
        } else {
            $users = User::whereHas('notification_records', function ($query) {
                $query->where('notification_type', 'digest_mail')
                    ->where('is_read', 1);
            })
                ->whereHas('xprofile', function ($query) {
                    $query->where('status', 'active');
                })
                ->when($lastSentUserId, function ($q) use ($lastSentUserId) {
                    $q->where('ID', '>', $lastSentUserId);
                })
                ->limit(100)
                ->orderBy('ID', 'ASC')
                ->get();
        }

        if ($users->isEmpty()) {
            // It's done
            Utility::updateOption('last_digest_sent_date', current_time('mysql'));
            Utility::updateOption('last_digest_sent_user_id', 0);
            return false;
        }

        $startAt = microtime(true);
        $maxSendPerSecond = 10;
        $sentCount = 0;

        foreach ($users as $user) {
            Utility::updateOption('last_digest_sent_user_id', $user->ID);
            $emailDigest = new DailyDigest($user);
            if ($emailDigest->send()) {
                $sentCount++;
            }

            if (microtime(true) - FLUENT_COMMUNITY_START_TIME > $this->maxRunTime) {
                // It's been 45 seconds, let's stop and schedule the next one and schedule a new one
                as_schedule_single_action(time(), 'fluent_community_send_daily_digest', [], 'fluent-community', false);
                return true;
            }

            if ($sentCount % $maxSendPerSecond === 0) {
                $timeTaken = microtime(true) - $startAt;
                if ($timeTaken < 1) {
                    usleep((int)(1000000 - ($timeTaken * 1000000)));
                }
                $startAt = microtime(true);
            }
        }

        return $this->maybeSendDailyDigest();
    }

    private function isEnabled($key)
    {
        $settings = Utility::getEmailNotificationSettings();

        return Arr::get($settings, $key) === 'yes';
    }

    public function handleCommunityJoinRequest(BaseSpace $space, $userId)
    {
        $xProfile = XProfile::where('user_id', $userId)->first();
        if (!$xProfile) {
            return;
        }

        $emailComposer = new \FluentCommunity\App\Services\Libs\EmailComposer();

        $emailComposer->addBlock('paragraph', __('Hi Space Leader,', 'fluent-community'));
        /* translators: %1$s is replaced by the name of the user who requested to join the space, %2$s is replaced by the title of the space */
        $emailComposer->addBlock('paragraph', sprintf(__('You have a new join request from %1$s to join %2$s.', 'fluent-community'), '<b>' . $xProfile->display_name . '</b>', '<b>' . $space->title . '</b>'));
        $emailComposer->addBlock('paragraph', __('Please review the request in the portal', 'fluent-community'));

        $emailComposer->addBlock('button', __('View Pending Join Requests', 'fluent-community'), [
            'link' => Helper::baseUrl('space/' . $space->slug . '/members?view_pending=yes')
        ]);

        $emailComposer->setDefaultLogo();

        /* translators: %s is replaced by the title of the space */
        $emailComposer->addFooterLine('paragraph', sprintf(__('You are getting this email because you are an admin/moderator at %s', 'fluent-community'), '<a style="text-decoration: underline !important;" href="' . $space->getPermalink() . '">' . $space->title . '</a>'));

        $emailBody = $emailComposer->getHtml();

        $emailSubject = \sprintf(
        /* translators: %1$s is replaced by the name of the user who requested to join the space, %2$s is replaced by the title of the space */
            __('%1$s requested to join %2$s', 'fluent-community'),
            $xProfile->display_name,
            $space->title
        );

        $moderatorsUserIds = SpaceUserPivot::where('space_id', $space->id)
            ->whereIn('role', ['moderator', 'admin'])
            ->pluck('user_id')
            ->toArray();

        if (!$moderatorsUserIds) {
            return false;
        }

        $mailer = new Mailer('', $emailSubject, $emailBody);
        if (count($moderatorsUserIds) == 1) {
            $modUserId = $moderatorsUserIds[0];

            $moderator = get_user_by('ID', $modUserId);

            if (!$moderator || !$moderator->user_email) {
                return;
            }

            $mailer->to($moderator->user_email, $moderator->display_name);
            return $mailer->send();
        }

        // send by BCC by 12 chunks
        $chunks = array_chunk($moderatorsUserIds, 12);

        foreach ($chunks as $chunk) {

            $mailer = new Mailer('', $emailSubject, $emailBody);

            $users = User::whereIn('ID', $chunk)->get();
            $first = null;
            foreach ($users as $user) {
                if (!$user || !$user->user_email) {
                    continue;
                }
                if (!$first) {
                    $mailer->to($user->user_email, $user->display_name);
                    $first = $user;
                    continue;
                }
                $mailer->addBCC($user->display_name . ' <' . $user->user_email . '>');
            }

            if ($first) {
                $mailer->send();
                sleep(1); // sleeping for 1 second
            }
        }

        return true;
    }

    private function getNotificationBadges($userId)
    {
        $unreadCount = Notification::byStatus('unread', $userId)->count();
        $unreadMessages = apply_filters('fluent_messaging/get_unread_message_count', 0, $userId);

        $html = '';
        if ($unreadCount) {
            $notificationUrl = ProfileHelper::signUserUrlWithAuthHash(Helper::baseUrl('notifications'), $userId);
            /* translators: %d is replaced by the number of unread notifications */
            $html = '<a style="text-decoration: none;" href="' . $notificationUrl . '">' . sprintf(__('🔔 %d Unread Notifications.', 'fluent-community'), $unreadCount) . '</a>';
            if ($unreadMessages) {
                $html .= '<span style="margin: 0 10px;"> | </span>';
            }
        }

        if ($unreadMessages) {
            $chatUrl = ProfileHelper::signUserUrlWithAuthHash(Helper::baseUrl('chat'), $userId);
            /* translators: %d is replaced by the number of unread messages */
            $html .= '<a style="text-decoration: none;" href="' . $chatUrl . '">' . sprintf(__('✉️ %d Unread Messages', 'fluent-community'), $unreadMessages) . '</a>';
        }

        return $html;
    }
}
