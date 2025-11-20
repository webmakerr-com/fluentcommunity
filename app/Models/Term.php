<?php

namespace FluentCommunity\App\Models;

use FluentCommunity\App\Functions\Utility;

class Term extends Model
{
    protected $table = 'fcom_terms';

    protected $guarded = ['id'];

    protected $fillable = [
        'parent_id',
        'taxonomy_name',
        'slug',
        'title',
        'description',
        'settings'
    ];

    protected $searchable = [
        'title',
        'description',
        'slug'
    ];

    public static function boot()
    {
        parent::boot();

        static::deleting(function ($term) {
            $term->posts()->detach();
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

    public function posts()
    {
        return $this->belongsToMany(Feed::class, 'fcom_term_feed', 'term_id', 'post_id')
            ->withoutGlobalScopes();
    }

    public function getSettingsAttribute($value)
    {
        $settings = Utility::safeUnserialize($value);

        if (!$settings) {
            $settings = [];
        }

        return $settings;
    }

    public function setSettingsAttribute($value)
    {
        $this->attributes['settings'] = maybe_serialize($value);
    }

    public function base_spaces()
    {
        return $this->belongsToMany(BaseSpace::class, 'fcom_meta', 'object_id', 'meta_key')
            ->wherePivot('object_type', 'term_space_relation')
            ->withoutGlobalScopes();
    }
}
