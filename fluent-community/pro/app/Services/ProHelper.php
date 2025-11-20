<?php

namespace FluentCommunityPro\App\Services;


use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Services\ProfileHelper;
use FluentCommunity\Framework\Support\Arr;

class ProHelper
{
    /*
     * Install Plugins with direct download link ( which doesn't have wordpress.org repo )
     */
    public static function backgroundInstallerDirect($plugin_to_install, $plugin_id, $downloadUrl)
    {
        if (!empty($plugin_to_install['repo-slug'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/plugin.php';

            \WP_Filesystem();

            $skin = new \Automatic_Upgrader_Skin();
            $upgrader = new \WP_Upgrader($skin);
            $installed_plugins = array_reduce(array_keys(\get_plugins()), array(static::class, 'associate_plugin_file'), array());
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
                    $package = $downloadUrl;
                    $download = $upgrader->download_package($package);

                    if (is_wp_error($download)) {
                        throw new \Exception($download->get_error_message());
                    }

                    $working_dir = $upgrader->unpack_package($download, true);

                    if (is_wp_error($working_dir)) {
                        throw new \Exception($working_dir->get_error_message());
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
                        throw new \Exception($result->get_error_message());
                    }

                    $activate = true;

                } catch (\Exception $e) {
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
                        throw new \Exception($result->get_error_message());
                    }
                } catch (\Exception $e) {
                }
            }
        }
    }

    private static function associate_plugin_file($plugins, $key)
    {
        $path = explode('/', $key);
        $filename = end($path);
        $plugins[$filename] = $key;
        return $plugins;
    }

    public static function createUserFromCrmContact(\FluentCrm\App\Models\Subscriber $subscriber)
    {
        $userName = ProfileHelper::createUserNameFromStrings($subscriber->email, array_filter([
            $subscriber->first_name,
            $subscriber->last_name
        ]));

        $userData = [
            'role'       => get_option('default_role', 'subscriber'),
            'user_email' => $subscriber->email,
            'user_login' => $userName,
            'user_pass'  => wp_generate_password(8, false),
            'first_name' => $subscriber->first_name,
            'last_name'  => $subscriber->last_name
        ];

        $userId = wp_insert_user($userData);

        if (is_wp_error($userId)) {
            return $userId;
        }

        return $userId;
    }

    public static function getSnippetsSettings()
    {
        $defaults = [
            'custom_css' => '',
            'custom_js' => '',
        ];

        $settings = Utility::getOption('snippets_settings', $defaults);

        $settings = wp_parse_args($settings, $defaults);

        return $settings;
    }

    public static function updateSnippetsSettings($settings)
    {
        $preSettings = self::getSnippetsSettings();

        $settings = Arr::only($settings, array_keys($preSettings));

        Utility::updateOption('snippets_settings', $settings);

        return $settings;
    }

    public static function sanitizeCSS($css)
    {
        return preg_match('#</?\w+#', $css) ? '' : $css;
    }

    public static function getCourseSmartCodes()
    {
        return apply_filters('fluent_community/course_smart_codes', [
            '{{section.title}}'           => __('Section Title', 'fluent-community'),
            '{{section.url}}'             => __('Section URL', 'fluent-community-pro'),
            '{{course.title}}'            => __('Course Title', 'fluent-community-pro'),
            '{{user.display_name}}'       => __('User Name', 'fluent-community-pro'),
            '{{community.name}}'          => __('Site Name', 'fluent-community-pro'),
            '{{community.name_with_url}}' => __('Site Name with URL', 'fluent-community-pro')
        ]);
    }

    public static function getDefaultCourseNotification()
    {
        return apply_filters('fluent_community/default_course_email_notification', [
            'subject' => '{{section.title}} is now available for you in {{course.title}}',
            'message' => 'Hi {{user.display_name}},' . PHP_EOL . PHP_EOL . '{{section.title}} is now available to you in {{course.title}}.' . PHP_EOL . 'To complete this section, please follow this link:' . PHP_EOL . '{{section.url}},' . PHP_EOL . PHP_EOL . 'Thanks,' . PHP_EOL .'{{community.name_with_url}}'
        ]);
    }
}
