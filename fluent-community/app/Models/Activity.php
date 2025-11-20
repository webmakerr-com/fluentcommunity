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
class Activity extends Model
{
    protected $table = 'fcom_user_activities';

    protected $primaryKey = 'id';

    protected $guarded = ['id'];

    protected $fillable = [
        'user_id',
        'feed_id',
        'space_id',
        'related_id',
        'message',
        'is_public',
        'action_name'
    ];

    public static function boot()
    {
        parent::boot();
    }

    public function scopeByActions($query, $actions = [])
    {
        if (!empty($actions)) {
            $query->whereIn('action_name', $actions);
        }

        return $query;
    }

    public function scopeBySpace($query, $spaceId)
    {
        if ($spaceId) {
            return $query->where('space_id', $spaceId);
        }

        return $query->where('space_id', $spaceId);
    }

    public function feed()
    {
        return $this->belongsTo(Feed::class, 'feed_id');
    }

    public function space()
    {
        return $this->belongsTo(BaseSpace::class, 'space_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function xprofile()
    {
        return $this->belongsTo(XProfile::class, 'user_id', 'user_id');
    }

    public function getFormattedMessage()
    {
        if (!$this->xprofile || !$this->feed) {
            return '';
        }

        $message = '';

        switch ($this->action_name) {
            case 'feed_published':
                /* translators: %1$s is the person name and %2$s is the feed excerpt */
            $message = sprintf(__('%1$s published a new status %2$s', 'fluent-community'), '<span class="fcom_user_name">' . $this->xprofile->display_name . '</span>', '<span class="fcom_feed_excerpt">' . $this->feed->getHumanExcerpt() . '</span>');
                break;
            case 'comment_added':
                /* translators: %1$s is the person name and %2$s is the feed excerpt */
                $message = sprintf(__('%1$s added a comment on %2$s', 'fluent-community'), '<span class="fcom_user_name">' . $this->xprofile->display_name . '</span>', '<span class="fcom_feed_excerpt">' . $this->feed->getHumanExcerpt() . '</span>');
                break;
        }

        return $message;
    }
}
