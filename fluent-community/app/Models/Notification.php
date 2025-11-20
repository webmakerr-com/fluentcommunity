<?php

namespace FluentCommunity\App\Models;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\XProfile;

/**
 *  Meta Model - DB Model for Notifications table
 *
 *  Database Model
 *
 * @package FluentCommunity\App\Models
 *
 * @version 1.0.0
 */
class Notification extends Model
{
    protected $table = 'fcom_notifications';

    protected $primaryKey = 'id';

    protected $guarded = ['id'];

    protected $fillable = [
        'feed_id',
        'object_id',
        'src_user_id',
        'src_object_type',
        'action',
        'route',
        'content'
    ];

    public static function boot()
    {
        parent::boot();

        static::deleting(function ($notification) {
            NotificationSubscriber::where('object_id', $notification->id)
                ->delete();
        });
    }

    public function setRouteAttribute($value)
    {
        $this->attributes['route'] = maybe_serialize($value);
    }

    public function getRouteAttribute($value)
    {
        return Utility::safeUnserialize($value);
    }

    public function subscribers()
    {
        return $this->hasMany(NotificationSubscriber::class, 'object_id');
    }

    public function subscriber()
    {
        return $this->hasOne(NotificationSubscriber::class, 'object_id');
    }

    public function feed()
    {
        return $this->belongsTo(Feed::class, 'feed_id');
    }

    public function src_user()
    {
        return $this->belongsTo(User::class, 'src_user_id');
    }

    public function xprofile()
    {
        return $this->belongsTo(XProfile::class, 'src_user_id', 'user_id');
    }

    public function subscribe($userIds = [])
    {
        if (empty($userIds)) {
            return $this;
        }

        $subscribersData = [];

        $currentDateTime = current_time('mysql');

        foreach ($userIds as $userId) {
            $subscribersData[] = [
                'user_id'           => $userId,
                'object_id'         => $this->id,
                'object_type'       => 'notification',
                'notification_type' => 'web',
                'created_at'        => $currentDateTime,
                'updated_at'        => $currentDateTime,
                'is_read'           => 0
            ];
        }

        // let's insert by chunk, 50 per chunk
        $chunks = array_chunk($subscribersData, 50);
        foreach ($chunks as $chunk) {
            NotificationSubscriber::insert($chunk);
        }

        return $this;
    }

    public function scopeByStatus($query, $status, $userId)
    {
        $valids = ['unread', 'read'];
        if (!in_array($status, $valids)) {
            return $query;
        }

        $value = $status === 'unread' ? 0 : 1;

        $query->whereHas('subscribers', function ($query) use ($userId, $value) {
            return $query->where('user_id', $userId)
                ->where('is_read', $value);
        });
    }

    public function scopeByType($query, $type)
    {
        $valids = ['mentioned', 'following'];
        if (!in_array($type, $valids)) {
            return $query;
        }

        if ($type == 'mentioned') {
            $query->whereIn('action', ['feed/mentioned', 'mention_added']);
        } else {
            $query->whereNotIn('action', ['feed/mentioned', 'mention_added']);
        }

        return $query;
    }
}
