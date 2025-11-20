<?php

namespace FluentCommunity\App\Http\Controllers;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\BaseSpace;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\App\Services\ProfileHelper;
use FluentCommunity\Framework\Http\Request\Request;

class MembersController extends Controller
{
    public function getMembers(Request $request)
    {
        $start = microtime(true);
        $mention = $request->getSafe('mention', 'sanitize_text_field');
        if($mention && !get_current_user_id()) {
            return $this->sendError([
                'message' => __('You must be logged in to mention someone', 'fluent-community'),
            ]);
        }

        $canAccess = Utility::canViewMembersPage();
        $isMod = Helper::isModerator();

        $members = XProfile::select(ProfileHelper::getXProfilePublicFields())
            ->whereHas('user');

        if($mention) {
            $spaceSlug = $request->getSafe('space', 'sanitize_text_field');
            $space = null;
            if ($spaceSlug) {
                $space = BaseSpace::where('slug', $spaceSlug)->first();
                if (!$space || !Helper::isUserInSpace(get_current_user_id(), $space->id)) {
                    return $this->sendError([
                        'message' => __('Please join the space first to view the members', 'fluent-community'),
                        'permission_failed' => true
                    ]);
                }
            }

            if (!$space) {
                $spaceId = $request->getSafe('space_id', 'intval');
                if ($spaceId) {
                    $space = BaseSpace::find($spaceId);
                    if (!$space || !Helper::isUserInSpace(get_current_user_id(), $space->id)) {
                        return $this->sendError([
                            'message' => __('Space not found', 'fluent-community'),
                            'permission_failed' => true
                        ]);
                    }
                }
            }

            if ($space) {
                $members = $members->whereHas('spaces', function ($query) use ($space) {
                    $query->where('space_id', $space->id);
                });
            }

            $members = $members->where('status', 'active')
                ->where('user_id', '!=', get_current_user_id())
                ->mentionBy($mention)
                ->limit(10)
                ->get();

            return [
                'members' => [
                    'data' => $members
                ],
                'execution_time' => microtime(true) - $start
            ];
        }

        if(!$canAccess) {
            return $this->sendError([
                'message' => __('You do not have permission to view members', 'fluent-community'),
                'permission_failed' => true
            ]);
        }

        $shortBy = $request->getSafe('sort_by', 'sanitize_text_field', 'last_activity');

        if ($shortBy == 'last_activity') {
            $members = $members->orderBy('last_activity', 'DESC');
        } else {
            $members = $members->orderBy($shortBy, 'ASC');
        }

        $members = $members
            ->searchBy($request->getSafe('search', 'sanitize_text_field'));

        if($isMod) {
            $statuses = $request->getSafe('status', 'sanitize_text_field', 'active');
            if ($statuses == 'in_active') {
                $statuses = ['pending', 'blocked'];
            } else if($statuses == 'deactivated') {
                $statuses = [''];
            } else {
                $statuses = ['active'];
            }
            $members = $members->whereIn('status', $statuses);
        } else {
            $members = $members->where('status', 'active');
        }

        do_action_ref_array('fluent_community/members_query_ref', [&$members, $request->all()]);

        $members = $members->paginate();

        return apply_filters('fluent_community/members_api_response', [
            'members' => $members,
            'execution_time' => microtime(true) - $start
        ], $members, $request->all());
    }

    public function patchMember(Request $request, $userId)
    {
        $currentUser = $this->getUser(true);
        $currentUser->verifyCommunityPermission('delete_any_feed');

        $newStatus = $request->getSafe('status', 'sanitize_text_field');

        $wpUser = User::findOrFail($userId);

        if ($wpUser->isCommunityAdmin() && $newStatus != 'active') {
            return $this->sendError([
                'message' => __('Sorry, you can not change status of another admin. Remove the user from moderators', 'fluent-community')
            ]);
        }

        $xProfile = XProfile::findOrFail($userId);

        $newStatus = $request->getSafe('status', 'sanitize_text_field');
        $validStatuses = ['active', 'pending', 'blocked'];

        if (in_array($newStatus, $validStatuses)) {
            $xProfile->status = $newStatus;
            $xProfile->save();
        }

        return [
            'message' => __('Member status have been updated', 'fluent-community'),
            'member'  => $xProfile
        ];
    }
}
