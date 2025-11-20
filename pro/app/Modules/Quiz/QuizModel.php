<?php

namespace FluentCommunityPro\App\Modules\Quiz;

use FluentCommunity\Framework\Support\Arr;
use FluentCommunityPro\App\Models\Model;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\Modules\Course\Model\CourseLesson;

class QuizModel extends Model
{
    protected $table = 'fcom_post_comments';

    protected $guarded = ['id'];

    protected $fillable = [
        'user_id',
        'post_id',
        'parent_id',
        'meta',
        'type',
        'score',
        'status',
        'message',
        'content_type',
        'created_at',
        'updated_at'
    ];

    protected $attributeMap = [
        'score'   => 'reactions_count'
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->type = 'quiz';
            $model->content_type = 'quiz';
            if (empty($model->status)) {
                $model->status = 'published';
            }
        });

        static::addGlobalScope('type', function ($builder) {
            $builder->where('type', 'quiz');
        });

        static::addGlobalScope('defaultSelect', function ($builder) {
            $builder->select([
                'id',
                'user_id',
                'post_id',
                'parent_id',
                'meta',
                'type',
                'status',
                'content_type',
                'message',
                'reactions_count as score',
                'updated_at'
            ]);
        });
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

    public function getMessageAttribute($value)
    {
        return \maybe_unserialize($value);
    }

    public function setMessageAttribute($value)
    {
        $this->attributes['message'] = \maybe_serialize($value);
    }

    public function xprofile()
    {
        return $this->belongsTo(XProfile::class, 'user_id', 'user_id');
    }

    public function lesson()
    {
        return $this->belongsTo(CourseLesson::class, 'post_id', 'id');
    }

    public function hideCorrectAnswers()
    {
        return array_map(function ($answer) {
            if (isset($answer['is_correct'])) {
                unset($answer['is_correct']);
            }
            return $answer;
        }, $this->message);
    }

    public static function isCorrectAnswer($question, $userAnswer)
    {
        $questionType = Arr::get($question, 'type');
        $questionOptions = Arr::get($question, 'options');

        if ($questionType === 'single_choice') {
            foreach ($questionOptions as $option) {
                if ($userAnswer == Arr::get($option, 'label') && Arr::isTrue($option, 'is_correct')) {
                    return true;
                }
            }
            return false;
        }

        if ($questionType === 'multiple_choice') {
            $correctLabels = [];
            foreach ($questionOptions as $option) {
                if (Arr::isTrue($option, 'is_correct')) {
                    $correctLabels[] = Arr::get($option, 'label');
                }
            }
            return is_array($userAnswer) && count($userAnswer) == count($correctLabels) && !array_diff($userAnswer, $correctLabels);
        }

        return false;
    }
}
