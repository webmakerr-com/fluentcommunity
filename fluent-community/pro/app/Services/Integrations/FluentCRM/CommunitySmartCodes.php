<?php

namespace FluentCommunityPro\App\Services\Integrations\FluentCRM;


use FluentCommunity\App\Models\BaseSpace;
use FluentCommunity\App\Models\SpaceUserPivot;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\App\Services\Helper;
use FluentCrm\App\Models\FunnelSubscriber;

class CommunitySmartCodes
{
    public function init()
    {
        add_filter('fluent_crm/smartcode_group_callback_fluent_com', array($this, 'parseCodes'), 10, 4);
        add_filter('fluent_crm/extended_smart_codes', array($this, 'pushGeneralCodes'));
        add_filter('fluent_crm_funnel_context_smart_codes', array($this, 'pushContextCodes'), 12, 2);
    }

    public function pushGeneralCodes($codes)
    {
        $codes['fluent_com'] = [
            'key'        => 'fluent_com',
            'title'      => 'FluentCommunity',
            'shortcodes' => $this->getSmartCodes()
        ];

        return $codes;
    }

    public function parseCodes($code, $valueKey, $defaultValue, $subscriber)
    {
        $userId = $subscriber->getWpUserId();

        if (!$userId) {
            return $defaultValue;
        }

        $xProfile = XProfile::where('user_id', $userId)->first();

        if (!$xProfile) {
            return $defaultValue;
        }

        /*
         * General Student Items
         */
        switch ($valueKey) {
            case 'courses':
            case 'spaces':
                if ($valueKey == 'courses') {
                    $items = $xProfile->courses;
                } else {
                    $items = $xProfile->spaces;
                }

                $itemNames = array();
                foreach ($items as $item) {
                    $itemNames[] = $item->title;
                }

                return implode(', ', $itemNames);
            case 'courses_link':
            case 'spaces_link':
                if ($valueKey == 'courses_link') {
                    $items = $xProfile->courses;
                } else {
                    $items = $xProfile->spaces;
                }
                $itemNames = array();
                foreach ($items as $item) {
                    $itemNames[] = [
                        'title'     => $item->title,
                        'permalink' => $item->getPermalink()
                    ];
                }

                if (!$itemNames) {
                    return $defaultValue;
                }

                $html = '<ul class="fcom_spaces">';
                foreach ($itemNames as $itemName) {
                    $html .= '<li><a href="' . $itemName['permalink'] . '">' . $itemName['title'] . '</a>';
                }
                $html .= '</ul>';
                return $html;
        }

        /*
         * Contextual Course / Space Related SmartCodes
         */
        $triggerSource = false;
        $triggerId = false;

        if (!empty($subscriber->funnel_subscriber_id)) {
            $funnelSub = FunnelSubscriber::where('id', $subscriber->funnel_subscriber_id)->first();
            if ($funnelSub) {
                $triggerSource = $this->getTriggerSource($funnelSub->source_trigger_name);
                $triggerId = $funnelSub->source_ref_id;
            }
        }

        if ($triggerSource == 'course' && !in_array($valueKey, ['course_name', 'course_href', 'course_name_linked'])) {
            $triggerId = false;
        } else if ($triggerSource == 'space' && !in_array($valueKey, ['space_name', 'space_href', 'space_name_linked'])) {
            $triggerId = false;
        }

        if (!$triggerId) {
            $courseItems = ['course_name', 'course_href', 'course_name_linked'];
            if (in_array($valueKey, $courseItems)) {
                $triggerSource = 'course';
                // Get the last course the user enrolled in
                $lastItem = SpaceUserPivot::where('role', 'student')
                    ->where('user_id', $userId)
                    ->orderBy('created_at', 'DESC')
                    ->first();
                if ($lastItem) {
                    $triggerId = $lastItem->space_id;
                }
            } else if (in_array($valueKey, ['space_name', 'space_href', 'space_name_linked'])) {
                $triggerSource = 'space';
                // Get the last space the user joined
                $lastItem = SpaceUserPivot::whereIn('role', ['member', 'moderator', 'admin'])
                    ->where('user_id', $userId)
                    ->where('status', 'active')
                    ->orderBy('created_at', 'DESC')
                    ->first();

                if ($lastItem) {
                    $triggerId = $lastItem->space_id;
                }
            } else {
                return $defaultValue;
            }
        }

        if (!$triggerId) {
            return $defaultValue;
        }

        $baseSpace = BaseSpace::withoutGlobalScopes()->find($triggerId);

        if (!$baseSpace) {
            return $defaultValue;
        }

        switch ($valueKey) {
            case 'course_name':
            case 'space_name':
                return $baseSpace->title;
            case 'course_href':
            case 'space_href':
                return $baseSpace->getPermalink();
            case 'course_name_linked':
            case 'space_name_linked':
                $title = $baseSpace->title;
                if ($title) {
                    return '<a href="' . $baseSpace->getPermalink() . '">' . $title . '</a>';
                }
                return $defaultValue;
        }

        return $defaultValue;
    }

    public function pushContextCodes($codes, $context)
    {
        $triggerSource = $this->getTriggerSource($context);
        if (!$triggerSource) {
            return $codes;
        }

        if ($triggerSource == 'course') {
            $codes[] = [
                'key'        => 'fluent_com_course',
                'title'      => 'Enrolled Course',
                'shortcodes' => $this->getSmartCodes('course')
            ];
            return $codes;
        }

        if ($triggerSource == 'space') {
            $codes[] = [
                'key'        => 'fluent_com_space',
                'title'      => 'Space Membership',
                'shortcodes' => $this->getSmartCodes('space')
            ];
            return $codes;
        }

        return $codes;
    }

    private function getSmartCodes($withContext = '')
    {
        $generalCodes = [
            '{{fluent_com.spaces}}'       => __('Membership Space Names (Comma Separated)', 'fluent-community-pro'),
            '{{fluent_com.courses_link}}' => __('Membership Space Names with links (list)', 'fluent-community-pro'),
        ];

        if (Helper::isFeatureEnabled('course_module')) {
            $generalCodes['{{fluent_com.courses}}'] = __('Enrolled Course Names (Comma Separated)', 'fluent-community-pro');
            $generalCodes['{{fluent_com.courses_link}}'] = __('Enrolled Course Names with links (list)', 'fluent-community-pro');
        }

        if (!$withContext) {
            return $generalCodes;
        }

        $courseContext = [
            '{{fluent_com.course_name}}'        => __('Current Course Title', 'fluent-community-pro'),
            '{{fluent_com.course_name_linked}}' => __('Current Course Title with Hyperlink', 'fluent-community-pro'),
            '##fluent_com.course_href##'        => __('HTTP Link of the current course', 'fluent-community-pro')
        ];

        $spaceContext = [
            '{{fluent_com.space_name}}'        => __('Current Space Title', 'fluent-community-pro'),
            '{{fluent_com.space_name_linked}}' => __('Current Space Title with Hyperlink', 'fluent-community-pro'),
            '##fluent_com.space_href##'        => __('HTTP Link of the current Space', 'fluent-community-pro')
        ];

        if ($withContext == 'all') {
            return array_merge($generalCodes, $courseContext, $spaceContext);
        }

        if ($withContext == 'course') {
            return $courseContext;
        }

        if ($withContext == 'space') {
            return $spaceContext;
        }

//        if ($withContext == 'lesson') {
//            return [
//                '{{fluent_com.lesson_name}}'        => __('Current Lesson Title', 'fluent-community-pro'),
//                '{{fluent_com.lesson_name_linked}}' => __('Current Lesson Title with Hyperlink', 'fluent-community-pro'),
//                '##fluent_com.lesson_href##'        => __('HTTP Link of the current Lesson', 'fluent-community-pro')
//            ];
//        }

        return [];
    }


    public function getTriggerSource($triggerName)
    {
        $maps = [
            'fluent_community/space/user_left'     => 'space',
            'fluent_community/space/joined'        => 'space',
            'fluent_community/course/student_left' => 'course',
            'fluent_community/course/enrolled'     => 'course',
            'fluent_community/course/completed' => 'course'
        ];

        return isset($maps[$triggerName]) ? $maps[$triggerName] : false;
    }
}
