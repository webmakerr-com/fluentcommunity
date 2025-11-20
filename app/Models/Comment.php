<?php

namespace FluentCommunity\App\Models;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\App\Models\Activity;
use FluentCommunity\App\Services\FeedsHelper;
use FluentCommunity\App\Services\Helper;

class Comment extends Model
{
    protected $table = 'fcom_post_comments';

    protected $guarded = ['id'];

    protected $fillable = [
        'user_id',
        'post_id',
        'parent_id',
        'message',
        'message_rendered',
        'meta',
        'type',
        'content_type',
        'status',
        'is_sticky',
        'reactions_count',
        'created_at',
        'updated_at'
    ];

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
            $model->type = 'comment';

            if(empty($model->status)) {
                $model->status = 'published';
            }

        });

        static::deleting(function ($comment) {
            Media::where('sub_object_id', $comment->id)
                ->where('object_source', 'comment')
                ->update([
                    'is_active' => 0
                ]);

            Activity::where('feed_id', $comment->post_id)
                ->where('action_name', 'comment_added')
                ->where('related_id', $comment->id)
                ->delete();

            $notifications = Notification::where('object_id', $comment->id)
                ->where('src_object_type', 'comment')
                ->get();

            foreach ($notifications as $notification) {
                $notification->delete();
            }

            $childComments = Comment::where('parent_id', $comment->id)
                ->where('post_id', $comment->post_id)
                ->get();

            foreach ($childComments as $childComment) {
                $childComment->delete();
            }
        });

        static::addGlobalScope('type', function ($builder) {
            $builder->where('type', 'comment');
        });
    }

    public function scopeSearchBy($query, $search)
    {
        if ($search) {
            $fields = $this->searchable;
            $query->where(function ($query) use ($fields, $search) {
                $query->where(array_shift($fields), 'LIKE', "%$search%");
                foreach ($fields as $field) {
                    $query->orWhere($field, 'LIKE', "$search%");
                }
            });
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

    public function media()
    {
        return $this->hasOne(Media::class, 'sub_object_id', 'id');
    }

    public function post()
    {
        return $this->belongsTo(Feed::class, 'post_id', 'id')->withoutGlobalScopes();
    }

    public function space()
    {
        return $this->hasOneThrough(
            BaseSpace::class,
            Feed::class,
            'id',         // Foreign key on the feeds table
            'id',         // Foreign key on the spaces table
            'post_id',    // Local key on the comments table
            'space_id'    // Local key on the feeds table
        )->withoutGlobalScopes();
    }

    public function reactions()
    {
        return $this->hasMany(Reaction::class, 'object_id', 'id')
            ->where('object_type', 'comment');
    }

    public function scopeByContentModerationAccessStatus($query, $user, $space = null)
    {
        if (!$user || !Helper::isFeatureEnabled('content_moderation')) {
            return $query->where('status', 'published');
        }

        if (
            $user->hasCommunityModeratorAccess() ||
            ($space && $user->hasSpacePermission('edit_any_comment', $space))
        ) {
            return $query->whereIn('status', ['published', 'pending']);
        }

        // This is a normal User.
        return $query->where(function ($q) use ($user) {
            $q->where('status', 'published')
                ->orWhere(function ($q) use ($user) {
                    $q->where('status', 'pending')
                        ->where('user_id', $user->ID);
                });
        });

    }

    /*
     * Find all the user ids of a child comment who commented on the parent comment including the parent comment author
     */
    public function getCommentParentUserIds($lastUserId = 0)
    {
        if (!$this->parent_id) {
            return [];
        }

        $parentComment = Comment::select(['user_id'])->find($this->parent_id);
        $allUserIds = Comment::where('parent_id', $this->parent_id)
            ->select(['user_id'])
            ->distinct('user_id')
            ->when($lastUserId, function ($query) use ($lastUserId) {
                $query->where('user_id', '>', $lastUserId);
            })
            ->get()
            ->pluck('user_id')
            ->toArray();

        if ($parentComment) {
            if ($parentComment->user_id > $lastUserId) {
                $allUserIds[] = $parentComment->user_id;
            }
        }

        return array_values(array_unique($allUserIds));
    }

    public function setMetaAttribute($value)
    {
        $this->attributes['meta'] = maybe_serialize($value);
    }

    public function getMetaAttribute($value)
    {
        $meta = Utility::safeUnserialize($value);

        if (!$meta) {
            $meta = [];
        }

        return $meta;
    }

    public function getHumanExcerpt($length = 30)
    {
        $content = $this->message;

        return Helper::getHumanExcerpt($content, $length);
    }

    public function getEmailSubject($feed = null)
    {
        if (!$feed) {
            $feed = $this->post;
        }

        if ($this->parent_id) {
            if ($feed->title) {
                /* translators: %1$s is the feed title and %2$s is the comment author name */
                $emailSubject = \sprintf(__('New reply on comment at %1$s - %2$s', 'fluent-community'), $feed->title, $this->xprofile->display_name);
            } else {
                /* translators: %1$s is the post title and %2$s is the comment author name */
                $emailSubject = \sprintf(__('New reply of a comment in a post on %1$s - %2$s', 'fluent-community'), $this->post->getHumanExcerpt(40), $this->xprofile->display_name);
            }
        } else {
            if ($feed->title) {
                /* translators: %1$s is the feed title and %2$s is the comment author name */
                $emailSubject = \sprintf(__('New comment on %1$s - %2$s', 'fluent-community'), $feed->title, $this->xprofile->display_name);
            } else {
                /* translators: %1$s is the post title and %2$s is the comment author name */
                $emailSubject = \sprintf(__('New comment on a post on %1$s - %2$s', 'fluent-community'), $this->post->getHumanExcerpt(40), $this->xprofile->display_name);
            }
        }

        return $emailSubject;
    }

    public function getCommentHtml($withPlaceholder = false, $buttonText = null)
    {
        $emailComposer = new \FluentCommunity\App\Services\Libs\EmailComposer();
        if ($withPlaceholder) {
            $postPermalink = '##feed_permalink##';
        } else {
            $postPermalink = $this->post->getPermalink() . '?comment_id=' . $this->id;
        }

        if ($this->post->title) {
            $postTitle = $this->post->title;
        } else {
            $postTitle = $this->post->getHumanExcerpt(120);
        }

        $renderedMessage = $this->message_rendered;

        // Remove all the URLs with the text but make it underlined
        $renderedMessage = preg_replace('/<a href="([^"]+)">([^<]+)<\/a>/', '<span style="text-decoration: underline !important;">$2</span>', $renderedMessage);

        $renderedMessage .= FeedsHelper::getMediaHtml($this->meta, $postPermalink);

        $buttonText = $buttonText ?: __('View the comment', 'fluent-community');

        $emailComposer->addBlock('boxed_content', $renderedMessage, [
            'user'         => $this->user,
            'permalink'    => $postPermalink,
            'post_content' => $postTitle
        ]);

        $emailComposer->addBlock('button', $buttonText, [
            'link' => $postPermalink
        ]);

        $emailComposer->setDefaultLogo();
        $emailComposer->setDefaultFooter($withPlaceholder);

        return $emailComposer->getHtml();
    }
}
