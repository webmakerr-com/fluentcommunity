<?php

namespace FluentCommunity\Modules\Course\Model;


use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\Model;
use FluentCommunity\App\Models\Reaction;
use FluentCommunity\App\Models\Term;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Models\Media;
use FluentCommunity\App\Models\Comment;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Support\Arr;

/**
 *  Clourse Lesson Model - DB Model for Individual Clourse Lesson
 *
 *  Database Model
 *
 * @package FluentCrm\App\Models
 *
 * @version 1.1.0
 */
class CourseLesson extends Model
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
        'scheduled_at',
        'expired_at',
        'content_type',
        'comments_count',
        'reactions_count',
        'meta',
        'priority',
        'parent_id'
    ];

    protected $searchable = [
        'message',
        'title'
    ];

    public static $publicColumns = [
        'id', 'slug', 'title', 'message_rendered', 'featured_image', 'created_at', 'privacy', 'type', 'status', 'slug', 'space_id', 'user_id', 'meta', 'content_type', 'comments_count', 'reactions_count'
    ];

    protected static $type = 'course_lesson';

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->user_id = get_current_user_id();
            if (empty($model->slug)) {
                $model->slug = self::generateNewSlug($model);
            }

            $model->type = self::$type;

            if (empty($model->content_type)) {
                $model->content_type = 'text';
            }

            if (empty($model->message)) {
                $model->message = '';
            }

            if (empty($model->meta)) {
                $model->meta = self::getDefaultMeta();
            }
        });

        static::addGlobalScope('type', function ($builder) {
            $builder->where('type', self::$type);
        });
    }

    protected static function getDefaultMeta()
    {
        return [
            'media'           => [
                'type'         => 'oembed',
                'url'          => '',
                'content_type' => 'video',
                'html'         => ''
            ],
            'enable_comments' => 'yes',
            'enable_media'    => 'yes',
            'document_lists'  => []
        ];
    }

    protected static function generateNewSlug($newModel)
    {
        $slug = Utility::slugify($newModel->title, 'lesson-' . time());

        // check if the slug is available for this type
        $exist = self::where('slug', $slug)
            ->exists();

        if ($exist) {
            $slug = $slug . '-' . time();
        }

        return $slug;
    }

    public function topic()
    {
        return $this->belongsTo(CourseTopic::class, 'parent_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'space_id', 'id');
    }

    public function setMetaAttribute($value)
    {
        $this->attributes['meta'] = maybe_serialize($value);
    }

    public function getMetaAttribute($value)
    {
        $meta = Utility::safeUnserialize($value);

        if (!$meta) {
            $meta = self::getDefaultMeta();
        }

        return $meta;
    }

    public function getQuestionsAttribute()
    {
        return Arr::get($this->meta, 'quiz_questions', []);
    }

    public function getEnabledQuestionsAttribute()
    {
        return array_filter($this->questions, function($question) {
            return Arr::isTrue($question, 'enabled');
        });
    }

    public function getIsEnforcePassAttribute()
    {
        return Arr::isTrue($this->meta, 'enforce_passing_score');
    }

    public function getIsFreePreviewAttribute()
    {
        return Arr::isTrue($this->meta, 'free_preview_lesson');
    }

    public function getPassingScoreAttribute()
    {
        if (Arr::isTrue($this->meta, 'enable_passing_score')) {
            return Arr::get($this->meta, 'passing_score', 0);
        }
        return 0;
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

    public function comments()
    {
        return $this->hasMany(Comment::class, 'post_id', 'id');
    }

    public function reactions()
    {
        return $this->hasMany(Reaction::class, 'parent_id', 'id')
            ->where('object_type', 'comment');
    }

    public function lessonCompleted()
    {
        return $this->hasMany(Reaction::class, 'object_id', 'id')
            ->where('object_type', 'lesson_completed');
    }

    public function media()
    {
        return $this->hasMany(Media::class, 'feed_id', 'id');
    }

    public function terms()
    {
        return $this->belongsToMany(Term::class, 'fcom_term_feed', 'post_id', 'term_id');
    }

    public function isQuizType()
    {
        return $this->content_type == 'quiz';
    }
    
    public function getPermalink()
    {
        $uri = '/course/' . ($this->course ? $this->course->slug : 'undefined') . '/lessons/' . $this->slug . '/view';
        return Helper::baseUrl($uri);
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

    public function getPublicLessonMeta($canView = true)
    {
        $meta = $this->meta;

        if(!empty($meta['document_lists'])) {
            $docLists = $meta['document_lists'];
            foreach ($docLists as $index => $docList) {
                if(!empty($docList['media_key']) && !empty($docList['id'])) {
                    $docLists[$index]['url'] = Helper::baseUrl('?fcom_action=download_document&media_key='.$docList['media_key'].'&media_id='.$docList['id']);
                }
            }
            $meta['document_lists'] = $docLists;
        }

        if(!$canView) {
            $meta['document_lists'] = [];
            $meta['document_ids'] = [];
            unset($meta['media']);
        }

        $meta = apply_filters('fluent_community/lesson/get_public_meta', $meta, $this);

        return $meta;
    }
}
