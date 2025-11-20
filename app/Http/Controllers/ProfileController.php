<?php

namespace FluentCommunity\App\Http\Controllers;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\Comment;
use FluentCommunity\App\Models\NotificationSubscription;
use FluentCommunity\App\Models\Space;
use FluentCommunity\App\Models\SpaceGroup;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\App\Services\CustomSanitizer;
use FluentCommunity\App\Services\FeedsHelper;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\App\Services\NotificationPref;
use FluentCommunity\App\Services\ProfileHelper;
use FluentCommunity\Framework\Http\Request\Request;
use FluentCommunity\Framework\Support\Arr;

class ProfileController extends Controller
{
    public function getProfile(Request $request, $userName)
    {
        $xprofile = XProfile::where('username', $userName)->firstOrFail();

        if ($xprofile->status != 'active' && !Helper::isModerator()) {
            return $this->sendError([
                'message' => __('This profile is not active', 'fluent-community'),
                'status'  => 403
            ]);
        }

        $user = get_user_by('ID', $xprofile->user_id);

        $profile = [
            'user_id'                    => $xprofile->user_id,
            'is_verified'                => $xprofile->is_verified,
            'display_name'               => $xprofile->display_name,
            'username'                   => $xprofile->username,
            'avatar'                     => $xprofile->avatar,
            'created_at'                 => $xprofile->created_at->format('Y-m-d H:i:s'),
            'short_description_rendered' => wp_kses_post(FeedsHelper::mdToHtml($xprofile->short_description)),
            'cover_photo'                => Arr::get($xprofile->meta, 'cover_photo'),
            'website'                    => Arr::get($xprofile->meta, 'website'),
            'social_links'               => (object)Arr::get($xprofile->meta, 'social_links', []),
            'status'                     => $xprofile->status,
            'badge_slugs'                => (array)Arr::get($xprofile->meta, 'badge_slug', []),
            'compilation_score'          => $xprofile->getCompletionScore(),
            'total_points'               => $xprofile->total_points,
            'canViewUserSpaces'          => ProfileHelper::canViewUserSpaces($xprofile->user_id, $this->getUser())
        ];

        if (Utility::getPrivacySetting('show_last_activity') === 'yes' || Helper::isModerator()) {
            $profile['last_activity'] = $xprofile->last_activity;
        }

        $currentUserId = get_current_user_id();

        $isOwn = $xprofile->user_id == $currentUserId;

        $isAdmin = Helper::isSiteAdmin($currentUserId);

        if ($isOwn || $isAdmin) {
            $profile['email'] = $user->user_email;
            $profile['first_name'] = $user->first_name;
            $profile['last_name'] = $user->last_name;
            $profile['short_description'] = $xprofile->short_description;
            $profile['can_change_username'] = $isAdmin || Utility::getPrivacySetting('can_customize_username') === 'yes';
            $profile['can_change_email'] = current_user_can('edit_users') || (Utility::getPrivacySetting('can_change_email') === 'yes' && $isOwn);
        }

        $profileBaseUrl = Helper::baseUrl('u/' . $xprofile->username . '/');

        $profile['profile_navs'] = [
            [
                'slug'          => 'user_profile',
                'title'         => __('About', 'fluent-community'),
                'url'           => $profileBaseUrl,
                'wrapper_class' => 'fcom_profile_about',
                'route'         => [
                    'name' => 'user_profile'
                ]
            ],
            [
                'slug'          => 'user_profile_feeds',
                'title'         => __('Posts', 'fluent-community'),
                'wrapper_class' => 'fcom_profile_posts',
                'url'           => $profileBaseUrl . 'posts',
                'route'         => [
                    'name' => 'user_profile_feeds'
                ]
            ]
        ];

        if ($profile['canViewUserSpaces']) {
            $profile['profile_navs'][] = [
                'slug'          => 'user_spaces',
                'wrapper_class' => 'fcom_profile_spaces',
                'title'         => __('Spaces', 'fluent-community'),
                'url'           => $profileBaseUrl . 'spaces',
                'route'         => [
                    'name' => 'user_spaces'
                ]
            ];
        }

        $profile['profile_navs'][] = [
            'slug'          => 'user_comments',
            'wrapper_class' => 'fcom_profile_comments',
            'title'         => __('Comments', 'fluent-community'),
            'url'           => $profileBaseUrl . 'comments',
            'route'         => [
                'name' => 'user_comments'
            ]
        ];

        $profile['profile_nav_actions'] = [];

        $profile = apply_filters('fluent_community/profile_view_data', $profile, $xprofile);

        return [
            'profile' => $profile
        ];
    }

    public function patchProfile(Request $request, $userName)
    {
        $xprofile = $this->verfifyAndGetProfile($userName);

        $updateData = $request->get('data', []);

        if (!empty($updateData['status']) && $updateData['status'] === 'deactivated' && $xprofile->status === 'active') {
            // handle deactivation
            $canDeactivate = Utility::getPrivacySetting('can_deactive_account') === 'yes' || Helper::isSiteAdmin();
            if (!$canDeactivate) {
                return $this->sendError([
                    'message' => __('You are not allowed to deactivate this account.', 'fluent-community')
                ]);
            }

            $xprofile->status = '';
            $xprofile->save();
            update_user_meta($userName, '_fcom_deactivated_at', current_time('mysql'));
            do_action('fluent_community/profile_deactivated', $xprofile);

            return [
                'message' => __('Your profile has been deactivated successfully.', 'fluent-community')
            ];
        }

        $mediaTypes = ['cover_photo', 'avatar'];

        foreach ($mediaTypes as $type) {
            if (!empty($updateData[$type])) {
                $media = Helper::getMediaFromUrl($updateData[$type]);
                if (!$media || $media->is_active) {
                    return $this->sendError([
                        'message' => __('Invalid media image. Please upload a new one.', 'fluent-community')
                    ]);
                }

                $updateData[$type] = $media->public_url;

                $media->update([
                    'is_active'     => true,
                    'user_id'       => $xprofile->user_id,
                    'object_source' => 'user_' . $type
                ]);
            }
        }

        $deletedMedias = [];

        if (!empty($updateData['avatar'])) {

            $deletedMedias[] = $xprofile->avatar;

            $xprofile->avatar = $updateData['avatar'];

            if (defined('FLUENTCRM')) {
                $contact = $xprofile->contact;

                if ($contact) {
                    $contact->update([
                        'avatar' => $updateData['avatar']
                    ]);
                }
            }

        }

        if (isset($updateData['cover_photo'])) {
            $deletedMedias[] = Arr::get($xprofile->meta, 'cover_photo');
            $xprofile->meta = wp_parse_args(['cover_photo' => $updateData['cover_photo']], $xprofile->meta);
        }

        $xprofile->save();

        if ($deletedMedias = array_filter($deletedMedias)) {
            do_action('fluent_community/remove_medias_by_url', $deletedMedias, [
                'user_id'        => $xprofile->user_id,
                'object_sources' => ['user_avatar', 'user_cover_photo']
            ]);
        }

        return [
            'message' => __('Profile updated', 'fluent-community')
        ];
    }

    public function updateProfile(Request $request, $userName)
    {
        $currentUser = $this->getUser(true);
        $data = $request->get('data', []);

        if ($currentUser->isCommunityModerator()) {
            $xProfile = XProfile::where('user_id', $data['user_id'])->firstOrFail();
        } else {
            $xProfile = XProfile::where('username', $userName)->firstOrFail();
            if ($xProfile->user_id != get_current_user_id()) {
                return $this->sendError([
                    'message' => __('You are not allowed to update this profile', 'fluent-community')
                ]);
            }
        }

        $this->validate($data, [
            'first_name' => 'required',
        ], [
            'first_name.required' => __('First name is required', 'fluent-community')
        ]);

        $updateData = Arr::only($data, ['first_name', 'last_name', 'short_description', 'website']);

        $updateData = apply_filters('fluent_community/update_profile_data', $updateData, $data, $xProfile);

        $currentUser = User::findOrFail(get_current_user_id());
        $meta = $xProfile->meta;

        $userNameChanged = false;

        if ($currentUser->isCommunityModerator()) {
            $updateData['is_verified'] = Arr::get($data, 'is_verified') ? 1 : 0;
            $updateData['status'] = Arr::get($data, 'status', 'active');
            $userName = Arr::get($data, 'username');

            if (user_can($xProfile->user_id, 'list_users')) {
                $updateData['status'] = 'active';
            }

            if ($userName) {
                // Check if username is exit or not
                $userName = CustomSanitizer::sanitizeUserName($userName);

                if (!$userName) {
                    return $this->sendError([
                        'message' => __('Invalid username. Only Latin characters with _ & - are allowed.', 'fluent-community')
                    ]);
                }

                if (XProfile::where('username', $userName)->where('user_id', '!=', $xProfile->user_id)->exists()) {
                    return $this->sendError([
                        'message' => __('Community Username already taken by someone else', 'fluent-community')
                    ]);
                }

                $userExist = get_user_by('user_login', $userName);

                if ($userExist && $userExist->ID != $xProfile->user_id) {
                    return $this->sendError([
                        'message' => __('Username already taken by someone else. Please use a different username.', 'fluent-community')
                    ]);
                }

                $updateData['username'] = $userName;
                $userNameChanged = $userName != $xProfile->username;
            }

            if (Helper::isFeatureEnabled('user_badge')) {
                $badgeSlug = (array)Arr::get($data, 'badge_slugs', []);
                $meta['badge_slug'] = $badgeSlug;
            }
        } else if (Utility::getPrivacySetting('can_customize_username')) {
            $userName = Arr::get($data, 'username');

            if ($xProfile->username != $userName) {
                $userName = strtolower(CustomSanitizer::sanitizeUserName($userName));
                if (!$userName) {
                    return $this->sendError([
                        'message' => __('Invalid username. Only Latin characters with _ & - are allowed.', 'fluent-community')
                    ]);
                }

                if (XProfile::where('username', $userName)->where('user_id', '!=', $xProfile->user_id)->exists()) {
                    return $this->sendError([
                        'message' => __('Community Username already taken by someone else', 'fluent-community')
                    ]);
                }

                if (strlen($userName) < 3) {
                    return $this->sendError([
                        'message' => __('Username should be at least 3 characters long.', 'fluent-community')
                    ]);
                }

                $reservedUserNames = ProfileHelper::getReservedUserNames();
                if (in_array($userName, $reservedUserNames)) {
                    return $this->sendError([
                        'message' => __('Please use another username. This username is reserved', 'fluent-community')
                    ]);
                }

                $updateData['username'] = $userName;
                $userNameChanged = true;
            }
        }

        $updateData['display_name'] = trim(sanitize_text_field(Arr::get($data, 'first_name') . ' ' . Arr::get($data, 'last_name')));

        $updateData['short_description'] = CustomSanitizer::unslashMarkdown(sanitize_textarea_field(trim(Arr::get($data, 'short_description'))));
        $meta['website'] = sanitize_url(Arr::get($data, 'website'));
        $socialLinks = Arr::get($data, 'social_links', []);

        $maxDescriptionLength = apply_filters('fluent_community/max_profile_description_length', 5000);
        if ($updateData['short_description'] && strlen($updateData['short_description']) > $maxDescriptionLength) {
            return $this->sendError([
                'message' => sprintf(
                    /* translators: %d: Maximum number of characters allowed in the profile bio. */
                    __('Profile bio should not exceed %d characters.', 'fluent-community'),
                    $maxDescriptionLength
                )
            ]);
        }

        if ($socialLinks) {
            $socialLinks = array_filter($socialLinks);
            $formattedSocialLinkes = [];
            $socialLinkProviders = ProfileHelper::socialLinkProviders(true);
            foreach ($socialLinks as $linkName => $socialLink) {
                if (isset($socialLinkProviders[$linkName])) {
                    $formattedSocialLinkes[$linkName] = sanitize_text_field(trim($socialLink));
                }
            }
            $meta['social_links'] = $formattedSocialLinkes;
        }

        $updateData['meta'] = $meta;

        $xProfile->fill($updateData);
        $xProfile->save();


        // Let's update the user's details
        $xProfile->user->updateCustomData($updateData);
        $xProfile->compilation_score = $xProfile->getCompletionScore();

        if ($userNameChanged) {
            return [
                'message'      => __('Profile has been updated', 'fluent-community'),
                'profile'      => $xProfile,
                'redirect_url' => Helper::baseUrl('u/' . $xProfile->username . '/update')
            ];
        }

        $isOwn = $xProfile->user_id == get_current_user_id();
        $canEditUsers = current_user_can('edit_users');
        if ($canEditUsers || (Utility::getPrivacySetting('can_change_email') === 'yes' && $isOwn)) {
            $emailAddress = Arr::get($data, 'email');

            if ($emailAddress && is_email($emailAddress) && $emailAddress != $xProfile->user->user_email) {
                $owner_id = email_exists($xProfile->user->user_email);
                if ($owner_id != $xProfile->user_id) {
                    return $this->sendError([
                        'message' => __('Email address already taken by someone else. Please use a different email address.', 'fluent-community')
                    ]);
                }

                // Let's check if it's their own
                $requireVerification = $isOwn && !$canEditUsers;
                if ($requireVerification) {
                    $currentUser = get_user_by('ID', $xProfile->user_id);
                    ProfileHelper::sendConfirmationOnProfileEmailChange($currentUser, $emailAddress);
                    return [
                        'message' => __('Email address change is pending. Please check your inbox to verify the new email address.', 'fluent-community'),
                        'profile' => $xProfile
                    ];
                }

                wp_update_user([
                    'user_email' => $emailAddress,
                    'ID'         => $xProfile->user_id
                ]);
            }
        }

        return [
            'message' => __('Profile has been updated', 'fluent-community'),
            'profile' => $xProfile
        ];
    }

    public function getSpaces(Request $request, $userName)
    {
        $xProfile = XProfile::where('username', $userName)->firstOrFail();
        $currentUser = $this->getUser();

        if (!ProfileHelper::canViewUserSpaces($xProfile->user_id, $currentUser)) {
            return $this->sendError([
                'message'           => __('You are not allowed to view this profile\'s spaces.', 'fluent-community'),
                'permission_failed' => true
            ]);
        }

        if ($xProfile->user_id == get_current_user_id() || ($currentUser && $currentUser->isCommunityModerator())) {
            $spaces = $xProfile->spaces()
                ->wherePivot('status', 'active')
                ->get();
        } else {
            $spaces = $xProfile->spaces()
                ->whereIn('privacy', ['public', 'private'])
                ->wherePivot('status', 'active')
                ->get();
        }

        foreach ($spaces as $space) {

            $shouldHideMembersCount = Arr::get($space->settings, 'hide_members_count') == 'yes';
            $canViewMembers = $currentUser && $space->verifyUserPermisson($currentUser, 'can_view_members', false);
            if ($shouldHideMembersCount && !$canViewMembers) {
                $space->members_count = 0;
                continue;
            }
            $space->members_count = $space->members()->count();
        }

        return [
            'spaces' => $spaces
        ];
    }

    public function getComments(Request $request, $userName)
    {
        $xProfile = XProfile::where('username', $userName)->first();

        if (!$xProfile) {
            return $this->sendError([
                'message' => __('Profile not found', 'fluent-community')
            ]);
        }

        $currentUser = $this->getUser();
        $hasAllAccess = $xProfile->user_id == get_current_user_id() || ($currentUser && $currentUser->isCommunityModerator());

        $comments = Comment::where('user_id', $xProfile->user_id)
            ->where('status', 'published')
            ->with([
                'post' => function ($q) {
                    $q->select(['id', 'title', 'message', 'type', 'space_id', 'slug', 'created_at'])
                        ->with([
                            'space' => function ($q) {
                                $q->select(['id', 'title', 'slug', 'type']);
                            }
                        ]);
                }
            ])
            ->when(!$hasAllAccess, function ($q) use ($xProfile) {
                $q->whereHas('post', function ($query) use ($xProfile) {
                    $query->byUserAccess(get_current_user_id());
                    $query->where('type', 'text');
                });
            })
            ->orderBy('id', 'desc')
            ->paginate();

        return [
            'comments' => $comments,
            'xprofile' => $xProfile
        ];
    }

    public function getNotificationPreferance(Request $request, $userName)
    {
        $emailPref = Utility::getEmailNotificationSettings();

        $xProfile = $this->verfifyAndGetProfile($userName);

        $globalPreferances = NotificationPref::getGlobalPrefs();

        $userPrefs = NotificationSubscription::where('user_id', $xProfile->user_id)
            ->select(['notification_type', 'is_read', 'object_id'])
            ->get();

        $userGlobalPrefs = [];
        $spaceWisePrefs = [];
        foreach ($userPrefs as $pref) {
            if (!$pref->object_id) {
                if ($pref->notification_type === 'message_email_frequency') {
                    $maps = [
                        0 => 'disabled',
                        1 => 'hourly',
                        2 => 'daily',
                        3 => 'weekly'
                    ];

                    if ($maps[$pref->is_read]) {
                        $userGlobalPrefs[$pref->notification_type] = $maps[$pref->is_read];
                    } else {
                        $userGlobalPrefs[$pref->notification_type] = 'default';
                    }
                    continue;
                }
                $userGlobalPrefs[$pref->notification_type] = $pref->is_read ? 'yes' : 'no';
            } else {
                if (empty($spaceWisePrefs[$pref->object_id])) {
                    $spaceWisePrefs[$pref->object_id] = [];
                }
                $spaceWisePrefs[$pref->object_id][$pref->notification_type] = $pref->is_read;
            }
        }

        $messagingConfig = Utility::getOption('_messaging_settings', []);
        $isGlobalPerUser = Arr::get($messagingConfig, 'messaging_email_frequency') == 'disabled';

        $userGlobalPrefsDefaults = [
            'digest_mail'             => Arr::get($globalPreferances, 'digest_email_status') ? 'yes' : 'no',
            'mention_mail'            => Arr::get($globalPreferances, 'mention_mail') ? 'yes' : 'no',
            'reply_my_com_mail'       => Arr::get($globalPreferances, 'reply_my_com_mail') ? 'yes' : 'no',
            'com_my_post_mail'        => Arr::get($globalPreferances, 'com_my_post_mail') ? 'yes' : 'no',
            'message_email_frequency' => $isGlobalPerUser ? 'disabled' : 'default'
        ];

        $userGlobalPrefs = wp_parse_args($userGlobalPrefs, $userGlobalPrefsDefaults);

        $spaceGroups = SpaceGroup::with(['spaces' => function ($query) {
            $query->whereHas('members', function ($q) {
                $q->where('user_id', get_current_user_id())
                  ->where('status', 'active');
            })
                ->where('type', 'community');
        }])
            ->orderBy('serial', 'ASC')
            ->get();

        $formattedSpaceGroups = [];
        foreach ($spaceGroups as $group) {
            if ($group->spaces->isEmpty()) {
                continue;
            }
            $formattedSpaces = [];
            foreach ($group->spaces as $space) {

                $pref = '';
                if (isset($spaceWisePrefs[$space->id])) {
                    $perfs = (array)$spaceWisePrefs[$space->id];
                    if (!empty($perfs['np_by_member_mail'])) {
                        $pref = 'all_member_posts';
                    } else if (!empty($perfs['np_by_admin_mail'])) {
                        $pref = 'admin_only_posts';
                    }
                }

                $formattedSpaces[] = [
                    'id'    => $space->id,
                    'title' => $space->title,
                    'icon'  => $space->getIconMark(),
                    'pref'  => $pref
                ];
            }
            if ($formattedSpaces) {
                $formattedSpaceGroups[] = [
                    'id'     => $group->id,
                    'title'  => $group->title,
                    'spaces' => $formattedSpaces
                ];
            }
        }

        // let's find the other spaces
        $otherSpaces = Space::whereHas('members', function ($q) use ($xProfile) {
            $q->where('user_id', $xProfile->user_id);
        })
            ->whereNull('parent_id')
            ->orderBy('title', 'ASC')
            ->get();

        if (!$otherSpaces->isEmpty()) {
            $formattedSpaces = [];
            foreach ($otherSpaces as $space) {
                $pref = '';
                if (isset($spaceWisePrefs[$space->id])) {
                    $perfs = (array)$spaceWisePrefs[$space->id];
                    if (!empty($perfs['np_by_member_mail'])) {
                        $pref = 'all_member_posts';
                    } else if (!empty($perfs['np_by_admin_mail'])) {
                        $pref = 'admin_only_posts';
                    }
                }

                $formattedSpaces[] = [
                    'id'    => $space->id,
                    'title' => $space->title,
                    'icon'  => $space->getIconMark(),
                    'pref'  => $pref
                ];
            }

            $formattedSpaceGroups[] = [
                'id'     => 'other_space_group',
                'title'  => __('Other Spaces', 'fluent-community'),
                'spaces' => $formattedSpaces
            ];
        }

        $digestDay = (string)Arr::get($emailPref, 'digest_mail_day', 'tue');
        if ($digestDay) {
            $maps = [
                'mon' => __('Monday', 'fluent-community'),
                'tue' => __('Tuesday', 'fluent-community'),
                'wed' => __('Wednesday', 'fluent-community'),
                'thu' => __('Thursday', 'fluent-community'),
                'fri' => __('Friday', 'fluent-community'),
                'sat' => __('Saturday', 'fluent-community'),
                'sun' => __('Sunday', 'fluent-community'),
            ];
            if (isset($maps[$digestDay])) {
                $digestDay = $maps[$digestDay];
            }
        }

        return [
            'user_globals'                      => (object)$userGlobalPrefs,
            'spaceGroups'                       => $formattedSpaceGroups,
            'space_prefs'                       => $spaceWisePrefs,
            'digestEmailDay'                    => $digestDay,
            'default_messaging_email_frequency' => Arr::get($messagingConfig, 'messaging_email_status') !== 'yes' ? 'no' : Arr::get($messagingConfig, 'messaging_email_frequency'),
        ];
    }

    public function saveNotificationPreferance(Request $request, $userName)
    {
        $xProfile = $this->verfifyAndGetProfile($userName);

        $userPrefs = $request->get('user_globals', []);
        $sapcePrefs = $request->get('space_prefs', []);

        $messagingPref = Arr::get($userPrefs, 'message_email_frequency');

        $userPrefs = array_map(function ($item) {
            return $item == 'yes' ? 1 : 0;
        }, $userPrefs);

        if ($messagingPref == 'hourly') {
            $userPrefs['message_email_frequency'] = 1;
        } else if ($messagingPref == 'daily') {
            $userPrefs['message_email_frequency'] = 2;
        } else if ($messagingPref == 'disabled') {
            $userPrefs['message_email_frequency'] = 0;
        } else if ($messagingPref == 'weekly') {
            $userPrefs['message_email_frequency'] = 3;
        } else {
            unset($userPrefs['message_email_frequency']);
        }

        foreach ($sapcePrefs as $spaceId => $pref) {
            $spaceId = (int)$spaceId;
            if (!$pref || !$spaceId) {
                continue;
            }

            if ($pref == 'all_member_posts') {
                $userPrefs['np_by_member_mail_' . $spaceId] = 1;
                $userPrefs['np_by_admin_mail_' . $spaceId] = 1;
            } else if ($pref == 'admin_only_posts') {
                $userPrefs['np_by_admin_mail_' . $spaceId] = 1;
            }
        }

        NotificationPref::updateUserPrefs($xProfile->user_id, $userPrefs);

        return [
            'prefs'   => $userPrefs,
            'message' => __('Email Notification preferences have been updated', 'fluent-community')
        ];
    }

    private function verfifyAndGetProfile($userName)
    {
        $xProfile = XProfile::where('username', $userName)->firstOrFail();

        $currentUser = $this->getUser();
        if ($xProfile->user_id != get_current_user_id() && (!$currentUser || !$currentUser->isCommunityModerator())) {
            throw new \Exception('You are not allowed to update this profile');
        }

        return $xProfile;
    }
}
