<?php

namespace FluentCommunityPro\App\Http\Controllers;

use FluentCommunityPro\App\Core\App;
use FluentCommunity\App\Http\Controllers\Controller as BaseController;

abstract class Controller extends BaseController
{
    public function __construct()
    {
        parent::__construct(App::getInstance());
    }
}
