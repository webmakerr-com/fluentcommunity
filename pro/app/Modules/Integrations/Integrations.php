<?php

namespace FluentCommunityPro\App\Modules\Integrations;

use FluentCommunity\Framework\Foundation\Application;

class Integrations
{

    public function register(Application $app)
    {
        $this->init();
    }

    public function init()
    {
        if (defined('WPPAYFORM_VERSION')) {
            new \FluentCommunityPro\App\Modules\Integrations\Paymattic\Bootstrap();
        }
    }
}
