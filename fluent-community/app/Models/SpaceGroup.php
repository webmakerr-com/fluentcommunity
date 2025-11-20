<?php

namespace FluentCommunity\App\Models;


use FluentCommunity\App\Functions\Utility;
use FluentCommunity\Framework\Support\Arr;

class SpaceGroup extends Model
{
    protected $table = 'fcom_spaces';

    protected static $type = 'space_group';

    protected $guarded = ['id'];

    protected $fillable = [
        'created_by',
        'parent_id',
        'title',
        'slug',
        'description',
        'logo',
        'cover_photo',
        'type',
        'privacy',
        'status',
        'serial',
        'settings'
    ];

    protected $searchable = [
        'title',
        'description'
    ];

    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->created_by)) {
                $model->created_by = get_current_user_id();
            }

            if (empty($model->slug)) {
                $slug = sanitize_title($model->tilte, time());
                $model->slug = $slug;
            }

            $model->type = static::$type;
        });

        static::addGlobalScope('type', function ($query) {
            if (static::$type) {
                $query->where('type', static::$type);
            }

            return $query;
        });
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'created_by', 'ID');
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

    public function updateCustomData($data, $removeSrc = false)
    {
        $deletePhotos = [];
        if (isset($data['logo'])) {
            $deletePhotos[] = $this->logo;
            $this->logo = sanitize_url($data['logo']);
        }

        if (isset($data['cover_photo'])) {
            $deletePhotos[] = $this->cover_photo;
            $this->cover_photo = sanitize_url($data['cover_photo']);
        }

        if (isset($data['description'])) {
            $this->description = wp_kses_post($data['description']);
        }

        if (isset($data['title'])) {
            $this->title = sanitize_text_field($data['title']);
        }

        if (isset($data['privacy'])) {
            $this->privacy = sanitize_text_field($data['privacy']);
        }

        $deletePhotos = array_filter($deletePhotos);

        if ($removeSrc && $deletePhotos) {
            $deletePhotos = array_filter($deletePhotos);
            do_action('fluent_community/remove_medias_by_url', $deletePhotos, [
                'sub_object_id' => $this->id,
            ]);
        }

        if (isset($data['settings'])) {
            $this->settings = Arr::only(fluentCommunitySanitizeArray($data['settings']), array_keys($this->defaultSettings()));
        }


        $this->save();

        return $this;
    }

    public function getSettingsAttribute($value)
    {
        $settings = Utility::safeUnserialize($value);

        if (!$settings) {
            $settings = [];
        }

        return array_merge($this->defaultSettings(), $settings);
    }

    public function defaultSettings()
    {
        return [
            'hide_members'       => 'no',
            'always_show_spaces' => 'yes'
        ];
    }

    public function setSettingsAttribute($value)
    {
        $this->attributes['settings'] = maybe_serialize($value);
    }

    public function spaces()
    {
        return $this->hasMany(BaseSpace::class, 'parent_id', 'id')
            ->withoutGlobalScopes();
    }

}
