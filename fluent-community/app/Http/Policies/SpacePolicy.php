<?php

namespace FluentCommunity\App\Http\Policies;

use FluentCommunity\App\Models\User;
use FluentCommunity\App\Models\Space;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Http\Request\Request;

class SpacePolicy extends BasePolicy
{
    /**
     * Check user permission for any method
     * @param \FluentCommunity\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        $xProfile = Helper::getCurrentProfile(true);
        if ($xProfile && $xProfile->status != 'active') {
            return false;
        }

        $userId = get_current_user_id();

        if ($request->getMethod() == 'GET') {
            return !!Helper::canAccessPortal($userId);
        }

        if (!$userId) {
            return false;
        }

        return $this->canManageCommunity($request);
    }

    public function join(Request $request)
    {
        return !!get_current_user_id();
    }

    public function leave(Request $request)
    {
        return !!get_current_user_id();
    }

    public function deleteBySlug(Request $request)
    {
        return $this->canManageCommunity($request);
    }

    public function deleteById(Request $request)
    {
        return $this->canManageCommunity($request);
    }

    public function addMember(Request $request, $slug)
    {
        $space = Space::where('slug', $slug)->first();

        $user = User::find(get_current_user_id());

        return $user && $user->verifySpacePermission('can_add_member', $space);
    }

    public function removeMember(Request $request, $slug)
    {
        $space = Space::where('slug', $slug)->first();

        $user = User::find(get_current_user_id());

        return $user && $user->verifySpacePermission('can_remove_member', $space);
    }

    public function getSpaceGroups(Request $request)
    {
        if ($request->get('options_only')) {
            return true;
        }

        return $this->canManageSpace($request);
    }

    public function createSpaceGroup(Request $request)
    {
        return $this->canManageSpace($request);
    }

    public function updateSpaceGroup(Request $request)
    {
        return $this->canManageSpace($request);
    }

    public function deleteSpaceGroup(Request $request)
    {
        return $this->canManageSpace($request);
    }

    public function getMetaSettings(Request $request)
    {
        return $this->canManageSpace($request);
    }

    protected function canManageCommunity(Request $request)
    {
        $user = User::find(get_current_user_id());

        if (!$user) {
            return false;
        }

        if ($user->hasCommunityAdminAccess()) {
            return true;
        }

        $space = Space::find($request->get('space_id'));

        return $space && $user->getSpaceRole($space) === 'admin';
    }

    protected function canManageSpace(Request $request)
    {
        $user = User::find(get_current_user_id());

        if (!$user) {
            return false;
        }

        if ($user->hasSpaceManageAccess()) {
            return true;
        }

        $space = Space::find($request->get('space_id'));

        return $space && $user->getSpaceRole($space) === 'admin';
    }
}

