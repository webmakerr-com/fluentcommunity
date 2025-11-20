<?php

namespace FluentCommunity\App\Http\Policies;

use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Http\Request\Request;
use FluentCommunity\Framework\Foundation\Policy;

class UserPolicy extends Policy
{
    /**
     * Check user permission for any method
     * @param  \FluentCommunity\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        return Helper::isSiteAdmin();
    }

    /**
     * Check user permission for any method
     * @param  \FluentCommunity\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function create(Request $request)
    {
        return Helper::isSiteAdmin();
    }
}
