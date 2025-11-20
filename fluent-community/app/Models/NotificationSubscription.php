<?php

namespace FluentCommunity\App\Models;

use FluentCommunity\App\Models\XProfile;
use FluentCommunity\App\Models\User;

/**
 *  Meta Model - DB Model for Notifications table
 *
 *  Database Model
 *
 * @package FluentCommunity\App\Models
 *
 * @version 1.0.0
 */
class NotificationSubscription extends Model
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
            $model->object_type = 'notification_pref';
        });

        static::updating(function ($model) {
            $model->object_type = 'notification_pref';
        });

        // Add global scope to get only notifications
        static::addGlobalScope('notification', function ($builder) {
            $builder->where('object_type', 'notification_pref');
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

}
