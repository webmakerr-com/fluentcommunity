<?php

namespace FluentCommunity\App\Models;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Services\FeedsHelper;
use FluentCommunity\App\Services\Helper;
use FluentCommunityPro\App\Models\Follow;

class Feed extends Model
{
    protected $table = 'fcom_posts';

    protected $guarded = ['id'];

    protected $casts = [
        'comments_count'  => 'int',
        'reactions_count' => 'int',
        'is_sticky'       => 'int',
        'priority'        => 'int',
    ];

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'message',
        'message_rendered',
        'type',
        'content_type',
        'space_id',
        'privacy',
        'status',
        'priority',
        'featured_image',
        'is_sticky',
        'expired_at',
        'scheduled_at',
        'comments_count',
        'reactions_count',
        'meta',
        'created_at',
        'updated_at'
    ];

    protected $searchable = [
        'message',
        'title'
    ];

    public static $publicColumns = [
        'id',
        'slug',
        'message_rendered',
        'meta',
        'title',
        'featured_image',
        'created_at',
        'privacy',
        'priority',
        'type',
        'content_type',
        'slug',
        'space_id',
        'user_id',
        'status',
        'is_sticky',
        'scheduled_at',
        'comments_count',
        'reactions_count'
    ];

    protected $appends = [
        'permalink'
    ];

    public static $scopeType = 'text';

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->user_id)) {
                $model->user_id = get_current_user_id();
            }
            if (empty($model->slug)) {
                $model->slug = self::generateNewSlug($model);
            }

            if (empty($model->meta)) {
                $model->meta = self::getDefaultMeta();
            }

            if (empty($model->status)) {
                $model->status = 'published';
            }

        });

        static::addGlobalScope('type', function ($builder) {
            $builder->where('type', self::$scopeType);
        });

        static::deleting(function ($feed) {
            Media::where('feed_id', $feed->id)
                ->update([
                    'is_active' => 0
                ]);
            Reaction::where('object_id', $feed->id)
                ->delete();
        });

    }

    protected static function generateNewSlug($newModel)
    {
        if ($newModel->title) {
            // Remove the emojis
            $title = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $newModel->title);
            // get the first 40 char from the title
            $title = substr($title, 0, 40);
        } else {
            // get the first 25 char from the message
            $title = substr($newModel->message, 0, 40);
        }

        $title = remove_accents($title);

        $title = strtolower($title);
        // only allow alphanumeric, dash, and underscore
        $title = trim(preg_replace('/[^a-z0-9-_]/', ' ', $title));

        $title = sanitize_title($title, 'post-' . time());

        // check if the slug is already exists
        $slug = $title;

        $count = 1;
        while (self::where('slug', $slug)->exists()) {
            if ($count == 5) {
                $count = time();
            }
            $slug = $title . '-' . $count;
            $count++;
        }

        if (strlen($slug) <= 4) {
            $slug = $slug . '-' . time();
        }

        return $slug;
    }

    protected static function getDefaultMeta()
    {
        return [
            'preview_data' => null
        ];
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

    public function scopeSearchBy($query, $search, $in = [])
    {
        if (!$search) {
            return $query;
        }

        if (!$in || !is_array($in)) {
            $in = ['post_content'];
        }

        $fields = $this->searchable;
        $query->where(function ($query) use ($fields, $search, $in) {
            if (in_array('post_content', $in)) {
                $query->where(array_shift($fields), 'LIKE', "%$search%");
                foreach ($fields as $field) {
                    $query->orWhere($field, 'LIKE', "%$search%");
                }
                
                $query->orWhere(function ($q) use ($search) {
                    $q->where('content_type', 'document')
                        ->where('meta', 'LIKE', '%document_lists%title%' . $search . '%');
                });

                if ($in && in_array('post_comments', $in)) {
                    $query->orWhereHas('comments', function ($q) use ($search) {
                        return $q->where('message', 'LIKE', "%$search%");
                    });
                }
            } else if ($in && in_array('post_comments', $in)) {
                $query->whereHas('comments', function ($q) use ($search) {
                    return $q->where('message', 'LIKE', "%$search%");
                });
            }
        });

        return $query;
    }

    public function scopeByUserAccess($query, $userId)
    {
        if ($userId) {
            return $query->where('user_id', $userId)->orWhereNull('space_id')
                ->orWhereHas('space', function ($q) use ($userId) {
                    $spaceIds = get_user_meta($userId, '_fcom_space_ids', true);
                    if ($spaceIds) {
                        $q->whereIn('id', $spaceIds);
                        return $q;
                    }
                    return $q->where('privacy', 'public');
                });
        }

        return $query->whereNull('space_id')
            ->orWhereHas('space', function ($q) {
                $q->where('privacy', 'public');
            });
    }

    public function scopeByContentModerationAccessStatus($query, $user, $space = null)
    {
        if (!$user || !Helper::isFeatureEnabled('content_moderation')) {
            return $query->where('status', 'published');
        }

        if (
            $user->hasCommunityModeratorAccess() ||
            ($space && $user->hasSpacePermission('edit_any_feed', $space))
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

    public function scopeByBookMarked($query, $userId)
    {
        return $query->whereHas('reactions', function ($q) use ($userId) {
            $q->where('user_id', $userId)
                ->where('type', 'bookmark');
        });
    }

    public function scopeByTopicSlug($query, $topicSlug)
    {
        if (!$topicSlug) {
            return $query;
        }

        return $query->whereHas('terms', function ($q) use ($topicSlug) {
            $topic = Term::where('taxonomy_name', 'post_topic')->where('slug', $topicSlug)->first();
            if ($topic) {
                $q->where('term_id', $topic->id);
            }
        });
    }

    public function scopeFilterBySpaceSlug($query, $space)
    {
        if (!$space) {
            return $query;
        }

        $query->whereHas('space', function ($q) use ($space) {
            $q->where('slug', $space);
        });

        return $query;
    }

    public function scopeByType($query, $type)
    {
        if (!$type) {
            return $query;
        }

        $query->where('type', $type);

        return $query;
    }

    public function scopeCustomOrderBy($query, $type)
    {
        $acceptedTypes = array_keys(Helper::getPostOrderOptions());

        if (!in_array($type, $acceptedTypes) || $type == 'latest') {
            return $query->orderBy('created_at', 'DESC');
        }

        if ($type == 'new_activity') {
            return $query->orderBy('updated_at', 'DESC');
        }

        if ($type == 'oldest') {
            return $query->orderBy('created_at', 'ASC');
        }

        if ($type == 'likes') {
            return $query->orderBy('reactions_count', 'DESC');
        }

        if ($type == 'unanswered') {
            return $query->where('comments_count', 0)
                ->orderBy('created_at', 'DESC');
        }

        if ($type == 'alphabetical') {
            return $query->orderBy('slug', 'ASC');
        }

        if ($type == 'popular') {
            // sort by comments_count + reactions_count desc
            return $query->orderByRaw('(reactions_count + (comments_count * 2)) DESC');
        }

        $query = apply_filters('fluent_community/custom_order_by', $query, $type);

        return $query;
    }

    public function scopeByStatus($query, $status)
    {
        if (!$status) {
            return $query->where('status', 'published');
        }

        $query->where('status', $status);

        return $query;
    }

    public function scopeByFollowing($query, $userId = null)
    {
        if (!Helper::isFeatureEnabled('followers_module')) {
            return $query;
        }

        if (!$userId) {
            $userId = get_current_user_id();
        }

        if (!$userId) {
            return $query;
        }

        return $query->whereHas('follows', function ($query) use ($userId) {
            $query->where('follower_id', $userId);
        });
    }

    public function scopeFilterByUserId($query, $userId)
    {
        if (!$userId) {
            return $query;
        }

        $query->where('user_id', $userId);

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

    public function space()
    {
        return $this->belongsTo(BaseSpace::class, 'space_id', 'id')
            ->withoutGlobalScopes();
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'post_id', 'id');
    }

    public function reactions()
    {
        return $this->hasMany(Reaction::class, 'object_id', 'id')
            ->where('object_type', 'feed');
    }

    // New Relationship: Follow records where this post's user_id is the followed_id
    public function follows()
    {
        return $this->hasMany(Follow::class, 'followed_id', 'user_id');
    }

    public function surveyVotes()
    {
        return $this->hasMany(Reaction::class, 'object_id', 'id')
            ->where('type', 'survey_vote');
    }

    public function terms()
    {
        return $this->belongsToMany(Term::class, 'fcom_term_feed', 'post_id', 'term_id');
    }

    public function hasUserReact($userId, $type = 'like')
    {
        if (!$userId) {
            return false;
        }

        return Reaction::select(['id'])
            ->where('object_id', $this->id)
            ->where('object_type', 'feed')
            ->where('user_id', $userId)
            ->where('type', $type)
            ->exists();
    }

    public function hasEditAccess($userId)
    {
        if (!$userId) {
            return false;
        }

        if ($this->user_id == $userId) {
            return true;
        }

        $userModel = User::find($userId);

        if (!$userModel) {
            return false;
        }

        if ($this->space_id) {
            return $userModel->hasSpacePermission('edit_any_feed', $this->space);
        }

        return $userModel->hasCommunityPermission('edit_any_feed');
    }

    public function getHumanExcerpt($length = 40)
    {
        $content = $this->title;
        if (!$content) {
            $content = $this->message;
        }

        return Helper::getHumanExcerpt($content, $length);
    }

    public function getPermalink()
    {
        $sectionPrefix = 'space';
        $contentPrefix = 'post';
        $isLesson = $this->type === 'course_lesson';

        if ($isLesson) {
            $sectionPrefix = 'course';
            $contentPrefix = 'lessons';
        }

        $urlPath = $contentPrefix . '/' . $this->slug;

        if ($this->space_id && $this->space) {
            $urlPath = $sectionPrefix . '/' . $this->space->slug . '/' . $contentPrefix . '/' . $this->slug;
            if ($isLesson) {
                $urlPath .= '/view';
            }
        }

        return Helper::baseUrl($urlPath);
    }

    public function getPermalinkAttribute()
    {
        return $this->getPermalink();
    }

    public function activities()
    {
        return $this->hasMany(Activity::class, 'feed_id', 'id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'feed_id', 'id');
    }

    public function media()
    {
        return $this->hasMany(Media::class, 'feed_id', 'id');
    }

    public function getSurveyCastsByUserId($userId = null)
    {
        if (!$userId || $this->content_type != 'survey') {
            return [];
        }

        return Utility::getFromCache('survey_cast_' . $this->id . '_' . $userId, function () use ($userId) {
            return Reaction::where('type', 'survey_vote')
                ->where('user_id', $userId)
                ->where('object_id', $this->id)
                ->pluck('object_type')->toArray();
        }, 86400);
    }

    public function updateCustomMeta($key, $value)
    {
        $exist = Meta::where('object_id', $this->id)
            ->where('object_type', 'feed')
            ->where('meta_key', $key)
            ->first();

        if ($exist) {
            $exist->value = $value;
            $exist->save();
        } else {
            Meta::create([
                'object_id'   => $this->id,
                'object_type' => 'feed',
                'meta_key'    => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                'value'       => $value
            ]);
        }

        return true;
    }

    public function getCustomMeta($key, $default = null)
    {
        $exist = Meta::where('object_id', $this->id)
            ->where('object_type', 'feed')
            ->where('meta_key', $key)
            ->first();

        if ($exist) {
            return $exist->value;
        }

        return $default;
    }

    public function attachTopics($topicIds, $sync = false)
    {
        if ((!$topicIds && !$sync) || !$this->space_id) {
            return $this;
        }

        // let's find the valid topics for this space
        $spaceTopics = Utility::getTopicsBySpaceId($this->space_id);
        $spaceTopicIds = array_map(function ($topic) {
            return $topic['id'];
        }, $spaceTopics);

        $validTopicIds = array_filter($topicIds, function ($topicId) use ($spaceTopicIds) {
            return in_array($topicId, $spaceTopicIds);
        });

        if ($sync) {
            $this->terms()->sync($validTopicIds);
        } else {
            $this->terms()->attach($validTopicIds);
        }

        return $this;
    }

    public function getJsRoute()
    {
        if ($this->type == 'course_lesson') {
            return [
                'name'   => 'view_lesson',
                'params' => [
                    'course_slug' => $this->space ? $this->space->slug : 'uknown',
                    'lesson_slug' => $this->slug
                ]
            ];
        }

        if ($this->space_id) {
            $route = [
                'name'   => 'space_feed',
                'params' => [
                    'space'     => $this->space->slug,
                    'feed_slug' => $this->slug
                ]
            ];
        } else {
            $route = [
                'name'   => 'single_feed',
                'params' => [
                    'feed_slug' => $this->slug
                ]
            ];
        }

        return $route;
    }

    public function recountStats()
    {
        $this->comments_count = Comment::where('post_id', $this->id)
            ->where('type', 'comment')
            ->count();

        $this->reactions_count = Reaction::where('object_type', 'feed')->where('type', 'like')
            ->where('object_id', $this->id)
            ->count();

        $this->save();
        return $this;
    }

    public function isEnabledForEveryoneTag()
    {
        return ($this->meta['send_announcement_email'] ?? null) === 'yes' && Utility::hasEmailAnnouncementEnabled();
    }

    public function getFeedHtml($withPlaceholder = false, $buttonText = null)
    {
        $emailComposer = new \FluentCommunity\App\Services\Libs\EmailComposer();

        if ($withPlaceholder) {
            $postPermalink = '##feed_permalink##';
        } else {
            $postPermalink = $this->getPermalink();
        }

        $feedHtml = $this->message_rendered;
        $feedHtml .= FeedsHelper::getMediaHtml($this->meta, $postPermalink);

        $buttonText = $buttonText ?: __('Join the conversation', 'fluent-community');

        $emailComposer->addBlock('post_boxed_content', $feedHtml, [
            'user'       => $this->user,
            'title'      => $this->title,
            'permalink'  => $postPermalink,
            'space_name' => $this->space ? $this->space->title : __('Community', 'fluent-community'),
            'is_single'  => true
        ]);

        $emailComposer->addBlock('button', $buttonText, [
            'link' => $postPermalink
        ]);

        $emailComposer->setDefaultLogo();

        $emailComposer->setDefaultFooter();

        return $emailComposer->getHtml();
    }
}
