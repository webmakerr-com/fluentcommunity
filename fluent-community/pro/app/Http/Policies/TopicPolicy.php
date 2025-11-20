<?php

namespace FluentCommunityPro\App\Http\Policies;

use FluentCommunity\Framework\Request\Request;
use FluentCommunity\Framework\Foundation\Policy;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Models\Space;

class TopicPolicy extends Policy
{
    /**
     * Check user permission for any method
     * @param WPFluent\Framework\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        $userId = get_current_user_id();

        if (!$userId) {
            return false;
        }

        $user = User::find($userId);

        if ($user->hasSpaceManageAccess()) {
            return true;
        }

        if ($request->method() == 'GET') {
            if ($request->get('optionsOnly') && $user->hasCourseCreatorAccess()) {
                return true;
            }
        }

        $space = Space::find($request->get('space_id'));

        return $space && $user->getSpaceRole($space) === 'admin';
    }
}

