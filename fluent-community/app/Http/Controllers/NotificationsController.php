<?php

namespace FluentCommunity\App\Http\Controllers;

use FluentCommunity\App\Http\Controllers\Controller;
use FluentCommunity\App\Models\Feed;
use FluentCommunity\App\Models\Notification;
use FluentCommunity\App\Models\NotificationSubscriber;
use FluentCommunity\App\Services\ProfileHelper;
use FluentCommunity\Framework\Http\Request\Request;

class NotificationsController extends Controller
{
    public function getNotifications(Request $request)
    {
        $user = $this->getUser(true);

        $notifcations = Notification::whereHas('subscribers', function ($query) use ($user) {
            return $query->where('user_id', $user->ID);
        })
            ->with([
                'xprofile'   => function ($q) {
                    return $q->select(ProfileHelper::getXProfilePublicFields());
                },
                'subscriber' => function ($q) {
                    return $q->where('user_id', get_current_user_id());
                }
            ])
            ->byStatus($request->get('status'), $user->ID)
            ->byType($request->get('notification_type', 'all'))
            ->orderBy('updated_at', 'DESC')
            ->paginate();

        return [
            'notifications' => $notifcations
        ];
    }

    public function getUnreadNotifications(Request $request)
    {
        $user = $this->getUser(true);

        $unreadNotifications = Notification::byStatus('unread', $user->ID)
            ->with(['xprofile' => function ($q) {
                return $q->select(ProfileHelper::getXProfilePublicFields());
            }])
            ->orderBy('updated_at', 'DESC')
            ->byType($request->get('notification_type', 'all'))
            ->limit(50)
            ->get();

        return [
            'notifications' => $unreadNotifications,
            'unread_count'  => Notification::byStatus('unread', get_current_user_id())->count()
        ];
    }

    public function markAllRead(Request $request)
    {
        NotificationSubscriber::where('is_read', 0)
            ->where('user_id', get_current_user_id())
            ->update(['is_read' => 1]);

        return [
            'message' => __('All notifications have been marked as read.', 'fluent-community')
        ];
    }

    public function markAsRead(Request $request, $notification_id)
    {
        $notification = Notification::find($notification_id);

        NotificationSubscriber::whereHas('notification', function ($query) use ($notification) {
            return $query->where('id', $notification->id)
                ->orWhere('feed_id', $notification->feed_id);
        })
            ->where('user_id', get_current_user_id())
            ->update(['is_read' => 1]);

        return [
            'unread_count' => Notification::byStatus('unread', get_current_user_id())->count()
        ];

        return $this->getUnreadNotifications($request);
    }

    public function markAsReadByFeedId(Request $request, $feedId)
    {
        $feed = Feed::findOrfail($feedId);

        NotificationSubscriber::whereHas('notification', function ($query) use ($feed) {
            return $query->where('feed_id', $feed->id);
        })
            ->where('user_id', get_current_user_id())
            ->update(['is_read' => 1]);

        $userModel = $this->getUser();

        return [
            'unread_notification_count' => $userModel->getUnreadNotificationCount(),
            'unread_feed_ids'           => $userModel->getUnreadNotificationFeedIds(),
        ];
    }
}
