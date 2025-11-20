<?php

namespace FluentCommunity\App\Hooks\Handlers;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\Media;
use FluentCommunity\App\Models\Notification;
use FluentCommunity\App\Models\NotificationSubscriber;
use FluentCommunity\App\Models\Reaction;

class CleanupHandler
{
    public function register()
    {
        add_action('fluent_community/feed/before_deleted', [$this, 'handleFeedDeleted'], 10, 1);
        add_action('fluent_community/lesson/before_deleted', [$this, 'handleLessonDeleted'], 10, 1);
        add_action('fluent_community/remove_old_notifications', [$this, 'maybeDeleteOldNotifications']);

        add_action('fluent_community/comment/media_deleted', [$this, 'queueMediaDelete'], 10, 1);
        add_action('fluent_community/feed/media_deleted', [$this, 'queueMediaDelete'], 10, 1);
        add_action('fluent_community/maybe_delete_draft_medias', [$this, 'deleteOldDraftMedias'], 10);
        add_action('fluent_community/remove_medias_by_url', function ($mediaUrls, $wheres = []) {
            if (!$mediaUrls) {
                return;
            }

            $subObjectId = isset($wheres['sub_object_id']) ? $wheres['sub_object_id'] : null;
            $media = Media::whereIn('media_url', $mediaUrls)
                ->when($subObjectId, function ($q) use ($subObjectId) {
                    $q->where('sub_object_id', $subObjectId);
                })
                ->get();

            if ($media->isEmpty()) {
                return;
            }

            $this->queueMediaDelete($media);
        }, 10, 2);

        add_action('deleted_user', [$this, 'handleUserDeleted'], 10, 2);
    }

    public function handleFeedDeleted($feed)
    {
        $feed->comments()->delete();
        $feed->reactions()->delete();
        $feed->activities()->delete();
        $this->queueMediaDelete($feed->media);

        if ($feed->comtent_type == 'survey') {
            Reaction::where('type', 'survey_vote')
                ->where('object_id', $feed->id)
                ->delete();
        }

        // Loop is used to delete notification with notified user data from fcom_notification_users table
        foreach ($feed->notifications as $notification) {
            $notification->delete();
        }

        Utility::getApp('db')->table('fcom_term_feed')->where('post_id', $feed->id)->delete();
    }

    public function handleLessonDeleted($lesson)
    {
        $lesson->comments()->delete();
        $lesson->reactions()->delete();
        $lesson->lessonCompleted()->delete();

        $mediaKeys = $lesson->media ? $lesson->media->pluck('media_key')->all() : [];

        $duplicateMediaKeys = Media::whereIn('media_key', $mediaKeys)
            ->where('feed_id', '!=', $lesson->id)
            ->groupBy('media_key')
            ->pluck('media_key')
            ->all();

        $deletedIds = [];
        if (!empty($duplicateMediaKeys)) {
            $deletedIds = Media::where('feed_id', $lesson->id)
                ->whereIn('media_key', $duplicateMediaKeys)
                ->pluck('id')
                ->all();

            Media::where('feed_id', $lesson->id)
                ->whereIn('media_key', $duplicateMediaKeys)
                ->delete();
        }

        $lessonMedia = $lesson->media ? $lesson->media->filter(function ($media) use ($deletedIds) {
            return !in_array($media->id, $deletedIds);
        }) : null;

        $this->queueMediaDelete($lessonMedia);
    }

    public function queueMediaDelete($media)
    {
        if (is_null($media)) {
            return false;
        }

        if ($media instanceof \FluentCommunity\Framework\Database\Orm\Collection) {

            if ($media->isEmpty()) {
                return false;
            }

            if (apply_filters('fluent_community/handle_remove_bulk_media', false, $media)) {
                return true;
            }

            foreach ($media as $medium) {
                if ($medium->driver == 'local') {
                    $medium->delete();
                } else {
                    $medium->is_active = 0;
                    $medium->save();
                }
            }
        } else {
            if ($media->driver == 'local') {
                $media->delete();
            } else {
                $media->is_active = 0;
                $media->save();
            }
        }
    }

    public function handleMediaDeleted($media)
    {
        if (!$media) {
            return false;
        }

        if ($media instanceof \FluentCommunity\Framework\Database\Orm\Collection) {
            if ($media->isEmpty()) {
                return false;
            }

            if (apply_filters('fluent_community/handle_remove_bulk_media', false, $media)) {
                return true;
            }

            foreach ($media as $medium) {
                $medium->delete();
            }
        } else {
            $media->delete();
        }
    }

    /*
     * Remove old notifications which are more than 1 month old
     */
    public function maybeDeleteOldNotifications()
    {
        $notifications = Notification::where('updated_at', '<', gmdate('Y-m-d H:i:s', strtotime('-1 month')))
            ->limit(100)
            ->get();

        $ids = [];
        foreach ($notifications as $notification) {
            $ids[] = $notification->id;
        }

        if (!$ids) {

            // Let's delete meta data
            Utility::getApp('db')->table('fcom_meta')->whereIn('meta_key', [
                '_last_mention_email_user_id',
                '_last_email_user_id'
            ])
                ->limit(9000)
                ->where('created_at', '<', gmdate('Y-m-d H:i:s', strtotime('-1 week')))
                ->delete();

            return false;
        }

        NotificationSubscriber::whereIn('object_id', $ids)->delete();

        Notification::whereIn('id', $ids)->delete();

        if (microtime(true) - FLUENT_COMMUNITY_START_TIME < 30) {
            $this->maybeDeleteOldNotifications();
        }


    }

    public function deleteOldDraftMedias()
    {
        $oldUnusedMedias = Media::where('is_active', '0')
            ->where('created_at', '<=', gmdate('Y-m-d H:i:s', current_time('timestamp') - 7200)) // 2 hours old media items
            ->limit(30)
            ->get();

        if ($oldUnusedMedias->isEmpty()) {
            return false;
        }

        $this->handleMediaDeleted($oldUnusedMedias);

        if (microtime(true) - FLUENT_COMMUNITY_START_TIME < 45) {
            $this->deleteOldDraftMedias();
        }

        return true;
    }

    public function handleUserDeleted($userId, $reassign)
    {
        /*
         * - Delete user related feeds where user_id = user_id
         * - Delete user related comments where user_id = user_id
         * - Delete likes where user_id = user_id
         * - Delete user related notifications where user_id = user_id
         *
         * fcom_chat_messages where user_id = user_id
         * fcom_chat_thread_users where user_id = user_id
         *
         * fcom_meta where object_type = user and object_id = user_id
         *
         * fcom_notification_users where user_id = user_id
         * fcom_post_comments where user_id = user_id
         * fcom_post_reactions where user_id = user_id
         * fcom_posts where user_id = user_id & type = text
         * fcom_space_user where user_id = user_id
         * fcom_user_activities where user_id = user_id
         * fcom_xprofile where user_id = user_id
         *
         * Delete the related media data as well which are connected with posts and comments
         *
         * ## Media
         * where object_source = user_photo|user_cover_photo|comment|feed|chat_message	and user_id = user_id
         *
         *
         */
        if (defined('FLUENT_MESSAGING_CHAT_VERSION')) {
            \FluentMessaging\App\Models\Thread::whereHas('thread_users', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
                ->whereNull('space_id')
                ->delete();

            Utility::getApp('db')->table('fcom_chat_messages')->where('user_id', $userId)->delete();
            Utility::getApp('db')->table('fcom_chat_thread_users')->where('user_id', $userId)->delete();
        }

        // meta
        Utility::getApp('db')->table('fcom_meta')->where('object_type', 'user')->where('object_id', $userId)->delete();

        // notifications
        Utility::getApp('db')->table('fcom_notification_users')->where('user_id', $userId)->delete();

        // comments
        Utility::getApp('db')->table('fcom_post_comments')->where('user_id', $userId)->delete();

        // reactions
        Utility::getApp('db')->table('fcom_post_reactions')->where('user_id', $userId)->delete();

        // posts
        Utility::getApp('db')->table('fcom_posts')->where('user_id', $userId)->where('type', 'text')->delete();

        // space user
        Utility::getApp('db')->table('fcom_space_user')->where('user_id', $userId)->delete();

        // user activities
        Utility::getApp('db')->table('fcom_user_activities')->where('user_id', $userId)->delete();

        // xprofile
        Utility::getApp('db')->table('fcom_xprofile')->where('user_id', $userId)->delete();

        // Delete the related media data as well which are connected with posts and comments
        // We are just going to update the is_active column to 0 then it will be deleted via cron jobs
        Media::whereIn('object_source', [
            'user_photo',
            'user_cover_photo',
            'comment',
            'feed',
            'chat_message'
        ])
            ->where('user_id', $userId)
            ->update([
                'is_active' => 0
            ]);
    }
}
