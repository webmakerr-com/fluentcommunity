<?php

namespace FluentCommunity\App\Http\Policies;

use FluentCommunity\App\Http\Policies\BasePolicy;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Http\Request\Request;

class AdminPolicy extends BasePolicy
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
}
