<?php

namespace FluentCommunity\App\Services;

use FluentCommunity\App\Models\Space;
use FluentCommunity\App\Models\SpaceGroup;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\Modules\Course\Model\Course;

class OnboardingService
{
    public static function maybeCreateSpaceTemplates($template = '')
    {
        $spaceCount = Space::count();
        if ($spaceCount >= 2) {
            return false;
        }

        if (!$template || $template == 'blank') {
            return false;
        }

        self::createDefaultSpaceTemplate();

        if ($template == 'course') {
            self::createCourseTemplate();
        } else if ($template == 'product') {
            self::createProductTemplate();
        }

        return true;
    }

    protected static function createDefaultSpaceTemplate()
    {
        $spaceGroupData = [
            'title'       => 'Get Started',
            'slug'        => 'get-started',
            'description' => 'General Discussion Group',
            'status'      => 'active',
            'type'        => 'space_group',
            'settings'    => [
                'hide_members'       => 'no',
                'always_show_spaces' => 'yes'
            ],
            'serial'      => 1
        ];

        $spaceGroup = SpaceGroup::where('slug', 'get-started')->first();
        if (!$spaceGroup) {
            $spaceGroup = SpaceGroup::create($spaceGroupData);
        }

        $spacesData = [
            [
                'title'       => 'Start Here',
                'slug'        => 'start-here',
                'privacy'     => 'public',
                'description' => '',
                'settings'    => [
                    'restricted_post_only' => 'no',
                    'emoji'                => '🏠',
                    'can_request_join'     => 'yes',
                    'custom_lock_screen'   => 'no',
                    'layout_style'         => 'timeline',
                    'show_sidebar'         => 'yes'
                ],
                'parent_id'   => $spaceGroup->id,
                'serial'      => 1
            ],
            [
                'title'       => 'Say Hello',
                'slug'        => 'say-hello',
                'privacy'     => 'public',
                'description' => '',
                'settings'    => [
                    'restricted_post_only' => 'no',
                    'emoji'                => '👋',
                    'can_request_join'     => 'yes',
                    'custom_lock_screen'   => 'no',
                    'layout_style'         => 'timeline',
                    'show_sidebar'         => 'yes'
                ],
                'parent_id'   => $spaceGroup->id,
                'serial'      => 2
            ]
        ];

        foreach ($spacesData as $spaceData) {
            $space = Space::where('slug', $spaceData['slug'])->first();
            if ($space) {
                continue;
            }

            $space = Space::create($spaceData);
            Helper::addToSpace($space, get_current_user_id(), 'admin');
        }

        return true;
    }

    protected static function createCourseTemplate()
    {
        $spaceGroupData = [
            'title'       => 'Courses',
            'slug'        => 'courses',
            'description' => 'Course Learning Modules',
            'status'      => 'active',
            'type'        => 'space_group',
            'settings'    => [
                'always_show_spaces' => 'yes'
            ],
            'serial'      => 2
        ];

        $spaceGroup = SpaceGroup::where('slug', 'courses')->first();

        if (!$spaceGroup) {
            $spaceGroup = SpaceGroup::create($spaceGroupData);
        } else {
            $spaceGroup = SpaceGroup::create($spaceGroupData);
        }

        $coursesData = [
            [
                'title'       => 'Demo Course 1',
                'slug'        => 'course-1',
                'privacy'     => 'private',
                'status'      => 'draft',
                'description' => '',
                'settings'    => [
                    'course_type' => 'self_paced',
                    'emoji'       => '1️⃣',
                    'shape_svg'   => ''
                ],
                'parent_id'   => $spaceGroup->id,
                'serial'      => 1
            ],
            [
                'title'       => 'Module 2',
                'slug'        => 'module-2',
                'privacy'     => 'private',
                'status'      => 'draft',
                'description' => '',
                'settings'    => [
                    'course_type' => 'self_paced',
                    'emoji'       => '2️⃣',
                    'shape_svg'   => ''
                ],
                'parent_id'   => $spaceGroup->id,
                'serial'      => 2
            ]
        ];

        foreach ($coursesData as $courseData) {
            $course = Course::where('slug', $courseData['slug'])->first();
            if ($course) {
                continue;
            }

            Course::create($courseData);
        }
    }

    protected static function createProductTemplate()
    {
        $spaceGroupData = [
            'title'       => 'Product Discussions',
            'slug'        => 'product-discussions',
            'description' => 'Product Discussion Group',
            'status'      => 'active',
            'type'        => 'space_group',
            'settings'    => [
                'always_show_spaces' => 'yes'
            ],
            'serial'      => 2
        ];

        $spaceGroup = SpaceGroup::where('slug', 'product-discussions')->first();

        if (!$spaceGroup) {
            $spaceGroup = SpaceGroup::create($spaceGroupData);
        }

        $spacesData = [
            [
                'title'       => 'Give Feedback',
                'slug'        => 'give-feedback',
                'privacy'     => 'public',
                'description' => '',
                'settings'    => [
                    'restricted_post_only' => 'no',
                    'emoji'                => '💬',
                    'can_request_join'     => 'yes',
                    'custom_lock_screen'   => 'no',
                    'layout_style'         => 'timeline',
                    'show_sidebar'         => 'yes'
                ],
                'parent_id'   => $spaceGroup->id,
                'serial'      => 1
            ],
            [
                'title'       => 'Ask for Help',
                'slug'        => 'ask-for-help',
                'privacy'     => 'public',
                'description' => '',
                'settings'    => [
                    'restricted_post_only' => 'no',
                    'emoji'                => '💬',
                    'can_request_join'     => 'yes',
                    'custom_lock_screen'   => 'no',
                    'layout_style'         => 'timeline',
                    'show_sidebar'         => 'yes'
                ],
                'parent_id'   => $spaceGroup->id,
                'serial'      => 2
            ],
            [
                'title'       => 'Announcements',
                'slug'        => 'announcements',
                'privacy'     => 'public',
                'description' => '',
                'settings'    => [
                    'restricted_post_only' => 'no',
                    'emoji'                => '📣',
                    'can_request_join'     => 'yes',
                    'custom_lock_screen'   => 'no',
                    'layout_style'         => 'timeline',
                    'show_sidebar'         => 'yes'
                ],
                'parent_id'   => $spaceGroup->id,
                'serial'      => 3
            ],
        ];

        foreach ($spacesData as $spaceData) {
            $space = Space::where('slug', $spaceData['slug'])->first();
            if ($space) {
                continue;
            }

            $space = Space::create($spaceData);
            Helper::addToSpace($space, get_current_user_id(), 'admin');
        }
    }

    public static function installAddons($addons = [])
    {
        $validAddons = ['fluent-crm', 'fluent-smtp', 'fluent-cart'];
        $validAddons = array_intersect($validAddons, $addons);

        if (!$validAddons || !current_user_can('install_plugins')) {
            return;
        }

        foreach ($addons as $addon) {
            self::installPlugin($addon);
        }

        return true;
    }

    public static function maybeOptinUserToNewsletter($settings)
    {
        $isOptin = isset($settings['subscribe_to_newsletter']) && $settings['subscribe_to_newsletter'] === 'yes';
        if (!$isOptin) {
            return false;
        }

        $userFullName = Arr::get($settings, 'user_full_name');
        $userEmail = Arr::get($settings, 'user_email_address');

        if (!$userEmail) {
            return;
        }

        $url = 'https://fluentcommunity.co/discount-deal/?fluentcrm=1&route=contact&hash=8850223d-c62d-4e6a-8108-b04c7e9e4fdb';

        $response = wp_remote_post($url, [
            'body' => json_encode([ // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
                'full_name'       => $userFullName,
                'email'           => $userEmail,
                'source'          => 'fcom_plugin',
                'optin_website'   => home_url(),
                'share_essential' => Arr::get($settings, 'share_data', 'no') === 'yes' ? 'yes' : 'no',
            ])
        ]);

        return true;
    }

    private static function installPlugin($pluginSlug)
    {
        $plugin = [
            'name'      => $pluginSlug,
            'repo-slug' => $pluginSlug,
            'file'      => $pluginSlug . '.php'
        ];

        $UrlMaps = [
            'fluentform' => [
                'admin_url' => admin_url('admin.php?page=fluent_forms'),
                'title'     => 'Go to Fluent Forms Dashboard',
            ],
            'fluent-crm' => [
                'admin_url' => admin_url('admin.php?page=fluentcrm-admin'),
                'title'     => 'Go to FluentCRM Dashboard'
            ],
            'fluent-smtp' => [
                'admin_url' => admin_url('options-general.php?page=fluent-mail#/'),
                'title'     => 'Go to FluentSMTP Dashboard'
            ],
            'fluent-cart' => [
                'admin_url' => admin_url('admin.php?page=fluent-cart#/'),
                'title'     => 'Go to FluentCart Dashboard'
            ]
        ];

        if (!isset($UrlMaps[$pluginSlug]) || (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS)) {
            return new \WP_Error('invalid_plugin', __('Invalid plugin or file mods are disabled.', 'fluent-community'));
        }

        try {
            return self::backgroundInstaller($plugin);
        } catch (\Exception $exception) {
            return new \WP_Error('plugin_install_error', $exception->getMessage());
        }
    }

    private static function backgroundInstaller($plugin_to_install)
    {
        if (!empty($plugin_to_install['repo-slug'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/plugin.php';

            WP_Filesystem();

            $skin = new \Automatic_Upgrader_Skin();
            $upgrader = new \WP_Upgrader($skin);
            $installed_plugins = array_keys(\get_plugins());
            $plugin_slug = $plugin_to_install['repo-slug'];
            $plugin_file = isset($plugin_to_install['file']) ? $plugin_to_install['file'] : $plugin_slug . '.php';
            $installed = false;
            $activate = false;

            // See if the plugin is installed already.
            if (isset($installed_plugins[$plugin_file])) {
                $installed = true;
                $activate = !is_plugin_active($installed_plugins[$plugin_file]);
            }

            // Install this thing!
            if (!$installed) {
                // Suppress feedback.
                ob_start();

                try {
                    $plugin_information = plugins_api(
                        'plugin_information',
                        array(
                            'slug'   => $plugin_slug,
                            'fields' => array(
                                'short_description' => false,
                                'sections'          => false,
                                'requires'          => false,
                                'rating'            => false,
                                'ratings'           => false,
                                'downloaded'        => false,
                                'last_updated'      => false,
                                'added'             => false,
                                'tags'              => false,
                                'homepage'          => false,
                                'donate_link'       => false,
                                'author_profile'    => false,
                                'author'            => false,
                            ),
                        )
                    );

                    if (is_wp_error($plugin_information)) {
                        throw new \Exception(wp_kses_post($plugin_information->get_error_message()));
                    }

                    $package = $plugin_information->download_link;
                    $download = $upgrader->download_package($package);

                    if (is_wp_error($download)) {
                        throw new \Exception(wp_kses_post($download->get_error_message()));
                    }

                    $working_dir = $upgrader->unpack_package($download, true);

                    if (is_wp_error($working_dir)) {
                        throw new \Exception(wp_kses_post($working_dir->get_error_message()));
                    }

                    $result = $upgrader->install_package(
                        array(
                            'source'                      => $working_dir,
                            'destination'                 => WP_PLUGIN_DIR,
                            'clear_destination'           => false,
                            'abort_if_destination_exists' => false,
                            'clear_working'               => true,
                            'hook_extra'                  => array(
                                'type'   => 'plugin',
                                'action' => 'install',
                            ),
                        )
                    );

                    if (is_wp_error($result)) {
                        throw new \Exception(wp_kses_post($result->get_error_message()));
                    }

                    $activate = true;

                } catch (\Exception $e) {
                    throw new \Exception(esc_html($e->getMessage()));
                }

                // Discard feedback.
                ob_end_clean();
            }

            wp_clean_plugins_cache();

            // Activate this thing.
            if ($activate) {
                try {
                    $result = activate_plugin($installed ? $installed_plugins[$plugin_file] : $plugin_slug . '/' . $plugin_file);

                    if (is_wp_error($result)) {
                        throw new \Exception(esc_html($result->get_error_message()));
                    }
                } catch (\Exception $e) {
                    throw new \Exception(esc_html($e->getMessage()));
                }
            }
        }
    }
}
