<?php

namespace FluentCommunity\App\Functions;

use FluentCommunity\App\App;
use FluentCommunity\App\Models\Meta;
use FluentCommunity\App\Models\Space;
use FluentCommunity\App\Models\SpaceGroup;
use FluentCommunity\App\Models\Term;
use FluentCommunity\App\Services\FeedsHelper;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\Modules\Course\Model\Course;

class Utility
{
    public static function isDev()
    {
        static $isDev = null;
        if ($isDev !== null) {
            return $isDev;
        }

        $config = App::getInstance()->config;
        $isDev = $config->get('app.env') === 'dev';
        return $isDev;
    }

    public static function getApp($instance = null)
    {
        return \FluentCommunity\App\App::getInstance($instance);
    }

    public static function extender()
    {
        return new FluentExtendApi();
    }

    /**
     * Get Global Fluent Community Option
     * @param string $key The option name
     * @param mixed $default the default value of the option if option is not available
     * @return mixed
     */
    public static function getOption($key, $default = null)
    {
        return self::getFromCache('option_' . $key, function () use ($key, $default) {
            $exist = \FluentCommunity\App\Models\Meta::where('object_type', 'option')
                ->where('meta_key', $key)
                ->first();

            if ($exist) {
                return $exist->value;
            }

            return $default;
        });
    }

    /**
     * Update Global Fluent Community Option
     * @param string $key The option name
     * @param mixed $value the value of the option
     * @return \FluentCommunity\App\Models\Meta
     */
    public static function updateOption($key, $value)
    {
        $exist = \FluentCommunity\App\Models\Meta::where('object_type', 'option')
            ->where('meta_key', $key)
            ->first();
        if ($exist) {
            $exist->value = $value;
            $exist->save();

        } else {
            $exist = \FluentCommunity\App\Models\Meta::create([
                'meta_key'    => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                'object_type' => 'option',
                'value'       => $value
            ]);
        }

        self::setCache('option_' . $key, $value);

        return $exist;
    }

    public static function deleteOption($key)
    {
        \FluentCommunity\App\Models\Meta::where('object_type', 'option')
            ->where('meta_key', $key)
            ->delete();

        self::forgetCache('option_' . $key);
    }

    public static function getFeaturesConfig()
    {
        static $features = null;

        if ($features) {
            return $features;
        }

        $features = self::getOption('fluent_community_features', []);

        // Default to every module being available so the plugin runs with the full
        // feature set out of the box.
        $defaults = [
            'leader_board_module' => 'yes',
            'course_module'       => 'yes',
            'giphy_module'        => 'yes',
            'giphy_api_key'       => '',
            'emoji_module'        => 'yes',
            'cloud_storage'       => 'yes',
            'invitation'          => 'yes',
            'user_badge'          => 'yes',
            'has_crm_sync'        => 'yes',
            'content_moderation'  => 'yes',
            'followers_module'    => 'yes'
        ];

        if (defined('FLUENT_COMMUNITY_CLOUD_STORAGE') && FLUENT_COMMUNITY_CLOUD_STORAGE) {
            $features['cloud_storage'] = 'yes';
        }

        $features = wp_parse_args($features, $defaults);

        $hasPro = defined('FLUENT_COMMUNITY_PRO') && FLUENT_COMMUNITY_PRO;

        // Ensure all premium toggles stay enabled even if stored options were disabled.
        if ($hasPro) {
            foreach ($defaults as $featureKey => $defaultValue) {
                $features[$featureKey] = 'yes';
            }
        } else {
            $features['leader_board_module'] = 'no';
            $features['giphy_module'] = 'no';
            $features['emoji_module'] = 'no';
            $features['cloud_storage'] = 'no';
            $features['user_badge'] = 'no';
            $features['followers_module'] = 'no';
        }

        return $features;
    }

    /**
     * @param $key
     * @param $callback
     * @param $expire
     * @return false|mixed
     * @internal Internal Function
     */
    public static function getFromCache($key, $callback = false, $expire = 3600)
    {
        $key = 'focm_' . $key;

        $value = wp_cache_get($key, 'fluent_community');

        if ($value !== false) {
            return $value;
        }

        if ($callback) {
            $value = $callback();
            if ($value) {
                wp_cache_set($key, $value, 'fluent_community', $expire);
            }
        }

        return $value;
    }

    /**
     * @param $key
     * @param $value
     * @param $expire
     * @return bool
     * @internal Internal Function
     */
    public static function setCache($key, $value, $expire = 600)
    {
        $key = 'focm_' . $key;

        return wp_cache_set($key, $value, 'fluent_community', $expire);
    }

    /**
     * @param $key
     * @return bool
     * @internal Internal Function
     */
    public static function forgetCache($key)
    {
        $key = 'focm_' . $key;
        return wp_cache_delete($key, 'fluent_community');
    }

    public static function getCustomizationSettings()
    {
        static $settings;

        if ($settings) {
            return $settings;
        }

        $defaults = [
            'dark_mode'            => 'yes',
            'fixed_page_header'    => 'yes',
            'show_powered_by'      => 'yes',
            'feed_link_on_sidebar' => 'yes',
            'show_post_modal'      => 'yes',
            'fixed_sidebar'        => 'no',
            'icon_on_header_menu'  => 'no',
            'affiliate_id'         => '',
            'rich_post_layout'     => 'classic',
            'member_list_layout'   => 'classic', // grid, classic
            'default_feed_layout'  => 'timeline', // list, timeline
            'post_title_pref'      => 'optional',
            'max_media_per_post'   => 4,
            'disable_feed_sort_by' => 'no',
            'default_feed_sort_by' => ''
        ];

        $settings = self::getOption('customization_settings', $defaults);

        $settings = wp_parse_args($settings, $defaults);

        if (!defined('FLUENT_COMMUNITY_PRO')) {
            $settings['show_powered_by'] = 'yes';
            $settings['affiliate_id'] = '';
            $settings['rich_post_layout'] = 'classic';
            $settings['member_list_layout'] = 'classic';
        }

        return $settings;
    }

    public static function getCustomizationSetting($key)
    {
        $settings = self::getCustomizationSettings();
        return Arr::get($settings, $key);
    }

    public static function updateCustomizationSettings($settings)
    {
        $preSettings = self::getCustomizationSettings();
        $settings = Arr::only($settings, array_keys($preSettings));

        self::updateOption('customization_settings', $settings);

        return $settings;
    }

    public static function getPrivacySettings()
    {
        static $settings;

        if ($settings) {
            return $settings;
        }

        $defaults = [
            'can_customize_username'         => 'no',
            'can_change_email'               => 'no',
            'show_last_activity'             => 'yes',
            'can_deactive_account'           => 'no',
            'email_auto_login'               => 'yes',
            'enable_gravatar'                => 'yes',
            'enable_user_sync'               => 'yes',
            'members_page_status'            => 'everybody', // everybody, logged_in, admin_only
            'user_space_visibility'          => 'everybody', // everybody, logged_in, admin_only
            'leaderboard_members_visibility' => 'everybody', // everybody, logged_in, admin_only
        ];

        $settings = self::getOption('privacy_settings', $defaults);

        $settings = wp_parse_args($settings, $defaults);

        if (!defined('FLUENT_COMMUNITY_PRO')) {
            $settings['can_customize_username'] = 'no';
            $settings['can_change_email'] = 'no';
            $settings['email_auto_login'] = 'no';
        }

        return $settings;
    }

    public static function canViewMembersPage()
    {
        $pageStatus = self::getPrivacySetting('members_page_status');

        if ($pageStatus == 'everybody') {
            return apply_filters('fluent_community/can_view_members_page', true, $pageStatus);
        }

        if ($pageStatus == 'logged_in') {
            return apply_filters('fluent_community/can_view_members_page', is_user_logged_in(), $pageStatus);
        }

        return apply_filters('fluent_community/can_view_members_page', Helper::isModerator(), $pageStatus);
    }

    public static function canViewLeaderboardMembers()
    {
        $pageStatus = self::getPrivacySetting('leaderboard_members_visibility');

        if ($pageStatus == 'everybody') {
            return apply_filters('fluent_community/can_view_leaderboard_members', true, $pageStatus);
        }

        if ($pageStatus == 'logged_in') {
            return apply_filters('fluent_community/can_view_leaderboard_members', is_user_logged_in(), $pageStatus);
        }

        return apply_filters('fluent_community/can_view_leaderboard_members', Helper::isModerator(), $pageStatus);
    }

    public static function getPrivacySetting($key)
    {
        $settings = self::getPrivacySettings();
        return Arr::get($settings, $key);
    }

    public static function updatePrivacySettings($settings)
    {
        $preSettings = self::getPrivacySettings();
        $settings = Arr::only($settings, array_keys($preSettings));

        self::updateOption('privacy_settings', $settings);
        self::forgetCache('privacy_settings');

        return $settings;
    }

    public static function isCustomizationEnabled($key, $matchingValue = 'yes')
    {
        $settings = self::getCustomizationSettings();
        return isset($settings[$key]) && $settings[$key] === $matchingValue;
    }

    public static function getProductUrl($isPowered = false, $params = [])
    {
        $url = 'https://fluentcommunity.co/';
        if ($isPowered) {
            $defaultParams = [
                'utm_source'   => 'power_footer',
                'utm_medium'   => 'site',
                'utm_campaign' => 'powered_by'
            ];
            $params = wp_parse_args($params, $defaultParams);

            $settings = self::getCustomizationSettings();
            $affId = Arr::get($settings, 'affiliate_id');
            if ($affId) {
                $params['ref'] = $affId;
            }

            return add_query_arg($params, $url);
        }

        return add_query_arg([
            'utm_source'   => 'plugin',
            'utm_medium'   => 'site',
            'utm_campaign' => 'plugin_ui'
        ], $url);
    }

    /**
     * Get the email notification settings.
     *
     * @return array The email notification settings.
     */
    public static function getEmailNotificationSettings()
    {
        static $settings;
        if ($settings) {
            return $settings;
        }

        $default = [
            'com_my_post_mail'    => 'yes',
            'reply_my_com_mail'   => 'yes',
            'mention_mail'        => 'yes',
            'digest_email_status' => 'no',
            'digest_mail_day'     => 'tue',
            'daily_digest_time'   => '09:00',
            'send_from_email'     => '',
            'send_from_name'      => '',
            'reply_to_email'      => '',
            'reply_to_name'       => '',
            'email_footer'        => 'You are getting this email because you are a member of {{site_name_with_url}}.' . PHP_EOL . PHP_EOL . '{{manage_email_notification_url|Manage Your Email Notifications Preference}}.',
            'disable_powered_by'  => 'no',
            'logo'                => ''
        ];

        $settings = array_filter(self::getOption('global_email_settings', $default));
        $settings = wp_parse_args($settings, $default);

        if (!defined('FLUENT_COMMUNITY_PRO')) {
            $settings['disable_powered_by'] = 'no';
        }

        if (empty($settings['email_footer_rendered']) && !empty($settings['email_footer'])) {
            $settings['email_footer_rendered'] = FeedsHelper::mdToHtml($settings['email_footer']);
        }

        return $settings;
    }

    public static function hasEmailAnnouncementEnabled()
    {
        $settings = self::getEmailNotificationSettings();
        return Arr::get($settings, 'mention_mail', 'no') === 'yes';
    }

    public static function postTitlePref()
    {
        $pref = self::getCustomizationSetting('post_title_pref');

        if ($pref == 'disabled') {
            $pref = '';
        }

        return apply_filters('fluent_community/has_post_title', $pref);
    }

    public static function getSpaces($byGroups = false)
    {
        if ($byGroups) {
            return SpaceGroup::orderBy('serial', 'ASC')->with('spaces', function ($q) {
                $q->orderBy('serial', 'ASC')
                    ->where('type', 'community');
            })->get();
        }

        return Space::where('type', 'community')->orderBy('serial', 'ASC')->get();
    }

    public static function getCourses($byGroups = false)
    {
        if ($byGroups) {
            return SpaceGroup::orderBy('serial', 'ASC')->with('spaces', function ($q) {
                $q->orderBy('serial', 'ASC')
                    ->where('type', 'course');
            })->get();
        }

        return Course::orderBy('serial', 'ASC')->get();
    }

    public static function getTopics()
    {
        static $topics;
        if ($topics) {
            return $topics;
        }

        $key = 'fluent_community_post_topics';
        $topics = self::getFromCache($key, function () {
            $topics = Term::where('taxonomy_name', 'post_topic')->orderBy('title', 'ASC')->get();

            /*
             *  object_id = term_id
             *  meta_key = space_id
             */
            $topicSpaceRelations = Meta::select(['id', 'object_id', 'meta_key'])->where('object_type', 'term_space_relation')
                ->get();

            $relations = [];
            foreach ($topicSpaceRelations as $relation) {
                if (!isset($relations[$relation->object_id])) {
                    $relations[$relation->object_id] = [];
                }

                $relations[$relation->object_id][] = $relation->meta_key;
            }

            $formattedTopics = [];
            foreach ($topics as $topic) {
                $formattedTopics[] = [
                    'id'          => $topic->id,
                    'title'       => $topic->title,
                    'description' => $topic->description,
                    'slug'        => $topic->slug,
                    'admin_only'  => Arr::get($topic->settings, 'admin_only', 'no'),
                    'space_ids'   => isset($relations[$topic->id]) ? $relations[$topic->id] : []
                ];
            }

            return $formattedTopics;
        }, MONTH_IN_SECONDS);

        return $topics;
    }

    public static function getTopicsBySpaceId($spaceId)
    {
        $topics = self::getTopics();
        $topics = array_filter($topics, function ($topic) use ($spaceId) {
            return in_array($spaceId, $topic['space_ids']);
        });
        return array_values($topics);
    }

    public static function getMaxRunTime()
    {
        if (function_exists('ini_get')) {
            $maxRunTime = (int)ini_get('max_execution_time');
            if ($maxRunTime === 0) {
                $maxRunTime = 60;
            }
            // If set to 0 (unlimited) or a negative value, return a large number
            if ($maxRunTime <= 0) {
                return PHP_INT_MAX;
            }

        } else {
            $maxRunTime = 30;
        }

        if ($maxRunTime > 58) {
            $maxRunTime = 58;
        }

        $maxRunTime = $maxRunTime - 3;

        return apply_filters('fluent_community/max_execution_time', $maxRunTime);
    }

    public static function getColorSchemas()
    {
        $defaultCustom = [
            'title'     => __('Custom', 'fluent-community'),
            'selectors' => [
                'body'          => [],
                'fcom_top_menu' => [],
                'spaces'        => []
            ],
        ];
        $shemas = apply_filters('fluent-community/color_schemas', [
            'lightSkins' => [
                'default'         => [
                    'title'     => __('Default', 'fluent-community'),
                    'selectors' => [
                        'body'          => [
                            'primary_bg'           => '#ffffff',
                            'secondary_bg'         => '#f0f2f5',
                            'secondary_content_bg' => '#f0f3f5',
                            'active_bg'            => '#f0f3f5',
                            'light_bg'             => '#E1E4EA',
                            'deep_bg'              => '#222530',
                            'menu_text'            => '#545861',
                            'primary_text'         => '#19283a',
                            'secondary_text'       => '#525866',
                            'text_off'             => '#959595',
                            'primary_button'       => '#2B2E33',
                            'primary_button_text'  => '#ffffff',
                            'primary_border'       => '#e3e8ee',
                            'secondary_border'     => '#9CA3AF',
                            'highlight_bg'         => '#fffce3',
                            'text_link'            => '#2271b1',
                        ],
                        'fcom_top_menu' => [
                            'primary_bg'       => '#ffffff',
                            'primary_border'   => '#e3e8ee',
                            'menu_text'        => '#545861',
                            'menu_text_active' => '#545861',
                            'active_bg'        => '#f0f3f5',
                            'menu_text_hover'  => '#545861',
                            'menu_bg_hover'    => '#f0f3f5',
                        ],
                        'spaces'        => [
                            'primary_bg'       => '#ffffff',
                            'primary_border'   => '#e3e8ee',
                            'menu_text'        => '#545861',
                            'menu_text_active' => '#545861',
                            'menu_text_hover'  => '#545861',
                            'menu_bg_hover'    => '#f0f3f5',
                            'active_bg'        => '#f0f3f5',
                        ]
                    ],
                ],
                'sunset_sands'    => [
                    'title'     => __('Sunset Sands', 'fluent-community'),
                    'selectors' => [
                        'body'          => [
                            'primary_bg'           => '#FFFFFF',
                            'secondary_bg'         => '#FDF6F0',
                            'secondary_content_bg' => '#FDF6F0',
                            'active_bg'            => '#FCF0E6',
                            'light_bg'             => '#FAE5D3',
                            'deep_bg'              => '#2D2D2D',
                            'menu_text'            => '#5D5D5D',
                            'primary_text'         => '#333333',
                            'secondary_text'       => '#666666',
                            'text_off'             => '#999999',
                            'text_link'            => '#E67E22',
                            'primary_button'       => '#E67E22',
                            'primary_button_text'  => '#FFFFFF',
                            'primary_border'       => '#EADDD3',
                            'secondary_border'     => '#E0D0C3',
                            'highlight_bg'         => '#FFF5EC',
                        ],
                        'fcom_top_menu' => [
                            'primary_bg'       => '#FFFFFF',
                            'primary_border'   => '#EADDD3',
                            'menu_text'        => '#5D5D5D',
                            'menu_text_active' => '#E67E22',
                            'active_bg'        => '#FFF5EC',
                            'menu_text_hover'  => '#E67E22',
                            'menu_bg_hover'    => '#FFF5EC',
                        ],
                        'spaces'        => [
                            'primary_bg'       => '#FFFFFF',
                            'primary_border'   => '#EADDD3',
                            'menu_text'        => '#5D5D5D',
                            'menu_text_hover'  => '#E67E22',
                            'menu_bg_hover'    => '#FFF5EC',
                            'menu_text_active' => '#FFFFFF',
                            'active_bg'        => '#E67E22',
                        ]
                    ]
                ],
                'ocean_blue'      => [
                    'title'     => __('Ocean Blue', 'fluent-community'),
                    'selectors' => [
                        'body'          => [
                            'primary_bg'           => '#FFFFFF',
                            'secondary_bg'         => '#F0F2F5',
                            'secondary_content_bg' => '#f0f3f5',
                            'active_bg'            => '#E7F3FF',
                            'light_bg'             => '#F6F9FA',
                            'deep_bg'              => '#18191A',
                            'menu_text'            => '#65676B',
                            'primary_text'         => '#050505',
                            'secondary_text'       => '#65676B',
                            'text_off'             => '#8A8D91',
                            'text_link'            => '#216FDB',
                            'primary_button'       => '#1877F2',
                            'primary_button_text'  => '#FFFFFF',
                            'primary_border'       => '#DADDE1',
                            'secondary_border'     => '#CED0D4',
                            'highlight_bg'         => '#E7F3FF'
                        ],
                        'fcom_top_menu' => [
                            'primary_bg'       => '#FFFFFF',
                            'primary_border'   => '#DADDE1',
                            'menu_text'        => '#65676B',
                            'menu_text_active' => '#1877F2',
                            'active_bg'        => '#E7F3FF',
                            'menu_text_hover'  => '#1877F2',
                            'menu_bg_hover'    => '#E7F3FF',
                        ],
                        'spaces'        => [
                            'primary_bg'       => '#FFFFFF',
                            'primary_border'   => '#DADDE1',
                            'menu_text'        => '#65676B',
                            'menu_text_hover'  => '#1877F2',
                            'menu_bg_hover'    => '#E7F3FF',
                            'menu_text_active' => '#FFFFFF',
                            'active_bg'        => '#1877F2'
                        ]
                    ]
                ],
                'sky_blue'        => [
                    'title'     => __('Sky Blue', 'fluent-community'),
                    'selectors' => [
                        'body'          => [
                            'primary_bg'           => '#FFFFFF',
                            'secondary_bg'         => '#F7F9FA',
                            'secondary_content_bg' => '#f0f3f5',
                            'active_bg'            => '#E8F5FD',
                            'light_bg'             => '#F7F9FA',
                            'deep_bg'              => '#15202B',
                            'menu_text'            => '#536471',
                            'primary_text'         => '#0F1419',
                            'secondary_text'       => '#536471',
                            'text_off'             => '#8899A6',
                            'text_link'            => '#1D9BF0',
                            'primary_button'       => '#1D9BF0',
                            'primary_button_text'  => '#FFFFFF',
                            'primary_border'       => '#EFF3F4',
                            'secondary_border'     => '#CFD9DE',
                            'highlight_bg'         => '#E8F5FD'
                        ],
                        'fcom_top_menu' => [
                            'primary_bg'       => '#FFFFFF',
                            'primary_border'   => '#EFF3F4',
                            'menu_text'        => '#536471',
                            'menu_text_active' => '#1D9BF0',
                            'active_bg'        => '#E8F5FD',
                            'menu_bg_hover'    => '#E8F5FD',
                            'menu_text_hover'  => '#536471',
                        ],
                        'spaces'        => [
                            'primary_bg'       => '#FFFFFF',
                            'primary_border'   => '#EFF3F4',
                            'menu_text'        => '#536471',
                            'menu_text_hover'  => '#1D9BF0',
                            'menu_bg_hover'    => '#E8F5FD',
                            'menu_text_active' => '#FFFFFF',
                            'active_bg'        => '#1D9BF0'
                        ]
                    ]
                ],
                'emerald_essence' => [
                    'title'     => __('Emerald Essence', 'fluent-community'),
                    'selectors' => [
                        'body'          => [
                            'primary_bg'           => '#FFFFFF',
                            'secondary_bg'         => '#F0F7F4',
                            'secondary_content_bg' => '#f0f3f5',
                            'active_bg'            => '#E3F2ED',
                            'light_bg'             => '#F7FAFA',
                            'deep_bg'              => '#1A2B32',
                            'menu_text'            => '#4A5D5E',
                            'primary_text'         => '#1F2937',
                            'secondary_text'       => '#4B5563',
                            'text_off'             => '#9CA3AF',
                            'text_link'            => '#059669',
                            'primary_button'       => '#10B981',
                            'primary_button_text'  => '#FFFFFF',
                            'primary_border'       => '#D1E7DD',
                            'secondary_border'     => '#A7C4BC',
                            'highlight_bg'         => '#ECFDF5'
                        ],
                        'fcom_top_menu' => [
                            'primary_bg'       => '#FFFFFF',
                            'primary_border'   => '#D1E7DD',
                            'menu_text'        => '#4A5D5E',
                            'menu_text_active' => '#059669',
                            'active_bg'        => '#ECFDF5',
                            'menu_bg_hover'    => '#ECFDF5',
                            'menu_text_hover'  => '#4A5D5E',
                        ],
                        'spaces'        => [
                            'primary_bg'       => '#FFFFFF',
                            'primary_border'   => '#D1E7DD',
                            'menu_text'        => '#4A5D5E',
                            'menu_text_hover'  => '#059669',
                            'menu_bg_hover'    => '#ECFDF5',
                            'menu_text_active' => '#FFFFFF',
                            'active_bg'        => '#10B981'
                        ]
                    ]
                ]
            ],
            'darkSkins'  => [
                'default'         => [
                    'title'     => __('Default (Dark)', 'fluent-community'),
                    'selectors' => [
                        'body'          => [
                            'primary_bg'           => '#2B2E33',
                            'secondary_bg'         => '#191B1F',
                            'secondary_content_bg' => '#42464D',
                            'active_bg'            => '#42464D',
                            'light_bg'             => '#2B303B',
                            'deep_bg'              => '#E1E4EA',
                            'menu_text'            => '#E4E7EB',
                            'menu_text_active'     => '#E4E7EB',
                            'primary_text'         => '#F0F3F5',
                            'secondary_text'       => '#99A0AE',
                            'menu_bg_hover'        => '#E1E4EA',
                            'text_off'             => '#A5A9AD',
                            'text_link'            => '#60a5fa',
                            'primary_button'       => '#FFFFFF',
                            'primary_button_text'  => '#2B2E33',
                            'primary_border'       => '#42464D',
                            'secondary_border'     => '#A5A9AD',
                            'highlight_bg'         => '#2c2c1a',
                        ],
                        'fcom_top_menu' => [
                            'primary_bg'       => '#2B2E33',
                            'primary_border'   => '#42464D',
                            'menu_text'        => '#E4E7EB',
                            'menu_text_active' => '#E4E7EB',
                            'active_bg'        => '#42464D',
                            'menu_bg_hover'    => '#42464D',
                            'menu_text_hover'  => '#E4E7EB',
                        ],
                        'spaces'        => [
                            'primary_bg'       => '#2B2E33',
                            'primary_border'   => '#42464D',
                            'menu_text'        => '#E4E7EB',
                            'menu_text_active' => '#E4E7EB',
                            'active_bg'        => '#42464D',
                            'menu_bg_hover'    => '#42464D',
                            'menu_text_hover'  => '#fff',
                        ]
                    ],
                ],
                'sunset_sands'    => [
                    'title'     => __('Sunset Sands (Dark)', 'fluent-community'),
                    'selectors' => [
                        'body'          => [
                            'primary_bg'           => '#1A1A1A',
                            'secondary_bg'         => '#222222',
                            'secondary_content_bg' => '#222222',
                            'active_bg'            => '#2A2420',
                            'light_bg'             => '#33302E',
                            'deep_bg'              => '#111111',
                            'menu_text'            => '#B0B0B0',
                            'primary_text'         => '#E0E0E0',
                            'secondary_text'       => '#A0A0A0',
                            'text_off'             => '#707070',
                            'text_link'            => '#F39C12',
                            'primary_button'       => '#F39C12',
                            'primary_border'       => '#3A3632',
                            'primary_button_text'  => '#1A1A1A',
                            'secondary_border'     => '#4C4D4F',
                            'highlight_bg'         => '#2A2420',
                        ],
                        'fcom_top_menu' => [
                            'primary_bg'       => '#1A1A1A',
                            'primary_border'   => '#3A3632',
                            'menu_text'        => '#B0B0B0',
                            'menu_text_active' => '#F39C12',
                            'active_bg'        => '#2A2420',
                            'menu_text_hover'  => '#F39C12',
                            'menu_bg_hover'    => '#2A2420',
                        ],
                        'spaces'        => [
                            'primary_bg'       => '#1A1A1A',
                            'primary_border'   => '#3A3632',
                            'menu_text'        => '#B0B0B0',
                            'menu_text_hover'  => '#F39C12',
                            'menu_bg_hover'    => '#2A2420',
                            'menu_text_active' => '#1A1A1A',
                            'active_bg'        => '#F39C12',
                        ]
                    ]
                ],
                'ocean_blue'      => [
                    'title'     => __('Ocean Blue (Dark)', 'fluent-community'),
                    'selectors' => [
                        'body'          => [
                            'primary_border'       => '#3E4042',
                            'menu_text'            => '#B0B3B8',
                            'menu_text_active'     => '#2D88FF',
                            'active_bg'            => '#263951',
                            'menu_text_hover'      => '#2D88FF',
                            'menu_bg_hover'        => '#263951',
                            'primary_bg'           => '#2B2E33',
                            'secondary_content_bg' => '#42464D',
                            'secondary_bg'         => '#191B1F',
                            'light_bg'             => '#2B303B',
                            'deep_bg'              => '#E1E4EA',
                            'primary_text'         => '#F0F3F5',
                            'secondary_text'       => '#99A0AE',
                            'text_off'             => '#A5A9AD',
                            'primary_button'       => '#FFFFFF',
                            'primary_button_text'  => '#191B1F',
                            'secondary_border'     => '#A5A9AD',
                            'highlight_bg'         => '#2c2c1a',
                            'text_link'            => '#2d88ff',
                        ],
                        'fcom_top_menu' => [
                            'primary_bg'       => '#242526',
                            'primary_border'   => '#3E4042',
                            'menu_text'        => '#B0B3B8',
                            'menu_text_active' => '#fff',
                            'active_bg'        => '#263951',
                            'menu_text_hover'  => '#fff',
                            'menu_bg_hover'    => '#263951',
                        ],
                        'spaces'        => [
                            'primary_bg'       => '#242526',
                            'primary_border'   => '#3E4042',
                            'menu_text'        => '#B0B3B8',
                            'menu_text_hover'  => '#2D88FF',
                            'menu_bg_hover'    => '#263951',
                            'menu_text_active' => '#FFFFFF',
                            'active_bg'        => '#2D88FF'
                        ]
                    ]
                ],
                'sky_blue'        => [
                    'title'     => __('Sky Blue (Dark)', 'fluent-community'),
                    'selectors' => [
                        'body'          => [
                            'primary_bg'           => '#15202B',
                            'secondary_bg'         => '#1E2732',
                            'secondary_content_bg' => '#2C3640',
                            'active_bg'            => '#1D2F41',
                            'light_bg'             => '#22303C',
                            'deep_bg'              => '#E7E9EA',
                            'menu_text'            => '#8B98A5',
                            'primary_text'         => '#E7E9EA',
                            'secondary_text'       => '#8B98A5',
                            'text_off'             => '#536471',
                            'text_link'            => '#1D9BF0',
                            'primary_button'       => '#1D9BF0',
                            'primary_button_text'  => '#15202B',
                            'primary_border'       => '#38444D',
                            'secondary_border'     => '#536471',
                            'highlight_bg'         => '#1D2F41'
                        ],
                        'fcom_top_menu' => [
                            'primary_bg'       => '#15202B',
                            'primary_border'   => '#38444D',
                            'menu_text'        => '#8B98A5',
                            'menu_text_active' => '#1D9BF0',
                            'active_bg'        => '#1D2F41',
                            'menu_bg_hover'    => '#1D2F41',
                            'menu_text_hover'  => '#E7E9EA',
                        ],
                        'spaces'        => [
                            'primary_bg'       => '#15202B',
                            'primary_border'   => '#38444D',
                            'menu_text'        => '#8B98A5',
                            'menu_text_hover'  => '#1D9BF0',
                            'menu_bg_hover'    => '#1D2F41',
                            'menu_text_active' => '#FFFFFF',
                            'active_bg'        => '#1D9BF0'
                        ]
                    ]
                ],
                'emerald_essence' => [
                    'title'     => __('Emerald Essence (Dark)', 'fluent-community'),
                    'selectors' => [
                        'body'          => [
                            'primary_bg'           => '#1A2B32',
                            'secondary_bg'         => '#243B43',
                            'secondary_content_bg' => '#2C464F',
                            'active_bg'            => '#1E3A31',
                            'light_bg'             => '#2A3F48',
                            'deep_bg'              => '#E2E8F0',
                            'menu_text'            => '#A7BCBF',
                            'primary_text'         => '#E2E8F0',
                            'secondary_text'       => '#A7BCBF',
                            'text_off'             => '#64748B',
                            'text_link'            => '#34D399',
                            'primary_button'       => '#10B981',
                            'primary_border'       => '#2F4C41',
                            'primary_button_text'  => '#1A2B32',
                            'secondary_border'     => '#3D5C52',
                            'highlight_bg'         => '#064E3B'
                        ],
                        'fcom_top_menu' => [
                            'primary_bg'       => '#1A2B32',
                            'primary_border'   => '#2F4C41',
                            'menu_text'        => '#A7BCBF',
                            'menu_text_active' => '#34D399',
                            'active_bg'        => '#064E3B',
                            'menu_bg_hover'    => '#064E3B',
                            'menu_text_hover'  => '#E2E8F0',
                        ],
                        'spaces'        => [
                            'primary_bg'       => '#1A2B32',
                            'primary_border'   => '#2F4C41',
                            'menu_text'        => '#A7BCBF',
                            'menu_text_hover'  => '#34D399',
                            'menu_bg_hover'    => '#064E3B',
                            'menu_text_active' => '#FFFFFF',
                            'active_bg'        => '#10B981'
                        ]
                    ]
                ]
            ]
        ]);

        $customSchemaConfig = self::getColorConfig('edit');

        $shemas['lightSkins']['custom'] = [
            'title'     => __('Custom', 'fluent-community'),
            'selectors' => $customSchemaConfig['light_config'] ?? $defaultCustom
        ];

        $shemas['darkSkins']['custom'] = [
            'title'     => __('Custom', 'fluent-community'),
            'selectors' => $customSchemaConfig['dark_config'] ?? $defaultCustom
        ];

        return $shemas;
    }

    public static function generateCss($sectors, $prefix = '')
    {
        if (!$sectors) {
            return '';
        }

        $bodyPrefix = 'body';
        if ($prefix) {
            $bodyPrefix = $prefix . ' body';
        }

        $elementPlusMap = [
            'primary_text' => '--el-color-primary',
            'secondary_bg' => '--el-color-white'
        ];

        $css = '';
        foreach ($sectors as $selector => $props) {
            $isBody = $selector === 'body';
            $prefix = $isBody ? $bodyPrefix : $bodyPrefix . ' .' . $selector;
            $prefix .= '{';

            $css .= $prefix;

            foreach ($props as $prop => $value) {
                $cssVar = '--fcom-' . str_replace('_', '-', $prop);
                $css .= $cssVar . ':' . $value . ';';

                if ($isBody && isset($elementPlusMap[$prop])) {
                    $css .= $elementPlusMap[$prop] . ':' . $value . ';';
                }
            }
            $css .= '} ';
        }

        return $css;
    }

    public static function getColorConfig($context = 'view')
    {
        return apply_filters('fluent_community/color_schmea_config', [
            'light_schema' => 'default',
            'dark_schema'  => 'default',
            'light_config' => [
                'body'          => [],
                'fcom_top_menu' => [],
                'spaces'        => []
            ],
            'dark_config'  => [
                'body'          => [],
                'fcom_top_menu' => [],
                'spaces'        => []
            ],
            'version'      => FLUENT_COMMUNITY_PLUGIN_VERSION
        ], $context);
    }

    public static function getColorCssVariables()
    {
        $customSchemaConfig = self::getColorConfig('view');

        if (!empty($customSchemaConfig['cached_css'])) {
            if ($customSchemaConfig['version'] != FLUENT_COMMUNITY_PLUGIN_VERSION) {
                do_action('fluent_community/recache_color_schema');
            }

            return (string)$customSchemaConfig['cached_css'];
        }

        $schemas = self::getColorSchemas();

        $lightName = Arr::get($customSchemaConfig, 'light_schema', 'default');
        $darkName = Arr::get($customSchemaConfig, 'dark_schema', 'default');

        $lightCss = self::generateCss(Arr::get($schemas, "lightSkins.$lightName.selectors"));
        $darkCss = self::generateCss(Arr::get($schemas, "darkSkins.$darkName.selectors"), 'html.dark');
        return $lightCss . $darkCss;
    }

    public static function getColorSchemaConfig()
    {
        $customSchemaConfig = self::getColorConfig('view');
        $lightName = Arr::get($customSchemaConfig, 'light_schema', 'default');
        $darkName = Arr::get($customSchemaConfig, 'dark_schema', 'default');
        $schemas = self::getColorSchemas();

        return [
            'dark'  => Arr::get($schemas, "lightSkins.$darkName.selectors"),
            'light' => Arr::get($schemas, "darkSkins.$lightName.selectors"),
        ];
    }

    public static function getSuggestedColors()
    {
        $pallets = current((array)get_theme_support('editor-color-palette'));

        $colors = [];

        if ($pallets && is_array($pallets)) {
            $colors = array_map(function ($color) {
                $colorString = Arr::get($color, 'color');
                // Trim any whitespace
                $colorString = trim($colorString);
                // Check if it's a CSS variable
                if (strpos($colorString, 'var(') === 0) {
                    // Extract the fallback color if present
                    preg_match('/var\(.*,\s*(#[A-Fa-f0-9]{6})\)/', $colorString, $matches);
                    if (!empty($matches[1])) {
                        return $matches[1];
                    }
                    // If no fallback color, return an empty string
                    return '';
                }

                if (preg_match('/#[A-Fa-f0-9]{6}/', $colorString, $matches)) {
                    return $matches[0];
                }

                return $colorString;
            }, $pallets);

            $colors = array_filter($colors);
        }

        if (!$colors) {
            $colors = ['#000000', '#abb8c3', '#ffffff', '#f78da7', '#ff6900', '#fcb900', '#7bdcb5', '#00d084', '#8ed1fc', '#0693e3', '#9b51e0'];
        }

        return apply_filters('fluent_community/suggested_colors', $colors);
    }

    public static function slugify($text, $fallback = '')
    {
        $title = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $text);
        $title = remove_accents($title);

        $title = strtolower($title);
        // only allow alphanumeric, dash, and underscore
        $title = trim(preg_replace('/[^a-z0-9-_]/', ' ', $title));

        if (!$title) {
            $title = $fallback;
        }

        if (!$title) {
            return $title;
        }

        return sanitize_title($title, $fallback);
    }

    public static function hasAnalyticsEnabled()
    {
        $defaultSettings = ['status' => 'no'];

        $settings = apply_filters('fluent_community/features/analytics', $defaultSettings);

        $status = Arr::get($settings, 'status');

        return $status === 'yes';
    }

    public static function getPortalSidebarData($scope = 'sidebar')
    {
        $userModel = Helper::getCurrentUser();
        $spaceGroups = Helper::getCommunityMenuGroups($userModel);
        $settingsMenu = apply_filters('fluent_community/settings_menu', [], $userModel);
        $menuGroups = Helper::getMenuItemsGroup('view');
        $topInlines = Arr::get($menuGroups, 'beforeCommunityMenuItems', []);
        $bottomLinkGroups = Arr::get($menuGroups, 'afterCommunityLinkGroups', []);
        $primaryMenuItems = Arr::get($menuGroups, 'mainMenuItems', []);
        $primaryMenuItems = apply_filters('fluent_community/main_menu_items', $primaryMenuItems, $scope);

        return apply_filters('fluent_community/sidebar_menu_groups_config', [
            'primaryItems'     => $primaryMenuItems,
            'spaceGroups'      => $spaceGroups,
            'settingsItems'    => $settingsMenu,
            'topInlineLinks'   => $topInlines,
            'bottomLinkGroups' => $bottomLinkGroups,
            'is_admin'         => Helper::isSiteAdmin(null, $userModel),
            'has_color_scheme' => Helper::hasColorScheme(),
            'context'          => $scope,
        ], $userModel);
    }

    public static function getVerifiedSenders()
    {
        $verifiedSenders = [];

        if (defined('FLUENTMAIL')) {
            $smtpSettings = get_option('fluentmail-settings', []);
            if ($smtpSettings && count($smtpSettings['mappings'])) {
                $verifiedSenders = array_keys($smtpSettings['mappings']);
            }
        }

        return apply_filters('fluent_community/verified_email_senders', $verifiedSenders);
    }

    public static function safeUnserialize($data)
    {
        if (!$data) {
            return $data;
        }

        if (is_serialized($data)) { // Don't attempt to unserialize data that wasn't serialized going in.
            return @unserialize(trim($data), [
                'allowed_classes' => false,
            ]);
        }

        return $data;
    }
}
