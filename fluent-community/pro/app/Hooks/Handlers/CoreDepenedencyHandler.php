<?php

namespace FluentCommunityPro\App\Hooks\Handlers;

class CoreDepenedencyHandler
{
    public function register()
    {
        // add a link to admin menu which will redirect to /portal
        add_action('admin_menu', function () {
            add_menu_page(
                __('FluentCommunity', 'fluent-community-pro'),
                __('FluentCommunity', 'fluent-community-pro'),
                'edit_posts',
                'fluent-community',
                [$this, 'showAdminPage'],
                $this->getMenuIcon(),
                130
            );
        });

        add_action('wp_ajax_fcom_install_core_plugin', [$this, 'installCorePlugin']);
    }

    public function installCorePlugin()
    {
        // verify nonce
        if (!wp_verify_nonce($_POST['_nonce'], 'fcom_onboarding_nonce')) {
            wp_send_json(['message' => 'Invalid nonce'], 403);
        }

        if (defined('FLUENT_COMMUNITY_PLUGIN_VERSION')) {
            wp_send_json(['message' => 'Already installed'], 200);
        }

        $result = $this->installPlugin('fluent-community');

        if (is_wp_error($result)) {
            wp_send_json(['message' => $result->get_error_message()], 403);
        }

        wp_send_json(['message' => 'Installed'], 200);
    }

    public function showAdminPage()
    {
        wp_enqueue_script('fluent-community-pro-onboard', FLUENT_COMMUNITY_PRO_URL . 'assets/app.js', ['jquery'], FLUENT_COMMUNITY_PRO_VERSION, true);

        wp_localize_script('fluent-community-pro-onboard', 'fluentComAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            '_nonce'   => wp_create_nonce('fcom_onboarding_nonce'),
            'logo'     => FLUENT_COMMUNITY_PRO_URL . 'assets/logo.png',
        ]);

        wp_enqueue_style('fluent-community-pro-onboard', FLUENT_COMMUNITY_PRO_URL . 'assets/app.css', [], FLUENT_COMMUNITY_PRO_VERSION);
        echo '<div id="fcom_onboarding_app"></div>';
    }

    private function getMenuIcon()
    {
        return 'data:image/svg+xml;base64,' . base64_encode('<svg width="82" height="71" viewBox="0 0 82 71" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M25.9424 49.1832L39.6888 41.2467L47.6253 54.9931C40.0334 59.3763 30.3256 56.7751 25.9424 49.1832Z" fill="white"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M53.4348 33.3101L39.6884 41.2466L47.6249 54.993L61.3713 47.0565L53.4348 33.3101ZM67.1821 25.3734L53.4356 33.3099L61.3721 47.0564L75.1186 39.1199L67.1821 25.3734Z" fill="white"/>
<path d="M67.182 25.3736C70.978 23.182 75.8319 24.4826 78.0235 28.2786L81.9917 35.1518L75.1185 39.12L67.182 25.3736Z" fill="white"/>
<path d="M42.593 30.4052L28.8466 38.3417L20.9101 24.5953L34.6565 16.6588L42.593 30.4052Z" fill="white"/>
<path d="M56.3397 22.4683L42.5933 30.4048L34.6568 16.6584C42.2487 12.2752 51.9565 14.8764 56.3397 22.4683Z" fill="white"/>
<path d="M28.847 38.3418L15.1006 46.2783L7.16409 32.5318L20.9105 24.5953L28.847 38.3418Z" fill="white"/>
<path d="M15.1011 46.2783C11.3051 48.4699 6.4512 47.1693 4.25959 43.3733L0.291343 36.5001L7.16456 32.5319L15.1011 46.2783Z" fill="white"/>
</svg>');

    }


    private function installPlugin($pluginSlug)
    {
        $plugin = [
            'name'      => $pluginSlug,
            'repo-slug' => $pluginSlug,
            'file'      => $pluginSlug . '.php'
        ];

        $UrlMaps = [
            'fluent-community' => [
                'admin_url' => admin_url('admin.php?page=fluent-community'),
                'title'     => 'Go to FluentCommunity Dashboard',
            ],
        ];

        if (!isset($UrlMaps[$pluginSlug]) || (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS)) {
            return new \WP_Error('invalid_plugin', __('Invalid plugin or file mods are disabled.', 'fluent-community-pro'));
        }

        try {
            return $this->backgroundInstaller($plugin);
        } catch (\Exception $exception) {
            return new \WP_Error('plugin_install_error', $exception->getMessage());
        }
    }

    private function backgroundInstaller($plugin_to_install)
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
