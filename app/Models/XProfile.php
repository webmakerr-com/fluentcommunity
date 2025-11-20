<?php

namespace FluentCommunity\App\Models;


use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\Contact;
use FluentCommunity\App\Models\Feed;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\Modules\Course\Model\Course;
use FluentCommunityPro\App\Models\Follow;
use FluentCrm\App\Models\Subscriber;

/**
 *  FluentCommunity XProfile Model
 *
 *  Database Model
 *
 * @package FluentCommunity\App\Models
 *
 * @version 1.1.0
 */
class XProfile extends Model
{
    protected $table = 'fcom_xprofile';

    protected $guarded = ['id'];

    protected $primaryKey = 'user_id';

    protected $casts = [
        'user_id'      => 'integer',
        'total_points' => 'integer',
        'is_verified'  => 'integer',
    ];

    protected $fillable = [
        'user_id',
        'total_points',
        'username',
        'status',
        'is_verified',
        'display_name',
        'avatar',
        'short_description',
        'last_activity',
        'meta',
        'created_at'
    ];

    protected $searchable = [
        'display_name',
        'username'
    ];

    protected $appends = ['badge'];

    public function scopeSearchBy($query, $search)
    {
        if ($search) {
            $fields = $this->searchable;

            $query->where(function ($q) use ($fields, $search) {
                $q->where(array_shift($fields), 'LIKE', "%$search%");
                foreach ($fields as $field) {
                    $q->orWhere($field, 'LIKE', "%$search%");
                }
            });
        }

        return $query;
    }

    public function scopeMentionBy($query, $search)
    {
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('display_name', 'LIKE', "%$search%")
                    ->orWhere('username', 'LIKE', "%$search%");
            });
        }

        return $query;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'ID');
    }

    public function contact()
    {
        return $this->hasOne(Contact::class, 'user_id', 'user_id');
    }

    public function spaces()
    {
        return $this->belongsToMany(BaseSpace::class, 'fcom_space_user', 'user_id', 'space_id')
            ->withPivot(['role', 'status', 'created_at']);
    }

    public function posts()
    {
        return $this->hasMany(Feed::class, 'user_id', 'user_id');
    }

    // Relationship: Users this user follows
    public function follows()
    {
        return $this->hasMany(Follow::class, 'follower_id', 'user_id');
    }

    // Relationship: Users following this user
    public function followers()
    {
        return $this->hasMany(Follow::class, 'followed_id', 'user_id');
    }

    public function scheduledPosts()
    {
        return $this->posts()->where('status', 'scheduled')->where('scheduled_at', '!=', null);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'user_id', 'user_id');
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'fcom_space_user', 'user_id', 'space_id')
            ->withPivot(['role', 'status', 'created_at']);
    }

    public function space_pivot()
    {
        return $this->belongsTo(SpaceUserPivot::class, 'user_id', 'user_id')->withoutGlobalScopes();
    }

    public function community_role()
    {
        return $this->belongsTo(Meta::class, 'user_id', 'object_id')
            ->where('meta_key', '_user_community_roles');
    }

    /**
     * Accessor to get dynamic photo attribute
     * @return string
     */
    public function getAvatarAttribute()
    {
        $gravatarEnabled = Utility::getPrivacySetting('enable_gravatar') != 'no';

        if (!empty($this->attributes['avatar'])) {
            $url = $this->attributes['avatar'];
            if (!$gravatarEnabled && strpos($url, 'gravatar.com')) {
                $url = apply_filters('fluent_community/default_avatar', FLUENT_COMMUNITY_PLUGIN_URL . 'assets/images/placeholder.png', $this->user_id);
            }

            if (!$url) {
                $url = FLUENT_COMMUNITY_PLUGIN_URL . 'assets/images/placeholder.png';
            }

            return $url;
        }

        if (!$gravatarEnabled) {
            $url = apply_filters('fluent_community/default_avatar', FLUENT_COMMUNITY_PLUGIN_URL . 'assets/images/placeholder.png', $this->user_id);

            if (!$url) {
                $url = FLUENT_COMMUNITY_PLUGIN_URL . 'assets/images/placeholder.png';
            }

            return $url;
        }

        $url = Utility::getFromCache('user_avatar_' . $this->user_id, function () {
            $displayName = $this->display_name;

            if ($displayName) {
                $names = explode(' ', $displayName);
                // take the first letter of each name
                $displayName = '';
                foreach ($names as $name) {
                    $name = (string)$name;
                    $firstLetter = mb_substr($name, 0, 1, 'UTF-8');
                    $displayName .= $firstLetter . '+';
                }
            }

            return get_avatar_url($this->user_id, [
                'size'    => 128,
                'default' => apply_filters('fluent_community/default_avatar', 'https://ui-avatars.com/api/' . esc_attr($displayName) . '/128', $this->user_id)
            ]);
        }, WEEK_IN_SECONDS);

        if (!$url) {
            $url = FLUENT_COMMUNITY_PLUGIN_URL . 'assets/images/placeholder.png';
        }

        return $url;
    }

    public function hasCustomAvatar()
    {
        return !empty($this->attributes['avatar']);
    }

    public function getBadgeAttribute()
    {
        return apply_filters('fluent_community/xprofile/badge', null, $this);
    }

    public function getCrmContact()
    {
        if (!defined('FLUENTCRM')) {
            return null;
        }

        if ($this->user_email) {
            return Subscriber::where('user_id', $this->ID)
                ->orWhere('email', $this->user_email)
                ->first();
        }

        return Subscriber::where('user_id', $this->ID)
            ->first();
    }

    public function getMetaAttribute($value)
    {
        $settings = Utility::safeUnserialize($value);

        if (!$settings) {
            $settings = [
                'cover_photo' => '',
                'website'     => ''
            ];
        }

        return $settings;
    }

    public function setMetaAttribute($value)
    {
        if (!$value) {
            $value = [
                'cover_photo' => '',
                'website'     => ''
            ];
        }

        $this->attributes['meta'] = maybe_serialize($value);
    }

    public function getFirstName()
    {
        if (!$this->display_name) {
            return '';
        }
        $fullName = explode(' ', $this->display_name);
        return $fullName[0];
    }

    public function getLastName()
    {

        if (!$this->display_name) {
            return '';
        }

        $fullName = explode(' ', $this->display_name);

        // remove the first name
        array_shift($fullName);

        if (count($fullName) == 0) {
            return '';
        }
        return implode(' ', $fullName);
    }

    public function getCompletionScore()
    {
        $scores = [
            'first_name'        => 20,
            'last_name'         => 20,
            'website'           => 30,
            'cover_photo'       => 20,
            'avatar'            => 20,
            'short_description' => 30,
            'social_links'      => 20
        ];

        $score = 0;

        if ($this->getFirstName()) {
            $score += $scores['first_name'];
        }

        if ($this->getLastName()) {
            $score += $scores['last_name'];
        }

        $meta = $this->meta;
        if (Arr::get($meta, 'website')) {
            $score += $scores['website'];
        }

        if (Arr::get($meta, 'cover_photo')) {
            $score += $scores['cover_photo'];
        }

        if ($this->short_description) {
            $score += $scores['short_description'];
        }

        if ($score >= 100) {
            return 100;
        }

        if (!empty($meta['social_links']) && array_filter(Arr::get($meta, 'social_links', []))) {
            $score += $scores['social_links'];
        }

        if (!empty($this->attributes['avatar'])) {
            $score += $scores['avatar'];
        }

        return $score > 100 ? 100 : $score;
    }

    public function getPermalink()
    {
        return Helper::baseUrl('u/' . $this->username);
    }

    public function getJsRoute()
    {
        return [
            'name'   => 'user_profile',
            'params' => [
                'username' => $this->username
            ]
        ];
    }
}
