<?php

namespace FluentCommunity\Modules\Course\Model;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\Comment;
use FluentCommunity\App\Models\Model;
use FluentCommunity\App\Models\Reaction;
use FluentCommunity\App\Models\Term;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Models\Meta;

class CourseTopic extends Model
{
    protected $table = 'fcom_posts';

    protected $guarded = ['id'];

    protected $casts = [
        'comments_count'  => 'int',
        'reactions_count' => 'int'
    ];

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'message',
        'message_rendered',
        'type',
        'space_id',
        'privacy',
        'status',
        'featured_image',
        'is_sticky',
        'expired_at',
        'comments_count',
        'reactions_count',
        'meta',
        'priority',
        'scheduled_at'
    ];

    protected $searchable = [
        'message',
        'title'
    ];

    public static $publicColumns = [
        'id', 'slug', 'title', 'message_rendered', 'featured_image', 'created_at', 'privacy', 'type', 'status', 'slug', 'space_id', 'user_id', 'meta', 'comments_count', 'reactions_count'
    ];

    protected static $type = 'course_section';

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->user_id = get_current_user_id();
            if (empty($model->slug)) {
                $model->slug = self::generateNewSlug($model);
            }

            $model->type = self::$type;

            if (empty($model->meta)) {
                $model->meta = self::getDefaultMeta();
            }
        });

        static::addGlobalScope('type', function ($builder) {
            $builder->where('type', self::$type);
        });
    }

    public function lessons()
    {
        return $this->hasMany(CourseLesson::class, 'parent_id')->orderBy('priority', 'ASC')->orderBy('id', 'ASC');
    }

    protected static function generateNewSlug($newModel)
    {
        $slug = Utility::slugify($newModel->title, 'topic');

        $exist = self::where('slug', $slug)
            ->exists();

        if ($exist) {
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

    public function scopeSearchBy($query, $search)
    {
        if (!$search) {
            return $query;
        }

        $fields = $this->searchable;
        $query->where(function ($query) use ($fields, $search) {
            $query->where(array_shift($fields), 'LIKE', "%$search%");
            foreach ($fields as $field) {
                $query->orWhere($field, 'LIKE', "$search%");
            }
        });

        return $query;
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id', 'ID');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'space_id', 'id');
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

    public function terms()
    {
        return $this->belongsToMany(Term::class, 'fcom_term_feed', 'post_id', 'term_id');
    }

    public function hasUserReact($userId, $type = 'like')
    {
        if (!$userId) {
            return false;
        }

        return (bool)Reaction::where('object_id', $this->id)
            ->select(['id'])
            ->where('object_type', 'feed')
            ->where('user_id', $userId)
            ->where('type', $type)
            ->first();
    }

    public function updateCustomMeta($key, $value)
    {
        $exist = Meta::where('object_id', $this->id)
            ->where('object_type', 'course_topic')
            ->where('meta_key', $key)
            ->first();

        if ($exist) {
            $exist->value = $value;
            $exist->save();
        } else {
            Meta::create([
                'object_id'   => $this->id,
                'object_type' => 'course_topic',
                'meta_key'    => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                'value'       => $value
            ]);
        }

        return true;
    }

    public function getCustomMeta($key, $default = null)
    {
        $exist = Meta::where('object_id', $this->id)
            ->where('object_type', 'course_topic')
            ->where('meta_key', $key)
            ->first();

        if ($exist) {
            return $exist->value;
        }

        return $default;
    }

    public function getHumanExcerpt($length = 40)
    {
        $content = $this->title;

        if (!$content) {
            $content = $this->message;
            if (!$content) {
                return '';
            }
            // remove all tags
            $content = wp_strip_all_tags($content);
            // remove new lines and tabs
            $content = str_replace(["\r", "\n", "\t"], ' ', $content);
            // remove multiple spaces
            $content = preg_replace('/\s+/', ' ', $content);

            // trim
            $content = trim($content);
        }

        if (!$content) {
            return '';
        }

        // return the first $length chars of the content with ... at the end
        return mb_substr($content, 0, $length) . '...';
    }

}
