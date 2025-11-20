<?php

namespace FluentCommunity\App\Http\Policies;

use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Foundation\Policy;
use FluentCommunity\Framework\Http\Request\Request;

/**
 *  BasePolicy - REST API Permission Policy
 *
 * @package FluentCrm\App\Http
 *
 * @version 1.0.0
 */
class BasePolicy extends Policy
{

    /**
     * Check user permission for any method
     * @param \FluentCommunity\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        return Helper::isSiteAdmin();
    }

    public function currentUserCan($permission)
    {
        return current_user_can($permission);
    }
}
