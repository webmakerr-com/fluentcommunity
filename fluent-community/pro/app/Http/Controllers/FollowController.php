<?php

namespace FluentCommunityPro\App\Http\Controllers;

use FluentCommunity\Framework\Http\Request\Request;
use FluentCommunityPro\App\Models\Follow;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\App\Services\Helper;
use FluentCommunityPro\App\Modules\Followers\FollowerHelper;

class FollowController extends Controller
{
    public function follow(Request $request, $username)
    {
        $xProfile = XProfile::where('username', $username)->first();

        if (!$xProfile) {
            return $this->sendError([
                'message' => __('Profile not found', 'fluent-community-pro')
            ]);
        }

        $followerId = get_current_user_id();
        $followedId = $xProfile->user_id;

        if ($followerId === $followedId) {
            return $this->sendError([
                'message' => __('You cannot follow yourself.', 'fluent-community-pro')
            ]);
        }

        $follow = Follow::where('follower_id', $followerId)
            ->where('followed_id', $followedId)
            ->first();

        if ($follow) {
            return $this->sendError([
                'message' => __('You are already following or blocked this user.', 'fluent-community-pro')
            ]);
        }

        $follow = Follow::create([
            'follower_id' => $followerId,
            'followed_id' => $followedId
        ]);

        do_action('fluent_community/followed_user', $follow, $xProfile);

        return [
            'follow'   => $follow,
            'xprofile' => $xProfile,
            'message'  => __('You followed this user successfully', 'fluent-community-pro')
        ];
    }

    public function unfollow(Request $request, $username)
    {
        $xProfile = XProfile::where('username', $username)->first();

        if (!$xProfile) {
            return $this->sendError([
                'message' => __('Profile not found', 'fluent-community-pro')
            ]);
        }

        $follow = Follow::where('follower_id', get_current_user_id())
            ->where('followed_id', $xProfile->user_id)
            ->first();

        if (!$follow || $follow->level == 0) {
            return $this->sendError([
                'message' => __('You are not currently following this user.', 'fluent-community-pro')
            ]);
        }

        do_action('fluent_community/before_unfollowing_user', $follow, $xProfile);

        $follow->delete();

        return [
            'follow'   => $follow,
            'xprofile' => $xProfile,
            'message'  => __('You unfollowed this user successfully', 'fluent-community-pro')
        ];
    }

    public function block(Request $request, $username)
    {
        $xProfile = XProfile::where('username', $username)->first();

        if (!$xProfile) {
            return $this->sendError([
                'message' => __('Profile not found', 'fluent-community-pro')
            ]);
        }

        if ($xProfile->user_id == get_current_user_id()) {
            return $this->sendError([
                'message' => __('You cannot block yourself.', 'fluent-community-pro')
            ]);
        }

        if ($xProfile->user->hasCommunityModeratorAccess()) {
            return $this->sendError([
                'message' => __('Sorry, You cannot block a moderator.', 'fluent-community-pro')
            ]);
        }

        if (Helper::isModerator()) {
            return $this->sendError([
                'message' => __('Sorry, You cannot block a user as you are a moderator.', 'fluent-community-pro')
            ]);
        }

        $followerId = get_current_user_id();

        $follow = Follow::where('follower_id', $followerId)
            ->where('followed_id', $xProfile->user_id)
            ->first();

        if ($follow) {
            if ($follow->level != 0) {
                $follow->level = 0;
                $follow->save();
            }
        } else {
            $follow = Follow::create([
                'follower_id' => $followerId,
                'followed_id' => $xProfile->user_id,
                'level'       => 0
            ]);
        }

        do_action('fluent_community/blocked_user', $follow, $xProfile);

        return [
            'follow'   => $follow,
            'xprofile' => $xProfile,
            'message'  => __('You blocked this user successfully', 'fluent-community-pro')
        ];
    }

    public function unblock(Request $request, $username)
    {
        $xProfile = XProfile::where('username', $username)->first();

        if (!$xProfile) {
            return $this->sendError([
                'message' => __('Profile not found', 'fluent-community-pro')
            ]);
        }

        $follow = Follow::where('follower_id', get_current_user_id())
            ->where('followed_id', $xProfile->user_id)
            ->first();

        if (!$follow || $follow->level != 0) {
            return $this->sendError([
                'message' => __('You are not currently blocking this user.', 'fluent-community-pro')
            ]);
        }

        do_action('fluent_community/before_unblocking_user', $follow, $xProfile);

        $follow->delete();

        return [
            'follow'   => $follow,
            'xprofile' => $xProfile,
            'message'  => __('You unblocked this user successfully', 'fluent-community-pro')
        ];
    }

    public function getFollowers(Request $request, $username)
    {
        $xProfile = XProfile::where('username', $username)->first();

        if (!$xProfile || ($xProfile->status != 'active' && !Helper::isModerator())) {
            return $this->sendError([
                'message' => __('This profile is not active', 'fluent-community-pro')
            ]);
        }

        $canViewFollowers = FollowerHelper::canViewFollowers($xProfile->user_id);
        if (!$canViewFollowers) {
            return $this->sendError([
                'message' => __('You don\'t have permission to view the followers of this profile', 'fluent-community-pro')
            ]);
        }

        $followers = Follow::where('followed_id', $xProfile->user_id)
            ->where('level', '>', 0)
            ->orderBy('id', 'DESC')
            ->with('follower')
            ->paginate();

        $followerIds = $followers->getCollection()->pluck('follower_id')->toArray();

        $currentUserFollows = Follow::where('follower_id', get_current_user_id())
            ->whereIn('followed_id', $followerIds)
            ->pluck('level', 'followed_id');

        $followers->getCollection()->each(function ($follow) use ($currentUserFollows) {
            if (empty($follow->follower)) {
                return;
            }
            $follow->follower->follow = $currentUserFollows[$follow->follower_id] ?? null;
        });

        return [
            'followers' => $followers
        ];
    }

    public function getFollowings(Request $request, $username)
    {
        $xProfile = XProfile::where('username', $username)->first();

        if (!$xProfile || ($xProfile->status != 'active' && !Helper::isModerator())) {
            return $this->sendError([
                'message' => __('This profile is not active', 'fluent-community-pro')
            ]);
        }

        $canViewFollowings = FollowerHelper::canViewFollowings($xProfile->user_id);
        if (!$canViewFollowings) {
            return $this->sendError([
                'message' => __('You don\'t have permission to view the followings of this profile', 'fluent-community-pro')
            ]);
        }

        $followings = Follow::where('follower_id', $xProfile->user_id)
            ->where('level', '>', 0)
            ->orderBy('id', 'DESC')
            ->with('followed')
            ->paginate();

        $followingIds = $followings->getCollection()->pluck('followed_id')->toArray();

        $currentUserFollows = Follow::where('follower_id', get_current_user_id())
            ->whereIn('followed_id', $followingIds)
            ->pluck('level', 'followed_id');

        $followings->getCollection()->each(function ($follow) use ($currentUserFollows) {
            if (empty($follow->followed)) {
                return;
            }
            $follow->followed->follow = $currentUserFollows[$follow->followed_id] ?? null;
        });

        return [
            'followings' => $followings
        ];
    }

    public function getBlockedUsers(Request $request, $username)
    {
        $xProfile = XProfile::where('username', $username)->first();

        if (!$xProfile || ($xProfile->status != 'active' && !Helper::isModerator())) {
            return $this->sendError([
                'message' => __('This profile is not active', 'fluent-community-pro')
            ]);
        }

        $blockedUsers = Follow::where('follower_id', $xProfile->user_id)
            ->where('level', 0)
            ->with('followed')
            ->paginate();

        return [
            'blockedUsers' => $blockedUsers
        ];
    }

    public function toggleNotification(Request $request, $username)
    {
        $xProfile = XProfile::where('username', $username)->first();

        if (!$xProfile || $xProfile->status != 'active') {
            return $this->sendError([
                'message' => __('Profile not found', 'fluent-community-pro'),
            ]);
        }

        $follow = Follow::where('follower_id', get_current_user_id())
            ->where('followed_id', $xProfile->user_id)
            ->first();

        if (!$follow || $follow->level == 0) {
            return $this->sendError([
                'message' => __('You are not currently following this user.', 'fluent-community-pro'),
            ]);
        }

        $follow->level = $follow->level == 2 ? 1 : 2;
        $follow->save();

        return [
            'follow'  => $follow,
            'message' => __('Notification has been updated successfully', 'fluent-community-pro')
        ];
    }
}
