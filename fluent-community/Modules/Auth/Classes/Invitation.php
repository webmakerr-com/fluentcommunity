<?php

namespace FluentCommunity\Modules\Auth\Classes;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\Model;
use FluentCommunity\App\Models\BaseSpace;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Support\Arr;

class Invitation extends Model
{
    protected $table = 'fcom_post_comments';

    protected $guarded = ['id'];

    protected $fillable = [
        'user_id',
        'post_id',
        'message',
        'message_rendered',
        'meta',
        'type',
        'reactions_count',
        'status',
        'created_at',
        'updated_at'
    ];

    protected $searchable = [
        'message'
    ];

    protected $hidden = [
        'parent_id',
        'content_type',
        'is_sticky'
    ];

    public static function boot()
    {
        parent::boot();

        static::addGlobalScope('type', function ($builder) {
            $builder->where('type', 'invitation');
        });

        static::creating(function ($model) {
            if (empty($model->user_id)) {
                $model->user_id = get_current_user_id();
            }
            $model->type = 'invitation';
        });
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
        return $this->belongsTo(BaseSpace::class, 'post_id', 'id');
    }

    public function setMetaAttribute($value)
    {
        $this->attributes['meta'] = maybe_serialize($value);
    }

    public function getMetaAttribute($value)
    {
        $meta = Utility::safeUnserialize($value);

        if (!$meta) {
            $meta = [
                'invitee_name' => '',
            ];
        }

        return $meta;
    }

    public static function getPendingInvitations($contentType, $spaceId, $user)
    {
        $query = self::where('status', 'pending')
            ->where('content_type', $contentType);

        if ($spaceId) {
            $spaceRole = $user->getSpaceRole(BaseSpace::findOrFail($spaceId));
            $query->where('post_id', $spaceId);

            if (!in_array($spaceRole, ['admin', 'moderator'])) {
                $query->where('user_id', $user->ID);
            }
        } elseif (!$user->isCommunityModerator()) {
            $query->where('user_id', $user->ID);
        }

        return $query->orderBy('updated_at', 'desc')->groupBy('message');
    }

    public static function findExistingInvitation($email, $contentType, $spaceId = null)
    {
        $query = self::where('message', $email)
            ->where('content_type', $contentType)
            ->where('status', 'pending');

        if ($spaceId) {
            $query->where('post_id', $spaceId);
        }

        return $query->first();
    }

    public static function createNewInvitation($data)
    {
        $inviteeId = get_current_user_id();

        return self::create([
            'user_id'          => $inviteeId,
            'post_id'          => Arr::get($data, 'space_id'),
            'message'          => $data['email'],
            'message_rendered' => $data['token'],
            'meta'             => ['role' => $data['role']],
            'type'             => 'invitation',
            'content_type'     => $data['content_type'],
            'status'           => 'pending',
        ]);
    }

    public static function getTodayInvitationCount($userId)
    {
        return self::where('user_id', $userId)
            ->whereDate('created_at', (new \DateTime())->format('Y-m-d'))
            ->count();
    }

    public function getAccessUrl()
    {
        return add_query_arg([
            'fcom_action'      => 'auth',
            'invitation_token' => $this->message_rendered
        ], Helper::baseUrl('/'));
    }

    public function isValid()
    {
        if ($this->message) {
            return $this->status == 'pending';
        }

        if ($this->status != 'active') {
            return false;
        }

        $meta = $this->meta;

        $expireDate = Arr::get($meta, 'expire_date', '');
        $limit = Arr::get($meta, 'limit', 0);

        if ($expireDate && strtotime($expireDate) < strtotime(gmdate('Y-m-d', current_time('timestamp')))) {
            $this->status = 'expired';
            $this->save();
            return false;
        }

        if ($limit && $this->reactions_count >= $limit) {
            $this->status = 'expired';
            $this->save();
            return false;
        }

        return true;
    }
}
