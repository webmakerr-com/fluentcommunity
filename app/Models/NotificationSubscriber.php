<?php

namespace FluentCommunity\App\Models;

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
class NotificationSubscriber extends Model
{
    protected $table = 'fcom_notification_users';

    protected $primaryKey = 'id';

    protected $guarded = ['id'];

    protected $fillable = [
        'object_id',
        'user_id',
        'is_read',
        'object_type',
        'notification_type'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->object_type = 'notification';
            $model->notification_type = 'web';
        });

        static::updating(function ($model) {
            $model->object_type = 'notification';
            $model->notification_type = 'web';
        });

        // Add global scope to get only notifications
        static::addGlobalScope('notification', function ($builder) {
            $builder->where('object_type', 'notification');
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function xprofile()
    {
        return $this->belongsTo(XProfile::class, 'user_id', 'user_id');
    }

    public function notification()
    {
        return $this->belongsTo(Notification::class, 'object_id');
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', 0);
    }

    public function scopeRead($query)
    {
        return $query->where('is_read', 1);
    }

}
