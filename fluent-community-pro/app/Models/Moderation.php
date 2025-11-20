<?php

namespace FluentCommunityPro\App\Models;

use FluentCommunity\App\Models\Feed;
use FluentCommunity\App\Models\Comment;
use FluentCommunity\App\Models\Notification;
use FluentCommunity\App\Models\XProfile;

class Moderation extends Model
{
    protected $table = 'fcom_post_comments';

    protected $guarded = ['id'];

    protected $fillable = [
        'user_id',
        'parent_id',
        'post_id',
        'reason',
        'explanation',
        'meta',
        'type',
        'status',
        'content_type',
        'reports_count',
        'created_at',
        'updated_at'
    ];

    protected $attributeMap = [
        'reason'        => 'message',
        'explanation'   => 'message_rendered',
        'reports_count' => 'reactions_count'
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->status)) {
                $model->status = 'pending';
            }
            $model->type = 'report';
        });

        static::deleting(function ($model) {
            $notifications = Notification::where('object_id', $model->id)
                ->where('src_object_type', 'report')
                ->get();

            foreach ($notifications as $notification) {
                $notification->delete();
            }
        });

        static::addGlobalScope('type', function ($builder) {
            $builder->where('type', 'report');
        });

        static::addGlobalScope('defaultSelect', function ($builder) {
            $builder->select([
                'id',
                'message as reason',
                'message_rendered as explanation',
                'meta',
                'post_id',
                'user_id',
                'parent_id',
                'type',
                'status',
                'content_type',
                'reactions_count as reports_count',
                'created_at'
            ]);
        });
    }

    public function post()
    {
        return $this->belongsTo(Feed::class, 'post_id', 'id');
    }

    public function comment()
    {
        return $this->belongsTo(Comment::class, 'parent_id', 'id');
    }

    public function reporter()
    {
        return $this->belongsTo(XProfile::class, 'user_id', 'user_id');
    }

    public function getAttribute($key)
    {
        if (array_key_exists($key, $this->attributeMap)) {
            return parent::getAttribute($this->attributeMap[$key]);
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        if (array_key_exists($key, $this->attributeMap)) {
            return parent::setAttribute($this->attributeMap[$key], $value);
        }

        return parent::setAttribute($key, $value);
    }

    public function setMetaAttribute($value)
    {
        $this->attributes['meta'] = \maybe_serialize($value);
    }

    public function getMetaAttribute($value)
    {
        return \maybe_unserialize($value);
    }
}
