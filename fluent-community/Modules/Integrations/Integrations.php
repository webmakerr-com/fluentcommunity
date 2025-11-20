<?php

namespace FluentCommunity\Modules\Integrations;

use FluentCommunity\App\Services\Helper;

class Integrations
{
    public function register($app)
    {
        $this->init($app);
    }

    public function init($app)
    {
        if (defined('FLUENTCRM')) {
            new \FluentCommunity\Modules\Integrations\FluentCRM\SpaceJoinTrigger();
            (new  \FluentCommunity\Modules\Integrations\FluentCRM\ProfileSection())->register();
            // Course Specifics
            if (Helper::isFeatureEnabled('course_module')) {
                new \FluentCommunity\Modules\Integrations\FluentCRM\CourseEnrollmentTrigger();
                new \FluentCommunity\Modules\Integrations\FluentCRM\CourseCompletedTrigger();
                new \FluentCommunity\Modules\Integrations\FluentCRM\CourseTopicCompletedTrigger();
                new \FluentCommunity\Modules\Integrations\FluentCRM\LessonCompletedTrigger();
            }
        }

        if (defined('FLUENTFORM')) {
            add_action('init', function () {
                new \FluentCommunity\Modules\Integrations\FluentForms\Bootstrap();
            });
        }

        if (defined('FLUENTCART_VERSION')) {
            (new \FluentCommunity\Modules\Integrations\FluentCart\Paywalls())->register($app);
            (new \FluentCommunity\Modules\Integrations\FluentCart\CartCheckout())->register();
        }
    }
}
