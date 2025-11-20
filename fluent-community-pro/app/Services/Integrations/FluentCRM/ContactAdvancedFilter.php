<?php

namespace FluentCommunityPro\App\Services\Integrations\FluentCRM;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\Space;
use FluentCommunity\App\Models\SpaceUserPivot;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\Modules\Course\Model\Course;
use FluentCommunity\Modules\Course\Services\CourseHelper;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Services\Libs\ConditionAssessor;

class ContactAdvancedFilter
{

    public function register()
    {
        add_filter('fluentcrm_advanced_filter_options', [$this, 'pushConditionGroup']);
        add_filter('fluentcrm_automation_condition_groups', [$this, 'pushConditionGroup']);

        add_filter('fluentcrm_automation_conditions_assess_fcom', [$this, 'checkFunnelCondition'], 10, 3);
        add_filter('fluentcrm_contacts_filter_fcom', [$this, 'setContactConditions'], 10, 2);

        /*
         * Contact Bulk Actions
         */
        add_filter('fluent_crm/custom_contact_bulk_actions', [$this, 'pushContactBulkActions']);
        add_filter('fluent_crm/contact_bulk_action_add_to_community', [$this, 'handleAddToCommunity'], 10, 3);
        add_filter('fluent_crm/contact_bulk_action_remove_from_community_space', [$this, 'handleRemoveFromCommunity'], 10, 3);
        add_filter('fluent_crm/contact_bulk_action_add_to_community_course', [$this, 'handleAddToCourse'], 10, 3);
        add_filter('fluent_crm/contact_bulk_action_remove_from_community_course', [$this, 'handleRemoveFromCourse'], 10, 3);

        add_filter('fluent_crm/contact_bulk_action_add_badge_to_community_users', [$this, 'handleAddBadge'], 10, 3);
        add_filter('fluent_crm/contact_bulk_action_remove_badge_to_community_users', [$this, 'handleRemoveBadge'], 10, 3);

        add_filter('fluent_community/activity/after_contents_user', [$this, 'pushCrmProfile'], 10, 2);


    }

    public function pushConditionGroup($groups)
    {
        $spaceOptions = Space::orderBy('title', 'ASC')->get()->pluck('title', 'id')->toArray();

        $group = [
            'label'    => __('FluentCommunity', 'fluent-community-pro'),
            'value'    => 'fcom',
            'children' => [
                [
                    'label'             => __('Space Membership', 'fluent-community-pro'),
                    'value'             => 'membership',
                    'type'              => 'selections',
                    'options'           => $spaceOptions,
                    'is_multiple'       => true,
                    'is_singular_value' => true
                ]
            ]
        ];

        if (Helper::isFeatureEnabled('course_module')) {
            $courseOptions = Course::orderBy('title', 'ASC')->get()->pluck('title', 'id')->toArray();

            $group['children'][] = [
                'label'             => __('Course Enrollment', 'fluent-community-pro'),
                'value'             => 'course_enrollment',
                'type'              => 'selections',
                'options'           => $courseOptions,
                'is_multiple'       => true,
                'is_singular_value' => true
            ];
        }

        $groups['fcom'] = $group;
        return $groups;
    }

    public function setContactConditions($query, $filters)
    {
        foreach ($filters as $filter) {
            $value = array_filter($filter['value']);
            if (!$value) {
                continue;
            }

            $property = $filter['property'];

            $operator = $filter['operator'];

            if ($property == 'membership' || $property == 'course_enrollment') {
                $method = ($operator == 'in' || $operator == 'contains') ? 'whereHas' : 'whereDoesntHave';
                $query = $query->{$method}('user', function ($userQuery) use ($value) {
                    return $userQuery->whereExists(function ($subQuery) use ($value) {
                        global $wpdb;
                        return $subQuery->select(fluentCrmDb()->raw(1))
                            ->from('fcom_space_user')
                            ->whereRaw("{$wpdb->prefix}fcom_space_user.user_id = {$wpdb->prefix}users.ID")
                            ->whereIn('fcom_space_user.space_id', $value)
                            ->where('fcom_space_user.status', 'active');
                    });
                });
            }
        }
        return $query;
    }

    public function checkFunnelCondition($result, $conditions, $subscriber)
    {
        $wpUserId = $subscriber->getWpUserId();
        if (!$wpUserId) {
            return false;
        }

        foreach ($conditions as $condition) {
            $prop = $condition['data_key'];
            if ($prop == 'membership' || $prop == 'course_enrollment') {
                $spaceIds = Helper::getUserSpaceIds($wpUserId);
            } else {
                return false;
            }

            $inputs = [];
            $inputs[$prop] = $spaceIds;

            if (!ConditionAssessor::assess($condition, $inputs)) {
                return false;
            }
        }

        return $result;
    }

    public function pushContactBulkActions($actions)
    {
        $spaces = Space::orderBy('title', 'ASC')->get();
        $formattedSpaces = [];
        foreach ($spaces as $space) {
            $formattedSpaces[$space->id] = $space->title;
        }

        $actions[] = [
            'label'          => __('[FluentCommunity] Add to Space', 'fluent-community-pro'),
            'action_name'    => 'add_to_community',
            'btn_text'       => __('Add to selected Space', 'fluent-community-pro'),
            'is_multiple'    => false,
            'custom_options' => $formattedSpaces,
            'help_message'   => __('Will apply to the contacts who are already a registered Site User', 'fluent-community-pro')
        ];

        $actions[] = [
            'label'          => __('[FluentCommunity] Remove from a Space', 'fluent-community-pro'),
            'action_name'    => 'remove_from_community_space',
            'btn_text'       => __('Remove from selected Space', 'fluent-community-pro'),
            'is_multiple'    => false,
            'custom_options' => $formattedSpaces
        ];

        if (Helper::isFeatureEnabled('course_module')) {
            $courses = Course::orderBy('title', 'ASC')->get();
            $formattedCourses = [];
            foreach ($courses as $course) {
                $formattedCourses[$course->id] = $course->title;
            }

            $actions[] = [
                'label'          => __('[FluentCommunity] Add to Course', 'fluent-community-pro'),
                'action_name'    => 'add_to_community_course',
                'btn_text'       => __('Add to selected Course', 'fluent-community-pro'),
                'is_multiple'    => false,
                'custom_options' => $formattedCourses
            ];

            $actions[] = [
                'label'          => __('[FluentCommunity] Remove from Course', 'fluent-community-pro'),
                'action_name'    => 'remove_from_community_course',
                'btn_text'       => __('Remove from selected Course', 'fluent-community-pro'),
                'is_multiple'    => false,
                'custom_options' => $formattedCourses
            ];
        }

        if (Helper::isFeatureEnabled('user_badge')) {

            $badges = Utility::getOption('user_badges', []);
            $formattedBadges = [];
            foreach ($badges as $badge) {
                $formattedBadges[$badge['slug']] = $badge['title'] ?? $badge['slug'];
            }

            $actions[] = [
                'label'          => __('[FluentCommunity] Add Badge to Profile', 'fluent-community-pro'),
                'action_name'    => 'add_badge_to_community_users',
                'btn_text'       => __('Add Selected Badges to users', 'fluent-community-pro'),
                'is_multiple'    => false,
                'custom_options' => $formattedBadges
            ];

            $actions[] = [
                'label'          => __('[FluentCommunity] Remove Badge from Profile', 'fluent-community-pro'),
                'action_name'    => 'remove_badge_to_community_users',
                'btn_text'       => __('Remove Selected Badges from users', 'fluent-community-pro'),
                'is_multiple'    => false,
                'custom_options' => $formattedBadges
            ];
        }

        return $actions;
    }

    public function handleAddToCommunity($response, $subscriberIds, $options)
    {

        $spaceId = (int)Arr::get($options, 'new_status');
        if (!$spaceId) {
            return $response;
        }

        $space = Space::find($spaceId);
        if (!$space) {
            return $response;
        }

        $subscribers = Subscriber::whereIn('id', $subscriberIds)->get();

        foreach ($subscribers as $subscriber) {
            $userId = $subscriber->getWpUserId();
            if (!$userId) {
                continue;
            }

            if (Helper::isUserInSpace($userId, $spaceId)) {
                continue;
            }

            $user = User::find($userId);
            $user->syncXProfile(false); // just in case we need to sync the xprofile

            $space->members()->attach($userId, [
                'role'   => 'member',
                'status' => 'active'
            ]);

            do_action('fluent_community/space/joined', $space, $userId, 'by_admin');
        }

        return [
            'message' => __('Selected registered users has been added to the space successfully', 'fluent-community-pro')
        ];
    }

    public function handleAddBadge($response, $subscriberIds, $options)
    {
        $badgeSlug = Arr::get($options, 'new_status');
        if (!$badgeSlug) {
            return $response;
        }

        $badges = Utility::getOption('user_badges', []);

        if (!isset($badges[$badgeSlug])) {
            return $response;
        }

        $subscribers = Subscriber::whereIn('id', $subscriberIds)->get();

        foreach ($subscribers as $subscriber) {
            $userId = $subscriber->getWpUserId();
            if (!$userId) {
                continue;
            }

            $user = User::find($userId);

            $user->syncXProfile(false); // just in case we need to sync the xprofile

            $xprofile = $user->xprofile;

            $meta = $xprofile->meta;

            $existingBadges = (array)Arr::get($meta, 'badge_slug', []);
            $meta['badge_slug'] = array_unique(array_merge($existingBadges, [$badgeSlug]));

            $xprofile->meta = $meta;
            $xprofile->save();
        }

        return [
            'message' => __('The Badge has been added to the selected users', 'fluent-community-pro')
        ];
    }

    public function handleRemoveBadge($response, $subscriberIds, $options)
    {
        $badgeSlug = Arr::get($options, 'new_status');
        if (!$badgeSlug) {
            return $response;
        }

        $badges = Utility::getOption('user_badges', []);

        if (!isset($badges[$badgeSlug])) {
            return $response;
        }

        $subscribers = Subscriber::whereIn('id', $subscriberIds)->get();

        foreach ($subscribers as $subscriber) {
            $userId = $subscriber->getWpUserId();
            if (!$userId) {
                continue;
            }

            $user = User::find($userId);

            $xprofile = $user->xprofile;
            if (!$xprofile) {
                continue;
            }

            $xprofile = $user->xprofile;

            $meta = $xprofile->meta;

            $existingBadges = (array)Arr::get($meta, 'badge_slug', []);
            $meta['badge_slug'] = array_unique(array_diff($existingBadges, [$badgeSlug]));
            $xprofile->meta = $meta;
            $xprofile->save();
        }

        return [
            'message' => __('The Badge has been removed from the selected users', 'fluent-community-pro')
        ];
    }

    public function handleRemoveFromCommunity($response, $subscriberIds, $options)
    {
        $spaceId = (int)Arr::get($options, 'new_status');
        if (!$spaceId) {
            return $response;
        }

        $space = Space::find($spaceId);
        if (!$space) {
            return $response;
        }

        $subscribers = Subscriber::whereIn('id', $subscriberIds)->get();

        foreach ($subscribers as $subscriber) {
            $userId = $subscriber->getWpUserId();
            if (!$userId) {
                continue;
            }

            if (!Helper::isUserInSpace($userId, $spaceId)) {
                continue;
            }

            SpaceUserPivot::bySpace($space->id)
                ->byUser($userId)
                ->delete();

            do_action('fluent_community/space/user_left', $space, $userId, 'by_admin');
        }

        return [
            'message' => __('Selected users has been removed from the space successfully', 'fluent-community-pro')
        ];
    }

    public function handleAddToCourse($response, $subscriberIds, $options)
    {
        $courseId = (int)Arr::get($options, 'new_status');
        if (!$courseId) {
            return $response;
        }

        $course = Course::find($courseId);
        if (!$course) {
            return $response;
        }

        $subscribers = Subscriber::whereIn('id', $subscriberIds)->get();

        foreach ($subscribers as $subscriber) {
            $userId = $subscriber->getWpUserId();
            if (!$userId) {
                continue;
            }

            CourseHelper::enrollCourse($course, $userId, 'by_admin');
        }

        return [
            'message' => __('Selected registered users has been added to the course successfully', 'fluent-community-pro')
        ];
    }

    public function handleRemoveFromCourse($response, $subscriberIds, $options)
    {
        $courseId = (int)Arr::get($options, 'new_status');
        if (!$courseId) {
            return $response;
        }

        $course = Course::find($courseId);
        if (!$course) {
            return $response;
        }

        $subscribers = Subscriber::whereIn('id', $subscriberIds)->get();

        foreach ($subscribers as $subscriber) {
            $userId = $subscriber->getWpUserId();
            if (!$userId) {
                continue;
            }

            CourseHelper::leaveCourse($course, $userId, 'by_admin');
        }

        return [
            'message' => __('Selected registered users has been removed from the course successfully', 'fluent-community-pro')
        ];
    }

    public function pushCrmProfile($content, $userId)
    {

        if (!$userId) {
            return '';
        }
        if (!\FluentCrm\App\Services\PermissionManager::currentUserCan('fcrm_read_contacts')) {
            return $content;
        }

        $profile = FluentCrmApi('contacts')->getContactByUserRef($userId);
        if (!$profile) {
            return $content;
        }

        $profileUrl = fluentcrm_menu_url_base('subscribers/' . $profile->id);
        $tags = $profile->tags;
        $lists = $profile->lists;

        $stats = $profile->stats();

        $lifeTimeValue = apply_filters('fluent_crm/contact_lifetime_value', 0, $profile);
        if ($lifeTimeValue) {
            $lifeTimeValue = apply_filters('fluentcrm_currency_sign', '') . ' ' . number_format_i18n($lifeTimeValue, 2);
        }
        ob_start();
        ?>
        <div class="app_side_widget">
            <div class="widget_header">
                <h3>
                    <?php esc_html_e('CRM Profile', 'fluent-community-pro'); ?>
                </h3>
                <div class="widget_actions">
                    <a target="_blank" rel="noopener" title="View Contact" href="<?php echo esc_url($profileUrl); ?>"
                       class="el-button el-button--small <?php echo $profile->status == 'subscribed' ? 'el-button--primary' : 'el-button--danger' ?>">
                        <?php echo esc_html(ucfirst($profile->status)); ?>
                    </a>
                </div>
            </div>
            <div class="widget_body">
                <div style="margin-top: 20px;" class="widget_body_item">
                    <?php if ($lifeTimeValue): ?>
                        <div style="margin-bottom: 10px; text-align: center;">
                                <span
                                    style="color: var(--fcom-text-link, #2271b1); padding: 3px 10px; border: 1px solid; border-radius: 3px;">
                                    <?php esc_html_e('Lifetime Value', 'fluent-community-pro'); ?>: <?php echo esc_html($lifeTimeValue); ?>
                                </span>
                        </div>
                    <?php endif; ?>
                    <div class="fc_stats" style="text-align: center">
                        <?php foreach ($stats as $statKey => $stat): ?>
                            <span><?php echo esc_html(ucfirst($statKey)); ?>: <?php echo esc_html($stat); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if (!$lists->isEmpty()): ?>
                    <div class="widget_body_item">
                        <h4 style="font-size: 0.9rem; line-height: 1.2rem;"><?php esc_html_e('Lists', 'fluent-community-pro'); ?></h4>
                        <div class="widget_body_item_content">
                            <div class="fc_taggables">
                                <?php foreach ($lists as $list) : ?>
                                    <span><?php echo esc_html($list->title); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!$tags->isEmpty()): ?>
                    <div class="widget_body_item">
                        <h4 style="font-size: 0.9rem; line-height: 1.2rem;"><?php esc_html_e('Tags', 'fluent-community-pro'); ?></h4>
                        <div class="widget_body_item_content">
                            <div class="fc_taggables">
                                <?php foreach ($tags as $tag) : ?>
                                    <span><?php echo esc_html($tag->title); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <div style="text-align: center;" class="fcom_meta_content">
                    <p class="fcom_meta_text"><?php esc_html_e('Visible for admin only', 'fluent-community-pro'); ?></p>
                </div>
            </div>
        </div>

        <style>
            .fc_stats {
                list-style: none;
                margin-bottom: 20px;
                padding: 0;
                box-sizing: border-box;
                border-radius: 3px;
            }

            .fc_stats span {
                border: 1px solid #d9ecff;
                margin: 0 -4px 0px 0px;
                padding: 3px 6px;
                display: inline-block;
                background: #ecf5ff;
                color: #7d7f82;
                font-size: 12px;
                line-height: 12px;
            }

            .fc_taggables span {
                border: 1px solid;
                margin-right: 4px;
                padding: 2px 5px;
                display: inline-block;
                margin-bottom: 10px;
                font-size: 11px;
                line-height: 12px;
                border-radius: 3px;
                color: var(--el-text-color-regular);
            }
        </style>

        <?php
        return $content . ob_get_clean();
    }

}
