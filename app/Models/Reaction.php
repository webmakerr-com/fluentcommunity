<?php

namespace FluentCommunity\App\Models;

use FluentCommunity\App\Models\Model;
use FluentCommunity\App\Models\XProfile;

class Reaction extends Model
{
    protected $table = 'fcom_post_reactions';

    protected $guarded = ['id'];

    protected $fillable = [
        'user_id',
        'object_id',
        'object_type',
        'type',
        'ip_address',
        'parent_id',
        'created_at',
        'updated_at'
    ];

    protected $hidden = ['ip_address'];

    protected $searchable = [
        'message'
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {

            if (empty($model->user_id)) {
                $model->user_id = get_current_user_id();
            }

            if (empty($model->object_type)) {
                $model->object_type = 'feed';
            }

            if (empty($model->type)) {
                $model->type = 'like';
            }

        });
    }

    public function scopeTypeBy($query, $type = 'like')
    {
        if ($type) {
            $query->where('type', $type);
        }

        return $query;
    }

    public function scopeObjectType($query, $type = 'feed')
    {
        if ($type) {
            $query->where('object_type', $type);
        }

        return $query;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'ID');
    }

    public function xprofile()
    {
        return $this->belongsTo(XProfile::class, 'user_id', 'user_id');
    }

    public function feed()
    {
        return $this->belongsTo(Feed::class, 'object_id', 'id')
            ->where('object_type', 'feed');
    }

    public function comment()
    {
        return $this->belongsTo(Comment::class, 'object_id', 'id')
            ->where('object_type', 'comment');
    }
}
