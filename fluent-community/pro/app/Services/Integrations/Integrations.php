<?php

namespace FluentCommunityPro\App\Services\Integrations;

use FluentCommunity\App\Services\Helper;
use FluentCommunityPro\App\Services\Integrations\FluentCRM\AddOrRemoveVerificationAction;
use FluentCommunityPro\App\Services\Integrations\FluentCRM\AddToSpaceAction;
use FluentCommunityPro\App\Services\Integrations\FluentCRM\AddToCourseAction;
use FluentCommunityPro\App\Services\Integrations\FluentCRM\CommunitySmartCodes;
use FluentCommunityPro\App\Services\Integrations\FluentCRM\CourseLeaveTrigger;
use FluentCommunityPro\App\Services\Integrations\FluentCRM\SpaceLeaveTrigger;
use FluentCommunityPro\App\Services\Integrations\FluentCRM\SpaceMembershipStatusChangeAction;
use FluentCommunityPro\App\Services\Integrations\FluentCRM\ContactAdvancedFilter;
use FluentCommunityPro\App\Services\Integrations\FluentCRM\RemoveFromSpaceAction;
use FluentCommunityPro\App\Services\Integrations\FluentCRM\RemoveFromCourseAction;

class Integrations
{
    public function register()
    {
        $this->init();
    }

    public function init()
    {
        if (defined('FLUENTCRM')) {
            new AddToSpaceAction();
            new SpaceMembershipStatusChangeAction();
            new RemoveFromSpaceAction();
            new SpaceLeaveTrigger();
            new AddOrRemoveVerificationAction();

            (new ContactAdvancedFilter())->register();
            (new CommunitySmartCodes())->init();


            // Course Specifics
            if (Helper::isFeatureEnabled('course_module')) {
                new RemoveFromCourseAction();
                new AddToCourseAction();
                new CourseLeaveTrigger();
            }
        }
    }
}
