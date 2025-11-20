<?php

namespace FluentCommunityPro\App\Models;

use FluentCommunity\App\Models\XProfile;

class Follow extends Model
{
    protected $table = 'fcom_followers';

    protected $guarded = ['id'];

    protected $fillable = [
        'follower_id',
        'followed_id',
        'level'
    ];

    protected $attributes = [
        'level' => 1
    ];

    public static function boot()
    {
        parent::boot();
    }

    // Relationship: The user who is following
    public function follower()
    {
        return $this->belongsTo(XProfile::class, 'follower_id', 'user_id')
            ->where('status', 'active');
    }

    // Relationship: The user being followed
    public function followed()
    {
        return $this->belongsTo(XProfile::class, 'followed_id', 'user_id')
            ->where('status', 'active');
    }
}
