<?php

namespace FluentCommunity\App\Http\Controllers;

use FluentCommunity\App\Models\User;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Http\Controller as BaseController;

abstract class Controller extends BaseController
{
    /**
     * @return \FluentCommunity\App\Models\User
     */
    public function getUser($throwException = false)
    {
        $user =  Helper::getCurrentUser(true);

        if($throwException && !$user) {
            throw new \Exception(esc_html__('You need to be logged in to perform this action', 'fluent-community'));
        }

        return $user;
    }

    public function getUserId()
    {
        return get_current_user_id();
    }

    /**
     * @return \FluentCommunity\App\Models\XProfile
     */
    public function getXProfile()
    {
        return Helper::getCurrentProfile(true);
    }
}
