<?php

namespace FluentCommunityPro\App\Modules\Webhooks;

use FluentCommunity\App\Models\Meta;
use FluentCommunity\App\Services\Helper;

class WebhookModel extends Meta
{

    protected $appends = ['webhook_url'];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->object_type = 'webhook';
            $model->meta_key = $model->generateUniqueSlug();
        });

        static::addGlobalScope('object_type', function ($builder) {
            $builder->where('object_type', 'webhook');
        });
    }

    public function scopeSearchBy($query, $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where('meta_key', 'like', "%{$search}%")
            ->orWhere('value', 'like', "%{$search}%");
    }

    public function generateUniqueSlug()
    {
        $slug = md5(wp_generate_uuid4() . '_' . time() . '_' . mt_rand(1, 1000));

        if (self::where('meta_key', $slug)->exists()) {
            return $this->generateUniqueSlug();
        }

        return $slug;
    }

    public function getWebhookUrlAttribute()
    {
        return Helper::baseUrl('?fcom_action=incoming_webhook&webhook=' . $this->meta_key);
    }
}
