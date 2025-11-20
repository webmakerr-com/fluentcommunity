<?php

namespace FluentCommunity\App\Http\Controllers;

use FluentCommunity\App\Models\Space;
use FluentCommunity\App\Models\Feed;
use FluentCommunity\App\Models\BaseSpace;
use FluentCommunity\App\Models\SpaceGroup;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Services\CustomSanitizer;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Services\ProfileHelper;
use FluentCommunity\Framework\Http\Request\Request;
use FluentCommunity\App\Models\Comment;
use FluentCommunity\App\Models\Reaction;
use FluentCommunity\App\Models\SpaceUserPivot;
use FluentCommunity\Framework\Support\Arr;

class SpaceController extends Controller
{
    public function get()
    {
        $spaces = Space::orderBy('title', 'ASC')
            ->whereHas('space_pivot', function ($q) {
                $q->where('user_id', get_current_user_id());
            })
            ->get();

        return [
            'spaces' => $spaces
        ];
    }

    public function create(Request $request)
    {
        $currentUser = $this->getUser(true);

        $data = $request->get('space', []);

        $slug = $data['slug'] ?: $data['title'];

        $slug = preg_replace('/[^a-zA-Z0-9-_]/', '', $slug);

        $data['slug'] = sanitize_title($slug, '');
        $data['title'] = sanitize_text_field($data['title']);
        $data['privacy'] = sanitize_text_field($data['privacy']);

        $this->validate($data, [
            'title'   => 'required',
            'slug'    => 'unique:fcom_spaces,slug',
            'privacy' => 'required|in:public,private,secret'
        ]);

        $spaceGroup = null;
        if (!empty($data['parent_id'])) {
            $spaceGroup = SpaceGroup::findOrFail($data['parent_id']);
            $serial = BaseSpace::query()->withoutGlobalScopes()->where('parent_id', $spaceGroup->id)->max('serial') + 1;
        } else {
            $serial = BaseSpace::query()->withoutGlobalScopes()->max('serial') + 1;
        }

        $settings = CustomSanitizer::santizeSpaceSettings(Arr::get($data, 'settings', []), $data['privacy']);

        if (is_wp_error($settings)) {
            return $this->sendError([
                'message' => $settings->get_error_message()
            ]);
        }

        $spaceData = apply_filters('fluent_community/space/create_data', [
            'title'       => sanitize_text_field($data['title']),
            'slug'        => $data['slug'],
            'privacy'     => $data['privacy'],
            'description' => sanitize_textarea_field($data['description']),
            'settings'    => $settings,
            'parent_id'   => $spaceGroup ? $spaceGroup->id : null,
            'serial'      => $serial ?: 1
        ]);

        $ogImage = Arr::get($data, 'settings.og_image', '');
        $ogMedia = null;
        if ($ogImage) {
            $ogMedia = Helper::getMediaFromUrl($ogImage);
            if ($ogMedia && !$ogMedia->is_active) {
                $spaceData['settings']['og_image'] = $ogMedia->public_url;
            }
        }

        $space = Space::create($spaceData);
        if ($ogMedia && !$ogMedia->is_active) {
            $ogMedia->update([
                'is_active'     => true,
                'user_id'       => get_current_user_id(),
                'sub_object_id' => $space->id,
                'object_source' => 'space_og_image'
            ]);
        }

        $imageTypes = ['cover_photo', 'logo'];
        $metaData = [];
        foreach ($imageTypes as $type) {
            if (!empty($data[$type])) {
                $media = Helper::getMediaFromUrl($data[$type]);
                if (!$media || $media->is_active) {
                    continue;
                }
                $metaData[$type] = $media->public_url;
                $media->update([
                    'is_active'     => true,
                    'user_id'       => get_current_user_id(),
                    'sub_object_id' => $space->id,
                    'object_source' => 'space_' . $type
                ]);
            }
        }

        if ($metaData) {
            $space->updateCustomData($metaData, false);
        }

        $space->members()->attach(get_current_user_id(), [
            'role' => 'admin'
        ]);

        if (Arr::has($data, 'topic_ids')) {
            $topicsConfig = Helper::getTopicsConfig();
            $topicIds = (array)Arr::get($data, 'topic_ids', []);
            $topicIds = array_slice($topicIds, 0, $topicsConfig['max_topics_per_space']);
            $space->syncTopics($topicIds);
        }

        $currentUser->cacheAccessSpaces();
        do_action('fluent_community/space/created', $space, $data);

        return [
            'message' => __('Space has been created successfully', 'fluent-community'),
            'space'   => $space
        ];
    }

    public function discover(Request $request)
    {
        $start = microtime(true);
        $currentUser = $this->getUser();
        $type = $request->getSafe('type');
        $search = $request->getSafe('search');
        $sortBy = $request->getSafe('sort_by', 'sanitize_text_field', 'alphabetical');

        $spaces = Space::with(['space_pivot' => function ($q) {
            $q->where('user_id', get_current_user_id());
        }])
            ->where(function ($q) {
                $q->whereHas('space_pivot', function ($q) {
                    $q->where('user_id', get_current_user_id());
                })
                    ->orWhereIn('privacy', ['public', 'private']);
            })
            ->when($type == 'joined', function ($q) {
                $q->whereHas('space_pivot', function ($q) {
                    $q->where('user_id', get_current_user_id());
                });
            })
            ->when($search, function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%');
            })
            ->when($sortBy == 'alphabetical', function ($q) {
                $q->orderBy('title', 'ASC');
            })
            ->when($sortBy == 'oldest', function ($q) {
                $q->orderBy('created_at', 'ASC');
            })
            ->when($sortBy == 'latest', function ($q) {
                $q->orderBy('created_at', 'DESC');
            })
            ->paginate();

        foreach ($spaces as $space) {
            $shouldHideMembersCount = Arr::get($space->settings, 'hide_members_count') == 'yes';
            $canViewMembers = $currentUser && $space->verifyUserPermisson($currentUser, 'can_view_members', false);

            if ($shouldHideMembersCount && !$canViewMembers) {
                $space->members_count = 0;
                continue;
            }

            $space->members_count = SpaceUserPivot::where('space_id', $space->id)->where('status', 'active')
                ->whereHas('xprofile', function ($q) {
                    $q->where('status', 'active');
                })
                ->whereHas('user')
                ->count();
        }

        return [
            'spaces'         => $spaces,
            'execution_time' => microtime(true) - $start
        ];
    }

    public function getBySlug(Request $request, $spaceSlug)
    {
        $user = $this->getUser();
        $space = Space::where('slug', $spaceSlug)
            ->firstOrFail();

        $space = $space->formatSpaceData($user);

        if ($space->privacy == 'secret' && !$space->membership) {
            return $this->sendError([
                'message'    => __('You are not allowed to view this space', 'fluent-community'),
                'error_type' => 'restricted'
            ]);
        }

        do_action_ref_array('fluent_community/space', [&$space]);

        return [
            'space' => $space
        ];
    }

    public function patchBySlug(Request $request, $slug)
    {
        $space = Space::where('slug', $slug)
            ->first();

        if (!$space) {
            return $this->sendError([
                'message' => __('Space not found', 'fluent-community')
            ]);
        }

        $data = $request->get('data', []);

        if (!empty($data['slug'])) {
            $taken = Space::where('slug', $data['slug'])
                ->where('id', '!=', $space->id)
                ->first();

            if ($taken) {
                return $this->sendError([
                    'message' => __('Slug is already taken. Please use a different slug', 'fluent-community')
                ]);
            }
        }

        $mediaTypes = ['cover_photo', 'logo'];
        foreach ($mediaTypes as $type) {
            if (!empty($data[$type])) {
                $media = Helper::getMediaFromUrl($data[$type]);
                if (!$media || $media->is_active) {
                    unset($data[$type]);
                    continue;
                }

                $data[$type] = $media->public_url;

                $media->update([
                    'is_active'     => true,
                    'user_id'       => get_current_user_id(),
                    'sub_object_id' => $space->id,
                    'object_source' => 'space_' . $type
                ]);
            } else if (isset($data[$type])) {
                $data[$type] = '';
            }
        }

        $data = apply_filters('fluent_community/space/update_data', $data, $space);

        if (isset($data['parent_id']) && !$data['parent_id']) {
            $data['parent_id'] = '';
        }

        $space = $space->updateCustomData($data, true);

        if (is_wp_error($space)) {
            return $this->sendError([
                'message' => $space->get_error_message()
            ]);
        }

        if (Arr::has($data, 'topic_ids')) {
            $topicsConfig = Helper::getTopicsConfig();
            $topicIds = (array)Arr::get($data, 'topic_ids', []);
            $topicIds = array_slice($topicIds, 0, $topicsConfig['max_topics_per_space']);
            $space->syncTopics($topicIds);
        }

        do_action('fluent_community/space/updated', $space, $data);
        $slugUpdated = $slug != $space->slug;

        $metaSettings = $request->get('meta_settings', []);
        if($metaSettings) {
            foreach ($metaSettings as $metaProvider => $metaData) {
                do_action('fluent_community/space/update_meta_settings_'.$metaProvider, $metaData, $space);
            }
        }

        return [
            'message'      => __('Settings has been updated', 'fluent-community'),
            'redirect_url' => $slugUpdated ? $space->getPermalink() : ''
        ];
    }

    public function patchById(Request $request, $id)
    {
        $space = Space::findOrFail($id);

        return $this->patchBySlug($request, $space->slug);
    }

    public function getMembers(Request $request, $slug)
    {
        $space = Space::where('slug', $slug)
            ->firstOrFail();

        $user = $this->getUser();

        if (!$space->verifyUserPermisson($user, 'can_view_members', false)) {
            return $this->sendError([
                'message'           => __('You are not allowed to view members of this space', 'fluent-community'),
                'permission_failed' => true
            ]);
        }

        $search = $request->getSafe('search', 'sanitize_text_field');

        $pendingCount = 0;
        if ($user && $user->can('can_add_member', $space)) {
            $pendingCount = SpaceUserPivot::bySpace($space->id)
                ->where('status', 'pending')
                ->count();

            if ($request->get('status') == 'pending') {
                $pendingRequests = SpaceUserPivot::bySpace($space->id)
                    ->whereHas('xprofile', function ($q) use ($search) {
                        return $q->searchBy($search)
                            ->where('status', 'active');
                    })
                    ->with(['xprofile' => function ($q) {
                        $q->select(ProfileHelper::getXProfilePublicFields());
                    }])
                    ->where('status', 'pending')
                    ->paginate();

                return [
                    'members'       => $pendingRequests,
                    'pending_count' => $pendingCount
                ];
            }
        }

        $spaceMembers = SpaceUserPivot::bySpace($space->id)
            ->whereHas('xprofile', function ($q) use ($search) {
                return $q->searchBy($search)
                    ->where('status', 'active');
            })
            ->with(['xprofile' => function ($q) {
                $q->select(ProfileHelper::getXProfilePublicFields());
            }])
            ->where('status', 'active')
            ->orderBy('created_at', 'ASC')
            ->paginate();
        
        return apply_filters('fluent_community/space_members_api_response', [
            'members'       => $spaceMembers,
            'pending_count' => $pendingCount
        ], $spaceMembers, $request->all());
    }

    public function join(Request $request, $slug)
    {
        $space = Space::where('slug', $slug)->first();

        if (!$space) {
            return $this->sendError([
                'message' => __('Space not found', 'fluent-community')
            ]);
        }

        $user = $this->getUser();

        $membership = $space->getMembership(get_current_user_id());

        if ($membership) {
            return $this->sendError([
                'message' => __('You are already a member of this space. Please reload this page', 'fluent-community')
            ]);
        }

        $roles = $user->getCommunityRoles();

        if (!$roles && $space->privacy == 'secret') {
            return $this->sendError([
                'message' => __('You are not allowed to join this space', 'fluent-community')
            ]);
        }

        $status = 'active';
        if (!$roles) {
            if ($space->privacy != 'public') {
                $status = apply_filters('fluent_community/space/join_status_for_private', 'pending', $space, $user);

                if (!in_array($status, ['pending', 'active'])) {
                    $status = 'pending';
                }
            }
            $role = 'member';
        } else {
            $role = $user->isCommunityAdmin() ? 'admin' : 'moderator';
        }


        $space->members()->attach(get_current_user_id(), [
            'role'   => $role,
            'status' => $status
        ]);

        $space->membership = $space->getMembership(get_current_user_id());

        if ($status == 'pending') {
            do_action('fluent_community/space/join_requested', $space, $user->ID, 'self');
        } else {
            do_action('fluent_community/space/joined', $space, $user->ID, 'self');
        }

        $user->cacheAccessSpaces();

        return [
            'message'    => ($status == 'active') ? __('You have joined this Space', 'fluent-community') : __('Your join request has been sent to the Space admin.', 'fluent-community'),
            'membership' => $space->membership
        ];
    }

    public function leave(Request $request, $slug)
    {
        $user = $this->getUser(true);
        $space = Space::where('slug', $slug)->first();

        if (!$space) {
            return $this->sendError([
                'message' => __('Space not found', 'fluent-community')
            ]);
        }

        $membership = $space->getMembership($user->ID);

        if (!$membership) {
            return $this->sendError([
                'message' => __('You are not a member of this community', 'fluent-community')
            ]);
        }

        Helper::removeFromSpace($space, $user->ID, 'self');

        return [
            'message' => __('You have left this space', 'fluent-community')
        ];
    }

    public function deleteBySlug(Request $request, $slug)
    {
        $space = Space::where('slug', $slug)->first();

        if (!$space) {
            return $this->sendError([
                'message' => __('Space not found', 'fluent-community')
            ]);
        }

        do_action('fluent_community/space/before_delete', $space);

        Comment::whereHas('post', function ($q) use ($space) {
            $q->where('space_id', $space->id);
        })->delete();

        Reaction::whereHas('feed', function ($q) use ($space) {
            $q->where('space_id', $space->id);
        })->delete();

        Feed::where('space_id', $space->id)->delete();

        SpaceUserPivot::where('space_id', $space->id)->delete();

        $spaceId = $space->id;
        $space->delete();

        do_action('fluent_community/space/deleted', $spaceId);

        return [
            'message' => __('Space has been deleted successfully', 'fluent-community')
        ];
    }

    public function deleteById(Request $request, $id)
    {
        $space = Space::findOrFail($id);

        return $this->deleteBySlug($request, $space->slug);
    }

    public function addMember(Request $request, $slug)
    {
        $space = Space::where('slug', $slug)->first();

        if (!$space) {
            return $this->sendError([
                'message' => __('Space not found', 'fluent-community')
            ]);
        }

        $this->validate($request->all(), [
            'user_id' => 'required|exists:users,ID'
        ]);

        $userId = $request->get('user_id');
        $targetUser = User::findOrFail($userId);
        $targetUser->syncXProfile();
        $xprofile = $targetUser->xprofile;

        if ($xprofile && $xprofile->status != 'active') {
            return $this->sendError([
                'message' => __('Selected user is not active', 'fluent-community')
            ]);
        }

        $pivot = SpaceUserPivot::bySpace($space->id)
            ->byUser($userId)
            ->first();

        $role = $request->get('role', 'member');

        if ($pivot) {
            if ($pivot->status == 'active') {
                if ($role != $pivot->role) {
                    $pivot->role = $role;
                    $pivot->save();

                    do_action('fluent_community/space/member/role_updated', $space, $pivot);

                    return [
                        'message' => __('Member role updated', 'fluent-community')
                    ];
                }

                return $this->sendError([
                    'message' => __('Selected user is already a member of this community', 'fluent-community')
                ]);
            }

            $pivot->status = 'active';
            $pivot->save();
            do_action('fluent_community/space/joined', $space, $userId, 'by_admin');

            if ($role != 'member') {
                do_action('fluent_community/space/member/role_updated', $space, $pivot);
            }

            return [
                'message' => __('Member approved', 'fluent-community')
            ];
        }

        $space->members()->attach($userId, [
            'role'   => $role,
            'status' => 'active'
        ]);

        $targetUser->cacheAccessSpaces();

        do_action('fluent_community/space/joined', $space, $userId, 'by_admin');

        return [
            'message' => __('User has been added to this Space', 'fluent-community')
        ];
    }

    public function removeMember(Request $request, $slug)
    {
        $space = Space::where('slug', $slug)->first();

        if (!$space) {
            return $this->sendError([
                'message' => __('Space not found', 'fluent-community')
            ]);
        }

        $userId = $request->get('user_id');

        $pivot = SpaceUserPivot::bySpace($space->id)
            ->byUser($userId)
            ->first();

        if (!$pivot) {
            return $this->sendError([
                'message' => __('Selected user is not a member of this community', 'fluent-community')
            ]);
        }

        $pivot->delete();

        $targetUser = User::find($userId);

        if ($targetUser) {
            $targetUser->cacheAccessSpaces();
        }

        do_action('fluent_community/space/user_left', $space, $userId, 'by_admin');

        return [
            'message' => __('User has been removed from this community', 'fluent-community')
        ];
    }

    public function getOtherUsers(Request $request)
    {
        $currentUser = $this->getUser(true);

        $this->validate($request->all(), [
            'space_id' => 'required|exists:fcom_spaces,id'
        ]);

        $isMod = $currentUser->isCommunityModerator() && current_user_can('list_users');

        $spaceId = $request->get('space_id');
        $space = Space::findOrFail($spaceId);

        if (!$space->verifyUserPermisson($currentUser, 'can_add_member', false)) {
            return $this->sendError([
                'message' => __('You are not allowed to add members to this space', 'fluent-community')
            ]);
        }

        $selects = ['ID', 'display_name'];
        if ($isMod) {
            $selects[] = 'user_email';
        }

        $userQuery = User::select(['ID'])
            ->whereDoesntHave('space_pivot', function ($q) use ($space) {
                $q->where('space_id', $space->id);
            })
            ->limit(100)
            ->searchBy($request->getSafe('search'));

        if (is_multisite()) {
            global $wpdb;
            $blogId = get_current_blog_id();
            $blogPrefix = $wpdb->get_blog_prefix($blogId);
            $userQuery->whereHas('usermeta', function ($q) use ($blogPrefix) {
                $q->where('meta_key', $blogPrefix . 'capabilities');
            });
        }

        $userIds = $userQuery->get()
            ->pluck('ID')
            ->toArray();

        $users = User::select($selects)
            ->whereIn('ID', $userIds)
            ->paginate(100);

        return [
            'users' => $users
        ];
    }

    public function updateLinks(Request $request, $slug)
    {
        $space = Space::where('slug', $slug)->first();

        if (!$space) {
            return $this->sendError([
                'message' => __('Space not found', 'fluent-community')
            ]);
        }

        $links = $request->get('links', []);

        $links = array_map(function ($link) {
            return CustomSanitizer::santizeLinkItem($link);
        }, $links);

        $settings = $space->settings;
        $settings['links'] = $links;
        $space->settings = $settings;
        $space->save();

        return [
            'message' => __('Links have been updated for the space', 'fluent-community'),
            'links'   => $links
        ];
    }

    public function getSpaceGroups(Request $request)
    {
        if ($request->get('options_only')) {
            $groups = SpaceGroup::orderBy('serial', 'ASC')
                ->select(['id', 'title'])
                ->get();

            return [
                'groups' => $groups
            ];
        }

        $user = $this->getUser();

        $groups = Helper::getAllCommunityGroups($user, false);

        foreach ($groups as $group) {
            foreach ($group->spaces as $space) {
                if ($space->type === 'community') {
                    $space = $space->formatSpaceData($user);
                } else {
                    $space->permalink = $space->getPermalink();
                    $space->topics = Utility::getTopicsBySpaceId($space->id);
                }
            }
        }

        $orphanedSpaces = BaseSpace::withoutGlobalScopes()
            ->whereNull('parent_id')
            ->whereIn('type', ['community', 'course'])
            ->orderBy('title', 'ASC')
            ->get();

        foreach ($orphanedSpaces as $space) {
            if ($space->type === 'community') {
                $space = $space->formatSpaceData($user);
            } else {
                $space->permalink = $space->getPermalink();
                $space->topics = Utility::getTopicsBySpaceId($space->id);
            }
        }

        return [
            'groups'          => $groups,
            'orphaned_spaces' => $orphanedSpaces
        ];
    }

    public function createSpaceGroup(Request $request)
    {
        $data = $request->all();

        $this->validate($data, [
            'title' => 'required|unique:fcom_spaces,title',
            'slug'  => 'required|unique:fcom_spaces,slug'
        ]);


        $formattedData = [
            'title'       => sanitize_text_field($data['title']),
            'slug'        => sanitize_title($data['slug']),
            'description' => sanitize_textarea_field($data['description']),
            'status'      => 'active',
            'type'        => 'space_group',
            'settings'    => [
                'always_show_spaces' => Arr::get($data, 'settings.always_show_spaces', 'yes'),
            ],
            'serial'      => SpaceGroup::max('serial') + 1
        ];

        $group = SpaceGroup::create($formattedData);

        return [
            'message' => __('Space group has been created successfully', 'fluent-community'),
            'group'   => $group
        ];
    }

    public function updateSpaceGroup(Request $request, $groupId)
    {
        $group = SpaceGroup::findOrFail($groupId);
        $data = $request->all();

        $this->validate($data, [
            'title' => 'required'
        ]);

        $taken = BaseSpace::where('title', $data['title'])
            ->where('id', '!=', $group->id)
            ->first();

        if ($taken) {
            return $this->sendError([
                'message' => __('The title is already taken. Please use a different title', 'fluent-community')
            ]);
        }

        $formattedData = [
            'title'       => sanitize_text_field($data['title']),
            'description' => sanitize_textarea_field($data['description']),
            'status'      => 'active',
            'type'        => 'space_group',
            'settings'    => [
                'always_show_spaces' => Arr::get($data, 'settings.always_show_spaces', 'yes'),
            ]
        ];

        $group->fill($formattedData)->save();

        return [
            'message' => __('Space group has been updated', 'fluent-community'),
            'group'   => $group
        ];
    }

    public function deleteSpaceGroup(Request $request, $groupId)
    {
        $group = SpaceGroup::findOrFail($groupId);

        if (!$group->spaces->isEmpty()) {
            return $this->sendError([
                'message' => __('You can not delete this group. It has spaces', 'fluent-community')
            ]);
        }

        $group->delete();

        return [
            'message' => __('Space group has been deleted successfully', 'fluent-community')
        ];
    }

    public function updateSpaceGroupIndexes(Request $request)
    {
        $indexes = $request->get('indexes', []);

        foreach ($indexes as $groupId => $indexNumber) {
            $group = SpaceGroup::findOrFail($groupId);
            $group->update([
                'serial' => $indexNumber + 1
            ]);
        }

        return [
            'message' => __('Space group indexes have been updated.', 'fluent-community')
        ];

    }

    public function updateSpaceIndexes(Request $request)
    {
        $indexes = $request->get('indexes', []);

        foreach ($indexes as $index => $spaceId) {
            $space = BaseSpace::withoutGlobalScopes()->findOrFail($spaceId);
            $space->update([
                'serial' => $index + 1
            ]);
        }

        return [
            'message' => __('Space indexes have been updated.', 'fluent-community')
        ];
    }

    public function moveSpace(Request $request)
    {
        $spaceId = $request->getSafe('space_id', 'intval');
        $groupId = $request->getSafe('group_id', 'intval');

        $space = BaseSpace::withoutGlobalScopes()->findOrFail($spaceId);
        $group = SpaceGroup::findOrFail($groupId);

        $space->update([
            'parent_id' => $group->id
        ]);

        return [
            'message' => __('Space has been moved successfully', 'fluent-community')
        ];
    }

    public function getLockScreenSettings(Request $request, $spaceSlug)
    {
        $space = Space::where('slug', $spaceSlug)->firstOrFail();
        $lockscreen = $space->getLockscreen();

        $lockscreen = apply_filters('fluent_community/get_lockscreen_settings', $lockscreen, $space);

        return [
            'lockscreen' => $lockscreen
        ];
    }

    public function getMetaSettings(Request $request, $spaceSlug)
    {
        $space = Space::where('slug', $spaceSlug)->firstOrFail();
        $metaSettings = apply_filters('fluent_community/space/meta_fields', [], $space);

        if (!$metaSettings) {
            return [
                'meta_settings' => null
            ];
        }

        return [
            'meta_settings' => $metaSettings
        ];
    }
}
