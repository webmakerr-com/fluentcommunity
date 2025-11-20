<?php

namespace FluentCommunity\App\Models;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\App\Services\ProfileHelper;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\Modules\Course\Model\Course;
use FluentCommunityPro\App\Models\Follow;
use FluentCrm\App\Models\Subscriber;

/**
 *  User Model - DB Model for WordPress Users Table
 *
 *  Database Model
 *
 * @package FluentCommunity\App\Models
 *
 * @version 1.0.0
 */
class User extends Model
{
    protected $table = 'users';

    protected $primaryKey = 'ID';

    protected $hidden = ['user_pass', 'user_activation_key'];

    protected $appends = ['photo'];

    public $timestamps = false;

    protected $searchable = [
        'display_name',
        'user_email'
    ];

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

    public function scopeMentionBy($query, $search)
    {
        if ($search) {
            $fields = [
                'display_name',
                'user_login'
            ];
            $query->where(function ($query) use ($fields, $search) {
                $query->where(array_shift($fields), 'LIKE', "$search%");
                foreach ($fields as $field) {
                    $query->orWhere($field, 'LIKE', "$search%");
                }
            });
        }

        return $query;
    }

    /**
     * Accessor to get dynamic photo attribute
     * @return string
     */
    public function getPhotoAttribute()
    {
        if ($photo = get_user_meta($this->ID, '_fcom_user_photo', true)) {
            return $photo;
        }

        if ($contact = $this->getContact()) {
            return $contact->photo;
        }

        if (empty($this->attributes['user_email'])) {
            if (defined('FLUENTCRM')) {
                $contact = Subscriber::where('user_id', $this->ID)->first();
                if ($contact) {
                    return $contact->photo;
                }
            }
            return '';
        }

        if (Utility::getPrivacySetting('enable_gravatar') != 'yes') {
            return apply_filters('fluent_community/default_avatar', FLUENT_COMMUNITY_PLUGIN_URL . 'assets/images/placeholder.png', $this->ID);
        }

        $hash = md5(strtolower(trim($this->attributes['user_email'])));

        /**
         * Gravatar URL by Email
         *
         * @return string $gravatar url of the gravatar image
         */
        $name = $this->attributes['display_name'];

        $fallback = '';
        if ($name) {
            $fallback = '&d=https%3A%2F%2Fui-avatars.com%2Fapi%2F' . urlencode($name) . '/128';
        }

        return apply_filters('fluent_crm/get_avatar',
            "https://www.gravatar.com/avatar/{$hash}?s=128" . $fallback,
            $this->attributes['user_email']
        );
    }

    public function getIsVerifiedAttribute()
    {
        return $this->isVerified();
    }

    public function getContact()
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

    public function xprofile()
    {
        return $this->belongsTo(XProfile::class, 'ID', 'user_id');
    }

    // Relationship: Users this user follows
    public function follows()
    {
        return $this->hasMany(Follow::class, 'follower_id', 'ID');

    }

    // Relationship: Users following this user
    public function followers()
    {
        return $this->hasMany(Follow::class, 'followed_id', 'ID');
    }

    public function usermeta()
    {
        return $this->hasMany(UserMeta::class, 'user_id', 'ID');
    }

    public function messages()
    {
        return $this->hasMany(\FluentMessaging\App\Models\Message::class, 'user_id', 'ID');
    }

    public function getGeneralData()
    {
        $user = get_user_by('ID', $this->ID);

        $fullName = '';
        if ($user->first_name || $user->last_name) {
            $fullName = trim($user->first_name . ' ' . $user->last_name);
        }

        if (!$fullName) {
            $fullName = $user->display_name;
        }

        $contact = $this->getContact();

        return [
            'is_contact'   => (bool)$contact,
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'full_name'    => $fullName,
            'display_name' => $fullName,
            'bio'          => $user->description,
            'website'      => $user->user_url,
            'id'           => $user->ID,
            'user_id'      => $user->ID,
            'created_at'   => $user->user_registered,
            'photo'        => $this->photo,
            'username'     => $this->username,
            'is_verified'  => $this->isVerified()
        ];
    }

    public function spaces()
    {
        return $this->belongsToMany(BaseSpace::class, 'fcom_space_user', 'user_id', 'space_id')
            ->withPivot(['role', 'status', 'created_at']);
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'fcom_space_user', 'user_id', 'space_id')
            ->withPivot(['role', 'created_at']);
    }

    public function notificationSubscriptions()
    {
        return $this->hasMany(NotificationSubscription::class, 'user_id');
    }

    public function space_pivot()
    {
        return $this->belongsTo(SpaceUserPivot::class, 'ID', 'user_id')->withoutGlobalScopes();
    }

    public function notification_records()
    {
        return $this->hasMany(NotificationSubscriber::class, 'user_id', 'ID')->withoutGlobalScopes();
    }

    public function crm_contact()
    {
        return $this->belongsTo(Contact::class, 'ID', 'user_id')->withoutGlobalScopes();
    }

    public function community_role()
    {
        return $this->belongsTo(Meta::class, 'ID', 'object_id')
            ->where('meta_key', '_user_community_roles');
    }

    public function updateCustomData($updateData, $removeSrc = false)
    {
        if (isset($updateData['first_name']) && Utility::getPrivacySetting('enable_user_sync') === 'yes') {
            $firstName = sanitize_text_field($updateData['first_name']);
            $lastName = sanitize_text_field($updateData['last_name']);

            $userData = [
                'first_name'   => $firstName,
                'last_name'    => $lastName,
                'display_name' => trim($firstName . ' ' . $lastName),
                'description'  => isset($updateData['short_description']) ? sanitize_textarea_field($updateData['short_description']) : '',
                'user_url'     => isset($updateData['website']) ? esc_url($updateData['website']) : '',
                'ID'           => $this->ID
            ];

            wp_update_user($userData);

            if ($contact = $this->getContact()) {
                $contact->fill(array_filter([
                    'first_name' => $firstName,
                    'last_name'  => $lastName
                ]));

                $dirtyFields = $contact->getDirty();
                if ($dirtyFields) {
                    $contact->save();
                    do_action('fluent_crm/contact_updated', $contact, $dirtyFields);
                }

            }
        }

        return $this;
    }

    public function getDisplayName()
    {
        $user = get_user_by('ID', $this->ID);
        $name = '';
        if ($user->first_name || $user->last_name) {
            $name = trim($user->first_name . ' ' . $user->last_name);
        }
        if ($name) {
            return $name;
        }

        return $user->display_name;
    }

    public function getCustomMeta($key, $default = null)
    {
        $exist = Meta::where('object_type', 'user')
            ->where('object_id', $this->ID)
            ->where('meta_key', $key)
            ->first();

        if ($exist && $exist->value) {
            return $exist->value;
        }

        return $default;
    }

    public function updateCustomMeta($key, $value)
    {
        $exist = Meta::where('object_type', 'user')
            ->where('object_id', $this->ID)
            ->where('meta_key', $key)
            ->first();

        if ($exist) {
            $exist->value = $value;
            $exist->save();
            return $exist;
        }

        return Meta::create([
            'object_type' => 'user',
            'object_id'   => $this->ID,
            'meta_key'    => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'value'       => $value
        ]);
    }

    public function isNotMemberOfAnySpace()
    {
        return $this->spaces()->count() == 0;
    }

    public function getSpaceIds($cached = true)
    {
        if ($cached) {
            $ids = get_user_meta($this->ID, '_fcom_space_ids', true);
            if (!$ids || !is_array($ids)) {
                $ids = [];
            }
            return $ids;
        }

        $this->cacheAccessSpaces();

        return get_user_meta($this->ID, '_fcom_space_ids', true);
    }

    public function getCommunityRoles()
    {
        if (Helper::isSuperAdmin($this->ID)) {
            return ['admin'];
        }

        return (array)$this->getCustomMeta('_user_community_roles', []);
    }

    public function isCommunityAdmin()
    {
        return in_array('admin', $this->getCommunityRoles());
    }

    public function isCommunityModerator()
    {
        return !!array_intersect(['moderator', 'admin'], $this->getCommunityRoles());
    }

    public function hasCommunityModeratorAccess()
    {
        $permissions = $this->getPermissions(true);

        return Arr::isTrue($permissions, 'community_moderator');
    }

    public function hasCommunityAdminAccess()
    {
        $permissions = $this->getPermissions(true);

        return Arr::isTrue($permissions, 'community_admin');
    }

    public function hasCourseCreatorAccess()
    {
        $permissions = $this->getPermissions(true);

        return Arr::isTrue($permissions, 'course_creator');
    }

    public function isSpaceModerator()
    {
        return $this->hasCourseCreatorAccess() || $this->hasCommunityModeratorAccess();
    }

    public function hasSpaceManageAccess()
    {
        $permissions = array_filter($this->getPermissions(true));

        return !!array_intersect(['community_admin', 'course_admin'], array_keys($permissions));
    }

    public function getSpaceRole($space)
    {
        $globalRoles = $this->getCommunityRoles();

        if ($globalRoles) {
            if (in_array('admin', $globalRoles)) {
                return 'admin';
            }
        }

        if ($space) {
            $spacePivot = SpaceUserPivot::where('space_id', $space->id)
                ->where('user_id', $this->ID)
                ->first();

            if ($spacePivot) {
                if ($spacePivot->role != 'member') {
                    return $spacePivot->role;
                }

                if (!in_array('moderator', $globalRoles)) {
                    if ($spacePivot->status == 'pending') {
                        return 'pending';
                    }
                    return $spacePivot->role;
                }
            }
        }

        if (in_array('moderator', $globalRoles)) {
            return 'moderator';
        }

        return '';
    }

    public function cacheAccessSpaces()
    {
        $globalRoles = $this->getCommunityRoles();

        if (array_intersect($globalRoles, ['admin', 'moderator'])) {
            $spaces = BaseSpace::onlyMain()->get();
        } else {
            $spaces = BaseSpace::onlyMain()->whereHas('members', function ($query) {
                $query->where('user_id', $this->ID)
                    ->where('status', 'active');
            })->orWhere('privacy', 'public')->get();
        }

        $spaceIds = $spaces->pluck('id')->toArray();

        update_user_meta($this->ID, '_fcom_space_ids', $spaceIds);

        return $spaces;
    }

    protected function getRolePermissions($roles)
    {
        if (!$roles) {
            return apply_filters('fluent_community/user/permissions', [
                'read' => true
            ], $roles, $this);
        }

        $isAdmin = in_array('admin', $roles);
        $isModerator = !!array_intersect($roles, ['admin', 'moderator']);

        $permissions = [
            'admin'               => $isAdmin,
            'super_admin'         => Helper::isSuperAdmin(),
            'community_admin'     => $isAdmin,
            'community_moderator' => $isModerator,
            'delete_any_feed'     => $isModerator,
            'edit_any_feed'       => $isModerator,
            'delete_any_comment'  => $isModerator,
            'edit_any_comment'    => $isModerator,
            'read'                => true
        ];

        if ($isAdmin || in_array('course_admin', $roles)) {
            $permissions['course_creator'] = true;
            $permissions['course_admin'] = true;
        } else if (in_array('course_creator', $roles)) {
            $permissions['course_creator'] = true;
        }

        return apply_filters('fluent_community/user/permissions', $permissions, $roles, $this);
    }

    public function getPermissions($cached = true)
    {
        static $permissions;

        if ($permissions && $cached) {
            return $permissions;
        }

        $roles = $this->getCommunityRoles();

        $permissions = $this->getRolePermissions($roles);

        return $permissions;
    }

    public function getSpacePermissions($space)
    {
        if (!$space) {
            return [];
        }

        $role = $this->getSpaceRole($space);

        $hasDocuments = defined('FLUENT_COMMUNITY_PRO') && Arr::get($space->settings, 'document_library') == 'yes';

        $isRestrictedPost = Arr::get($space->settings, 'restricted_post_only') == 'yes';

        $documentAccess = Arr::get($space->settings, 'document_access');

        if (!$role) {
            $permissions = [
                'can_create_post'    => false,
                'registered'         => true,
                'can_comment'        => false,
                'can_view_posts'     => true,
                'can_view_members'   => $space->canViewMembers($this),
                'is_pending'         => false,
                'is_non_member'      => true,
                'can_view_info'      => $space->privacy !== 'secret',
                'can_view_documents' => $hasDocuments && in_array($documentAccess, ['everybody', 'logged_in'])
            ];

            if ($space->privacy === 'secret' || $space->privacy === 'private') {
                $permissions['can_view_posts'] = false;
                $permissions['can_view_members'] = false;
                $permissions['can_view_documents'] = false;
            }
        } else if ($role == 'pending') {
            $permissions = [
                'can_create_post'    => false,
                'registered'         => true,
                'can_view_posts'     => true,
                'can_comment'        => false,
                'can_view_members'   => $space->canViewMembers($this),
                'is_pending'         => true,
                'can_view_info'      => $space->privacy !== 'secret',
                'can_view_documents' => $hasDocuments && in_array($documentAccess, ['everybody', 'logged_in'])
            ];

            if ($space->privacy === 'secret' || $space->privacy === 'private') {
                $permissions['can_view_posts'] = false;
                $permissions['can_view_members'] = false;
                $permissions['can_view_documents'] = false;
            }
        } else if ($role == 'member' || $role == 'student') {
            $permissions = [
                'can_create_post'      => $isRestrictedPost ? false : true,
                'registered'           => true,
                'can_view_posts'       => true,
                'can_view_members'     => $space->canViewMembers($this),
                'can_comment'          => true,
                'can_view_info'        => true,
                'can_view_documents'   => $hasDocuments,
                'can_upload_documents' => $hasDocuments && Arr::get($space->settings, 'document_upload') == 'members_only'
            ];
        } else {
            $isMod = in_array($role, ['admin', 'moderator']);
            $isAdmin = $role === 'admin';
            $permissions = [
                'can_create_post'      => $isRestrictedPost ? $isMod : true,
                'can_view_posts'       => true,
                'can_view_members'     => $isAdmin || $space->canViewMembers($this),
                'registered'           => true,
                'community_admin'      => $isAdmin,
                'community_moderator'  => $isMod,
                'edit_any_feed'        => $isMod,
                'delete_any_feed'      => $isMod,
                'edit_any_comment'     => $isMod,
                'delete_any_comment'   => $isMod,
                'super_admin'          => Helper::isSuperAdmin(),
                'read'                 => true,
                'can_remove_member'    => $isAdmin,
                'can_add_member'       => $isAdmin,
                'can_comment'          => $isMod,
                'can_view_info'        => true,
                'can_view_documents'   => $hasDocuments,
                'can_upload_documents' => $hasDocuments && $isMod
            ];
        }

        $permissions['is_member'] = in_array($role, ['admin', 'moderator', 'member', 'student']);

        return apply_filters('fluent_community/user/space/permissions', $permissions, $space, $role, $this);
    }

    public function hasCommunityPermission($permission)
    {
        $permissions = $this->getPermissions();

        if (isset($permissions[$permission])) {
            return $permissions[$permission];
        }

        return false;
    }

    public function hasSpacePermission($permission, $space)
    {
        $permissions = $this->getSpacePermissions($space);

        if (isset($permissions[$permission])) {
            return $permissions[$permission];
        }

        return false;
    }

    public function verifyCommunityPermission($permission)
    {
        if (!$this->hasCommunityPermission($permission)) {
            throw new \Exception('You do not have permission to do this action');
        }

        return true;
    }

    public function verifySpacePermission($permission, $space)
    {
        if (!$space || !$this->hasSpacePermission($permission, $space)) {
            throw new \Exception('You do not have permission to do this action');
        }

        return true;
    }

    public function canEditFeed($feed, $throwException = false)
    {
        $result = $feed->user_id == $this->ID || $this->hasCommunityPermission('edit_any_feed') || $this->hasSpacePermission('edit_any_feed', $feed->space);

        if (!$result && $throwException) {
            throw new \Exception('You do not have permission to do this action');
        }

        return $result;
    }

    public function canDeleteFeed($feed, $throwException = false)
    {
        $result = $feed->user_id == $this->ID || $this->hasCommunityPermission('delete_any_feed') || $this->hasSpacePermission('delete_any_feed', $feed->space);

        if (!$result && $throwException) {
            throw new \Exception('You do not have permission to do this action');
        }
        return $result;
    }

    public function can($permission, $space = null)
    {
        if ($space) {
            return $this->hasSpacePermission($permission, $space);
        }

        return $this->hasCommunityPermission($permission);
    }

    public function hasPermissionOrInCurrentSpace($permission, $space = null)
    {
        if ($this->hasCommunityPermission($permission)) {
            return true;
        }

        return $space && $this->hasSpacePermission($permission, $space);
    }

    public function getUnreadNotificationCount()
    {
        return NotificationSubscriber::where('user_id', $this->ID)
            ->unread()
            ->count();
    }

    public function getUnreadNotificationFeedIds()
    {
        $ids = Notification::whereHas('subscribers', function ($query) {
            return $query->where('user_id', $this->ID)
                ->unread();
        })->pluck('feed_id')->toArray();

        return array_values(array_unique($ids));
    }

    public function isVerified()
    {
        return get_user_meta($this->ID, '_fcom_is_verified', true) == 'yes';
    }

    public function syncXProfile($force = false, $useUserName = false)
    {
        $exist = XProfile::where('user_id', $this->ID)->first();

        if (($exist && !$force) || ($exist && Utility::getPrivacySetting('enable_user_sync') === 'no')) {
            return $exist;
        }

        $data = [
            'user_id'           => $this->ID,
            'username'          => ProfileHelper::generateUserName($this->ID, $useUserName),
            'display_name'      => $this->getDisplayName(),
            'is_verified'       => $this->isVerified() ? 1 : 0,
            'short_description' => get_user_meta($this->ID, 'description', true),
            'meta'              => [
                'website'     => $this->user_url,
                'cover_photo' => get_user_meta($this->ID, '_fluent_cover_photo', true)
            ]
        ];

        if ($exist) {
            unset($data['avatar']);
            unset($data['username']);

            if (apply_filters('fluent/community/user_wp_user_registered_date', true, $this)) {
                $data['created_at'] = $this->user_registered;
            }

            $data['meta'] = wp_parse_args($exist->meta, $data['meta']);
            $exist->fill($data);
            $exist->save();
            return $exist;
        }

        $counter = 1;
        $initialUserName = $data['username'];
        while (XProfile::where('username', $initialUserName)->first()) {
            $initialUserName = $data['username'] . '_' . $counter;
            $counter++;
        }

        $data['username'] = $initialUserName;
        if (apply_filters('fluent/community/user_wp_user_registered_date', true, $this)) {
            $data['created_at'] = get_date_from_gmt($this->user_registered, 'Y-m-d H:i:s');
        }

        $xprofile = XProfile::create($data);
        $this->load('xprofile');

        return $xprofile;
    }

    public function getUserMeta($metaKey, $default = null)
    {
        return get_user_meta($this->ID, $metaKey, true) ?? $default;
    }

    public function getWpUser()
    {
        return get_user_by('ID', $this->ID);
    }
}
