<?php

namespace FluentCommunityPro\App\Hooks\Handlers;

use FluentCommunity\App\Models\Notification;
use FluentCommunity\App\Models\NotificationSubscriber;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Models\Comment;
use FluentCommunity\App\Models\Feed;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\App\Services\ProfileHelper;
use FluentCommunity\App\Services\Libs\Mailer;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunityPro\App\Models\Moderation;

class ModerationHandler
{
    public function register()
    {
        if (!Helper::isFeatureEnabled('content_moderation')) {
            return;
        }

        add_action('fluent_community/content_moderation/created', [$this, 'handleNewReportEvent'], 10, 1);
        add_action('fluent_community/content_moderation/created', [$this, 'handleNewReportEmail'], 10, 1);
        add_action('fluent_community/content_moderation/created', [$this, 'maybeFlagContent'], 10, 2);

        add_filter('fluent_community/feed/new_feed_data', [$this, 'maybeFlagFeed'], 10, 2);
        add_filter('fluent_community/comment/comment_data', [$this, 'maybeFlagComment'], 10, 2);

        add_filter('fluent_community/feed/update_feed_data', [$this, 'maybeFlagUpdatedFeed'], 10, 2);
        add_filter('fluent_community/comment/update_comment_data', [$this, 'maybeFlagUpdatedComment'], 10, 4);

        add_action('fluent_community/feed/deleted', [$this, 'deleteFeedReport'], 10, 1);
        add_action('fluent_community/comment_deleted', [$this, 'deleteCommentReport'], 10, 1);
        add_action('fluent_community/comment_report_added_async', [$this, 'handleCommentReportEmailAsync'], 10, 2);
        add_action('fluent_community/post_report_added_async', [$this, 'handlePostReportEmailAsync'], 10, 2);
    }

    public function maybeFlagFeed($data, $requestData)
    {
        if (Arr::isTrue($requestData, 'is_admin', false)) {
             return $data;
        }

        $flagged = $this->isContentFlagged($data);

        if (!$flagged) {
            return $data;
        }

        $data['status'] = 'pending';
        $data['meta']['reports_count'] = 1;
        $data['meta']['auto_flagged'] = 'yes';
        $data['meta']['prevent_published'] = 'yes';

        add_action('fluent_community/feed/new_feed_pending', function ($feed) use ($flagged) {
            $reportData = [
                'post_id'       => $feed->id,
                'content_type'  => 'post',
                'user_id'       => null,
                'parent_id'     => null,
                'reports_count' => 1,
                'reason'        => $flagged['reason'],
                'status'        => 'flagged',
                'explanation'   => $flagged['explanation'],
                'meta'          => [
                    'flagged_by' => 'auto'
                ]
            ];

            $report = Moderation::create($reportData);
            do_action('fluent_community/content_moderation/created', $report, $feed);
        }, 10, 1);

        return $data;
    }

    public function maybeFlagComment($data)
    {
        if (Arr::isTrue($data, 'is_admin', false)) {
            return $data;
        }

        $moderationConfig = Helper::getModerationConfig();

        $content = Arr::get($data, 'message');
        $profiency = Arr::get($moderationConfig, 'profanity_filter', '');

        $flaggedWord = Helper::isProfanity($profiency, $content);

        if (!$flaggedWord) {
            return $data;
        }

        $data['status'] = 'pending';
        $data['meta']['reports_count'] = 1;
        $data['meta']['prevent_published'] = 'yes';

        add_action('fluent_community/comment/new_comment_pending', function ($comment, $feed) use ($flaggedWord) {
            $reportData = [
                'post_id'       => $feed->id,
                'content_type'  => 'comment',
                'user_id'       => null,
                'parent_id'     => $comment->id,
                'reports_count' => 1,
                'reason'        => 'profanity',
                'status'        => 'flagged',
                'explanation'   => 'Word: ' . $flaggedWord,
                'meta'          => [
                    'flagged_by' => 'auto'
                ]
            ];

            $report = Moderation::create($reportData);
            do_action('fluent_community/content_moderation/created', $report, $comment);

        }, 10, 2);

        return $data;
    }

    protected function isContentFlagged($data)
    {
        $moderationConfig = Helper::getModerationConfig();

        $profiency = Arr::get($moderationConfig, 'profanity_filter', '');
        $flagAllPosts = Arr::get($moderationConfig, 'flag_all_new_posts') == 'yes';
        $flagPostSpaces = Arr::get($moderationConfig, 'flag_all_new_posts_spaces', []);
        $firstPostApproval = Arr::get($moderationConfig, 'first_post_approval', 'no') == 'yes';
        $spaceId = Arr::get($data, 'space_id');

        $reason = '';
        $explanation = '';
        $content = Arr::get($data, 'title') . ' ' . Arr::get($data, 'message');

        if ($flagAllPosts) {
            $reason = 'flag_all_new_posts';
            $explanation = __('Global moderation settings require review', 'fluent-community-pro');
        } else if ($flagPostSpaces && $spaceId && in_array($spaceId, $flagPostSpaces)) {
            $reason = 'flag_all_new_posts_spaces';
            $explanation = __('Space moderation settings require review', 'fluent-community-pro');
        } else if ($firstPostApproval && !Feed::where('user_id', $data['user_id'])->where('status', 'published')->exists()) {
            $reason = 'first_post_approval';
            $explanation = __('First post requires approval', 'fluent-community-pro');
        } else if ($flaggedWord = Helper::isProfanity($profiency, $content)) {
            $reason = 'profanity';
            $explanation = 'Word: ' . $flaggedWord;
        }

        if (!$reason) {
            return false;
        }

        return [
            'reason'      => $reason,
            'explanation' => $explanation
        ];
    }

    public function deleteFeedReport($postId)
    {
        Moderation::where('post_id', $postId)
            ->where('content_type', 'post')
            ->delete();
    }

    public function deleteCommentReport($commentId)
    {
        Moderation::where('parent_id', $commentId)
            ->where('content_type', 'comment')
            ->delete();
    }

    public function maybeFlagContent($report, $content)
    {
        if (!$report || !$report->user_id || !$content) {
            return;
        }

        $moderationConfig = Helper::getModerationConfig();

        $threshold = Arr::get($moderationConfig, 'flag_after_threshold', 0);

        if ($threshold <= 0 || Arr::get($moderationConfig, 'is_enabled', 'no') != 'yes') {
            return;
        }

        if ($content->status != 'published' && $report->reports_count < $threshold) {
            return;
        }

        $content->status = 'pending';
        $content->save();

        $report->status = 'flagged';
        $report->save();

        if ($report->content_type == 'comment' && $post = Feed::find($report->post_id)) {
            $post->comments_count--;
            $post->save();
        }

        do_action('fluent_community/content_flagged', $report, $content);
    }

    public function handleNewReportEvent($report)
    {
        $post = $report->post;
        if (!$post) {
            return;
        }

        $userIds = $this->getNotificationUserIds($post);

        if ($report->content_type == 'comment') {
            return $this->handleCommentReport($report, $userIds);
        }

        return $this->handlePostReport($report, $userIds);
    }

    public function handleNewReportEmail($report)
    {
        $contentType = $report->content_type;

        as_schedule_single_action(
            time(),
            'fluent_community/' . $contentType . '_report_added_async',
            [$report->id, 0],
            'fluent-community'
        );
    }

    protected function handleCommentReport($report, $userIds)
    {
        $post = $report->post;
        $commentTitle = $report->comment->getHumanExcerpt(60);

        $exist = Notification::where('feed_id', $report->comment->id)
            ->where('action', 'comment_report_added')
            ->first();

        $notificationContent = \sprintf(
        /* translators: %1$s is the comment title*/
            __('A comment has been automatically flagged for review: %1$s', 'fluent-community-pro'),
            '<span class="fcom_nft">' . $commentTitle . '</span>'
        );

        if ($report->user_id) {
            $totalUsers = Moderation::where('post_id', $post->id)
                ->where('content_type', 'comment')
                ->where('parent_id', $report->comment->id)
                ->where('user_id', '!=', $report->user_id)
                ->distinct('user_id')
                ->count('user_id');

            $reporter = '<b class="fcom_nudn">' . $report->reporter->display_name . '</b>';

            if ($totalUsers) {
                $reporter .= sprintf(
                /* translators: %s is the number of other people */
                    __(' and %s other people', 'fluent-community-pro'),
                    '<b class="fcom_nrc">' . $totalUsers . '</b>'
                );
            }

            $notificationContent = \sprintf(
            /* translators: %1$s is the user name, %2$s is the comment title*/
                __('%1$s flagged a comment for your review: %2$s', 'fluent-community-pro'),
                $reporter,
                '<span class="fcom_nft">' . $commentTitle . '</span>'
            );
        }

        if ($exist) {
            $exist->content = $notificationContent;
            $exist->updated_at = current_time('mysql');
            $exist->src_user_id = $report->user_id;
            $exist->object_id = $report->id;
            $exist->save();

            NotificationSubscriber::where('object_id', $exist->id)
                ->update([
                    'is_read'    => 0,
                    'updated_at' => current_time('mysql')
                ]);
            return;
        }

        $notification = [
            'feed_id'         => $report->comment->id,
            'object_id'       => $report->id,
            'src_user_id'     => $report->user_id,
            'src_object_type' => 'report',
            'action'          => 'comment_report_added',
            'content'         => $notificationContent,
            'route'           => $post->getJsRoute(),
        ];

        $notification = Notification::create($notification);

        $notification->subscribe($userIds);
    }

    protected function handlePostReport($report, $userIds)
    {
        $post = $report->post;
        $postTitle = $post->getHumanExcerpt(60);

        $exist = Notification::where('feed_id', $post->id)
            ->where('action', 'post_report_added')
            ->first();

        $notificationContent = \sprintf(
        /* translators: %1$s is the feed title */
            __('A post has been automatically flagged for review: %1$s', 'fluent-community-pro'),
            '<span class="fcom_nft">' . $postTitle . '</span>'
        );

        if ($report->user_id) {
            $totalUsers = Moderation::where('post_id', $post->id)
                ->where('content_type', 'post')
                ->where('user_id', '!=', $report->user_id)
                ->distinct('user_id')
                ->count('user_id');

            $reporter = '<b class="fcom_nudn">' . $report->reporter->display_name . '</b>';

            if ($totalUsers) {
                $reporter .= sprintf(
                /* translators: %s is the number of other people */
                    __(' and %s other people', 'fluent-community-pro'),
                    '<b class="fcom_nrc">' . $totalUsers . '</b>'
                );
            }

            $notificationContent = \sprintf(
            /* translators: %1$s is the user name, %2$s is the feed title */
                __('%1$s flagged a post %2$s for your review', 'fluent-community-pro'),
                $reporter,
                '<span class="fcom_nft">' . $postTitle . '</span>'
            );

            if ($post->space_id) {
                $notificationContent = \sprintf(
                /* translators: %1$s is the user name, %2$s is the feed title  and %3$s space title*/
                    __('%1$s flagged a post %2$s for your review in %3$s', 'fluent-community-pro'),
                    $reporter,
                    '<span class="fcom_nft">' . $postTitle . '</span>',
                    '<span class="fcom_nst">' . $post->space->title . '</span>'
                );
            }
        }

        if ($exist) {
            $exist->content = $notificationContent;
            $exist->updated_at = current_time('mysql');
            $exist->src_user_id = $report->user_id;
            $exist->object_id = $report->id;
            $exist->save();

            NotificationSubscriber::where('object_id', $exist->id)
                ->update([
                    'is_read'    => 0,
                    'updated_at' => current_time('mysql')
                ]);
            return;
        }

        $notification = [
            'feed_id'         => $post->id,
            'object_id'       => $report->id,
            'src_user_id'     => $report->user_id,
            'src_object_type' => 'report',
            'action'          => 'post_report_added',
            'content'         => $notificationContent,
            'route'           => $post->getJsRoute(),
        ];

        $notification = Notification::create($notification);

        $notification->subscribe($userIds);
    }

    public function maybeFlagUpdatedFeed($data, $requestData)
    {
        if (Arr::isTrue($requestData, 'is_admin', false)) {
            return $data;
        }

        $moderationConfig = Helper::getModerationConfig();

        $profiency = Arr::get($moderationConfig, 'profanity_filter', '');

        $content = Arr::get($data, 'title') . ' ' . Arr::get($data, 'message');

        $flaggedWord = Helper::isProfanity($profiency, $content);

        if (!$flaggedWord) {
            return $data;
        }

        $data['status'] = 'pending';
        $data['meta']['reports_count'] = 1;
        $data['meta']['auto_flagged'] = 'yes';

        $reportData = [
            'post_id'       => null,
            'content_type'  => 'post',
            'user_id'       => null,
            'parent_id'     => null,
            'reports_count' => 1,
            'reason'        => 'profanity',
            'status'        => 'flagged',
            'explanation'   => 'Word: ' . $flaggedWord,
            'meta'          => [
                'flagged_by' => 'auto'
            ]
        ];

        add_action('fluent_community/feed/updated', function ($feed) use ($reportData) {
            $reportData['post_id'] = $feed->id;
            $report = Moderation::create($reportData);
            do_action('fluent_community/content_moderation/created', $report, $feed);
        }, 10, 1);

        return $data;
    }

    public function maybeFlagUpdatedComment($data, $feed, $requestData, $comment)
    {
        if (Arr::isTrue($requestData, 'is_admin', false)) {
            return $data;
        }

        $moderationConfig = Helper::getModerationConfig();

        $profiency = Arr::get($moderationConfig, 'profanity_filter', '');

        $content = Arr::get($data, 'message');

        $flaggedWord = Helper::isProfanity($profiency, $content);

        if (!$flaggedWord) {
            return $data;
        }

        $reportData = [
            'post_id'       => $feed->id,
            'content_type'  => 'comment',
            'user_id'       => null,
            'parent_id'     => $comment->id,
            'reports_count' => 1,
            'reason'        => 'profanity',
            'status'        => 'flagged',
            'explanation'   => 'Word: ' . $flaggedWord,
            'meta'          => [
                'flagged_by' => 'auto'
            ]
        ];

        add_action('fluent_community/comment_updated', function ($comment) use ($reportData) {
            $report = Moderation::create($reportData);
            do_action('fluent_community/content_moderation/created', $report, $comment);
        }, 10, 1);

        $data['status'] = 'pending';
        $data['meta']['reports_count'] = 1;
        $data['meta']['auto_flagged'] = 'yes';

        return $data;
    }

    public function handleCommentReportEmailAsync($reportId, $lastUserId)
    {
        $report = Moderation::find($reportId);

        $comment = Comment::find($report->parent_id);
        if (!$comment) {
            return;
        }

        $post = $comment->post;
        if (!$post) {
            return;
        }

        $userIds = $this->getNotificationUserIds($post);

        $users = User::query()->whereIn('ID', $userIds)
            ->whereHas('xprofile', function ($query) {
                return $query->where('status', 'active');
            })
            ->when($lastUserId, function ($q) use ($lastUserId) {
                $q->where('ID', '>', $lastUserId);
            })
            ->orderBy('ID', 'ASC')
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        $emailSubject = \sprintf(
        /* translators: %s is the comment excerpt (max 30 chars) */
            __('Comment flagged for review: %s', 'fluent-community-pro'),
            $comment->getHumanExcerpt(30)
        );

        $buttonText = __('Review the comment', 'fluent-community-pro');

        $emailBody = $comment->getCommentHtml(true, $buttonText);

        $postPermalink = $post->getPermalink() . '?comment_id=' . $comment->id;
        $usersCount = count($users);

        $startTime = microtime(true);
        $maxSendPerSecond = 10; // max 10 emails per second
        foreach ($users as $index => $user) {
            $lastUserId = $user->ID;
            if ($user->ID == $report->user_id) {
                continue;
            }

            $newEmailBody = str_replace([
                '##feed_permalink##',
                '##email_notification_url##'
            ], [
                ProfileHelper::signUserUrlWithAuthHash($postPermalink, $user->ID),
                ProfileHelper::getSignedNotificationPrefUrl($user->ID)
            ], $emailBody);

            $mailer = new Mailer('', $emailSubject, $newEmailBody);
            $mailer->to($user->user_email, $user->display_name);
            $mailer->send();

            if (microtime(true) - FLUENT_COMMUNITY_START_TIME > 50 && $index < ($usersCount - 1)) {
                as_schedule_single_action(
                    time(),
                    'fluent_community/comment_report_added_async',
                    [$reportId, $lastUserId],
                    'fluent-community'
                );
                return true;
            }

            if (($index + 1) % $maxSendPerSecond == 0) {
                $timeTaken = microtime(true) - $startTime;
                if ($timeTaken < 1) {
                    usleep( (int) (1000000 - ($timeTaken * 1000000)));
                }
                $startTime = microtime(true);
            }
        }

        return true;
    }

    public function handlePostReportEmailAsync($reportId, $lastUserId)
    {
        $report = Moderation::find($reportId);
        $post = $report->post;
        if (!$post) {
            return;
        }

        $userIds = $this->getNotificationUserIds($post);

        $users = User::query()->whereIn('ID', $userIds)
            ->whereHas('xprofile', function ($query) {
                return $query->where('status', 'active');
            })
            ->when($lastUserId, function ($q) use ($lastUserId) {
                $q->where('ID', '>', $lastUserId);
            })
            ->orderBy('ID', 'ASC')
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        $emailSubject = \sprintf(
        /* translators: %s is the post excerpt (max 30 chars) */
            __('Post flagged for review: %s', 'fluent-community-pro'),
            $post->getHumanExcerpt(30)
        );

        $buttonText = __('Review the post', 'fluent-community-pro');

        $emailBody = $post->getFeedHtml(true, $buttonText);

        $postPermalink = $post->getPermalink();
        $usersCount = count($users);

        $startTime = microtime(true);
        $maxSendPerSecond = 10; // max 10 emails per second
        foreach ($users as $index => $user) {
            $lastUserId = $user->ID;
            if ($user->ID == $report->user_id) {
                continue;
            }

            $newEmailBody = str_replace([
                '##feed_permalink##',
                '##email_notification_url##'
            ], [
                ProfileHelper::signUserUrlWithAuthHash($postPermalink, $user->ID),
                ProfileHelper::getSignedNotificationPrefUrl($user->ID)
            ], $emailBody);

            $mailer = new Mailer('', $emailSubject, $newEmailBody);
            $mailer->to($user->user_email, $user->display_name);
            $mailer->send();

            if (microtime(true) - FLUENT_COMMUNITY_START_TIME > 50 && $index < ($usersCount - 1)) {
                as_schedule_single_action(
                    time(),
                    'fluent_community/post_report_added_async',
                    [$reportId, $lastUserId],
                    'fluent-community'
                );
                return true;
            }

            if (($index + 1) % $maxSendPerSecond == 0) {
                $timeTaken = microtime(true) - $startTime;
                if ($timeTaken < 1) {
                    usleep( (int) (1000000 - ($timeTaken * 1000000)));
                }
                $startTime = microtime(true);
            }
        }
    }

    protected function getNotificationUserIds($post)
    {
        $userIds = User::whereHas('community_role')
            ->whereHas('xprofile', function ($q) {
                $q->where('status', 'active');
            })
            ->with('community_role')
            ->get()
            ->filter(function ($manager) {
                return array_intersect(['admin', 'moderator'], $manager->community_role->value);
            })
            ->pluck('ID')
            ->toArray();

        if ($post->space_id) {
            $spaceAdminIds = $post->space->admins->pluck('ID')->toArray();
            $userIds = array_unique(array_merge($spaceAdminIds, $userIds));
        }

        return $userIds;
    }
}
