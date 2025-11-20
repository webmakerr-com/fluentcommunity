<?php

namespace FluentCommunity\Modules\Auth\Classes;

use FluentCommunity\App\Http\Policies\BasePolicy;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Http\Request\Request;

class InvitationPolicy extends BasePolicy
{
    /**
     * Check user permission for any method
     * @param \FluentCommunity\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        $userId = get_current_user_id();

        if (!$userId) {
            return false;
        }

        return Helper::canAccessPortal($userId);
    }
}
