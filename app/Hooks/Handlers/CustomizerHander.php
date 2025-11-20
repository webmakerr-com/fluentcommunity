<?php

namespace FluentCommunity\App\Hooks\Handlers;

use FluentCommunity\App\Services\Helper;
use FluentCommunity\App\Vite;
use FluentCommunity\Framework\Support\Arr;

class CustomizerHander
{
    public function register()
    {
        add_filter('fluent_community/portal_data_vars', function ($vars) {
            if (Arr::get($vars, 'route_group') == 'admin' && Helper::isSiteAdmin()) {
                add_action('fluent_community/before_header_right_menu_items', function () {
                    ?>
                    <li class="customizer_menu_item">
                        <a
                           href="<?php echo esc_url(Helper::baseUrl('/?customizer_panel=1')); ?>">
                           <?php esc_html_e('Customize Colors', 'fluent-community'); ?>
                        </a>
                    </li>
                    <li class="top_menu_item customizer_menu_button">
                        <button class="fcom_menu_button">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_3687_33463)"><path d="M18.3337 9.99984C18.3337 5.39746 14.6027 1.6665 10.0003 1.6665C5.39795 1.6665 1.66699 5.39746 1.66699 9.99984C1.66699 14.6022 5.39795 18.3332 10.0003 18.3332C10.7018 18.3332 11.667 18.4301 11.667 17.4998C11.667 16.9924 11.403 16.6008 11.1408 16.2119C10.7572 15.6428 10.3773 15.0793 10.8337 14.1665C11.3892 13.0554 12.3152 13.0554 13.7349 13.0554C14.4448 13.0554 15.2781 13.0554 16.2504 12.9165C18.0012 12.6664 18.3337 11.5902 18.3337 9.99984Z" stroke="currentColor" stroke-width="1.5"></path><path d="M5.83398 12.502L5.84121 12.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path><ellipse cx="7.91699" cy="7.08301" rx="1.25" ry="1.25" stroke="currentColor" stroke-width="1.5"></ellipse><ellipse cx="13.75" cy="7.9165" rx="1.25" ry="1.25" stroke="currentColor" stroke-width="1.5"></ellipse></g><defs><clipPath id="clip0_3687_33463"><rect width="20" height="20" fill="currentColor"></rect></clipPath></defs></svg>
                        </button>
                    </li>
                    <?php
                });
            } else if (isset($_GET['customizer_panel']) && Helper::isSiteAdmin()) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $vars['css_files'] = array_merge($vars['css_files'], [
                    'customizer'     => [
                        'url' => Vite::getStaticSrcUrl('customizer.css')
                    ],
                    'customizer_app' => [
                        'url' => Vite::getStaticSrcUrl('customizer_app.css')
                    ],
                ]);

                add_action('fluent_community/before_portal_rendered', [$this, 'pushCustomizer'], 10, 1);
                $vars['js_vars']['customizerI18n'] = [
                    'Light Mode'                                                                                                                                    => __('Light Mode', 'fluent-community'),
                    'Dark Mode'                                                                                                                                     => __('Dark Mode', 'fluent-community'),
                    'color_inst'                                                                                                                                    => __('The following styles will be applied when a member views your community in', 'fluent-community'),
                    'Exit'                                                                                                                                          => __('Exit', 'fluent-community'),
                    'Save Settings'                                                                                                                                 => __('Save Settings', 'fluent-community'),
                    'Color Schema'                                                                                                                                  => __('Color Schema', 'fluent-community'),
                    'Select Color Schema'                                                                                                                           => __('Select Color Schema', 'fluent-community'),
                    'Header'                                                                                                                                        => __('Header', 'fluent-community'),
                    'Background color of the header area'                                                                                                           => __('Background color of the header area', 'fluent-community'),
                    'Background'                                                                                                                                    => __('Background', 'fluent-community'),
                    'Link or Button colors of the header area'                                                                                                      => __('Link or Button colors of the header area', 'fluent-community'),
                    'Text/Link'                                                                                                                                     => __('Text/Link', 'fluent-community'),
                    'Background color of the active item in the top menu'                                                                                           => __('Background color of the active item in the top menu', 'fluent-community'),
                    'Active Item Background'                                                                                                                        => __('Active Item Background', 'fluent-community'),
                    'Text color of the active item in the top menu'                                                                                                 => __('Text color of the active item in the top menu', 'fluent-community'),
                    'Active Item Color'                                                                                                                             => __('Active Item Color', 'fluent-community'),
                    'Background color of the hovered item in the top menu'                                                                                          => __('Background color of the hovered item in the top menu', 'fluent-community'),
                    'Hover Background'                                                                                                                              => __('Hover Background', 'fluent-community'),
                    'Text color of the hovered item in the top menu'                                                                                                => __('Text color of the hovered item in the top menu', 'fluent-community'),
                    'Hover Color'                                                                                                                                   => __('Hover Color', 'fluent-community'),
                    'The main background color of the sidebar'                                                                                                      => __('The main background color of the sidebar', 'fluent-community'),
                    'The text/link color of the sidebar'                                                                                                            => __('The text/link color of the sidebar', 'fluent-community'),
                    'Background color of the active item in the sidebar'                                                                                            => __('Background color of the active item in the sidebar', 'fluent-community'),
                    'Text color of the active item in the sidebar'                                                                                                  => __('Text color of the active item in the sidebar', 'fluent-community'),
                    'Background color of the hovered item in the sidebar'                                                                                           => __('Background color of the hovered item in the sidebar', 'fluent-community'),
                    'Text color of the hovered item in the sidebar'                                                                                                 => __('Text color of the hovered item in the sidebar', 'fluent-community'),
                    'Background color of the main area'                                                                                                             => __('Background color of the main area', 'fluent-community'),
                    'Body Background'                                                                                                                               => __('Body Background', 'fluent-community'),
                    'Background color of the primary content area like sub header and post content'                                                                 => __('Background color of the primary content area like sub header and post content', 'fluent-community'),
                    'Primary Content Background'                                                                                                                    => __('Primary Content Background', 'fluent-community'),
                    'Background color of the secondary content area like each comment'                                                                              => __('Background color of the secondary content area like each comment', 'fluent-community'),
                    'Secondary Content Background'                                                                                                                  => __('Secondary Content Background', 'fluent-community'),
                    'Text color of the main area this includes post, comment, headings'                                                                             => __('Text color of the main area this includes post, comment, headings', 'fluent-community'),
                    'Text Color'                                                                                                                                    => __('Text Color', 'fluent-community'),
                    'Text color of the secondary area this includes post meta, comment meta'                                                                        => __('Text color of the secondary area this includes post meta, comment meta', 'fluent-community'),
                    'Off Text Color'                                                                                                                                => __('Off Text Color', 'fluent-community'),
                    'Border color of the main sections'                                                                                                             => __('Border color of the main sections', 'fluent-community'),
                    'Primary Border Color'                                                                                                                          => __('Primary Border Color', 'fluent-community'),
                    'order color of the secondary sections'                                                                                                         => __('order color of the secondary sections', 'fluent-community'),
                    'Secondary Border Color'                                                                                                                        => __('Secondary Border Color', 'fluent-community'),
                    'Navigations'                                                                                                                                   => __('Navigations', 'fluent-community'),
                    'Color of the links in the main content area.'                                                                                                  => __('Color of the links in the main content area.', 'fluent-community'),
                    'Link Color'                                                                                                                                    => __('Link Color', 'fluent-community'),
                    'Background color of the primary buttons. This includes the buttons in the post, comment, and forms.'                                           => __('Background color of the primary buttons. This includes the buttons in the post, comment, and forms.', 'fluent-community'),
                    'Primary Button Background'                                                                                                                     => __('Primary Button Background', 'fluent-community'),
                    'Text color of the primary buttons. This includes the buttons in the post, comment, and forms.'                                                 => __('Text color of the primary buttons. This includes the buttons in the post, comment, and forms.', 'fluent-community'),
                    'Primary Button Text Color'                                                                                                                     => __('Primary Button Text Color', 'fluent-community'),
                    'Background color of the secondary buttons. This includes the buttons in the space, user profile, and other secondary headers.'                 => __('Background color of the secondary buttons. This includes the buttons in the space, user profile, and other secondary headers.', 'fluent-community'),
                    'Secondary Nav Text Color'                                                                                                                      => __('Secondary Nav Text Color', 'fluent-community'),
                    'Background color of the secondary buttons on active state. This includes the buttons in the space, user profile, and other secondary headers.' => __('Background color of the secondary buttons on active state. This includes the buttons in the space, user profile, and other secondary headers.', 'fluent-community'),
                    'Secondary Nav Active Background'                                                                                                               => __('Secondary Nav Active Background', 'fluent-community'),
                    'Text color of the secondary buttons on active state. This includes the buttons in the space, user profile, and other secondary headers.'       => __('Text color of the secondary buttons on active state. This includes the buttons in the space, user profile, and other secondary headers.', 'fluent-community'),
                    'Secondary Nav Active Color'                                                                                                                    => __('Secondary Nav Active Color', 'fluent-community'),
                    'Sidebar'                                                                                                                                       => __('Sidebar', 'fluent-community'),
                    'General'                                                                                                                                       => __('General', 'fluent-community'),
                    'Save Settings (Pro Required)'                                                                                                                  => __('Save Settings (Pro Required)', 'fluent-community'),
                ];
            }

            return $vars;
        });
    }

    public function pushCustomizer($data)
    {
        add_action('fluent_community/portal_footer', function () {
            $jsFiles = [
                'customizer_app' => [
                    'url' => Vite::getDynamicSrcUrl('customizer/customizer_app.js')
                ],
            ];

            foreach ($jsFiles as $file) {
                ?>
                <script type="module"
                        src="<?php echo esc_url($file['url']); ?>?version=<?php echo esc_attr(FLUENT_COMMUNITY_PLUGIN_VERSION); ?>"
                        defer="defer"></script>
                <?php
            }
        }, 1);

        add_action('fluent_community/before_portal_dom', function () {
            ?>
            <div id="fcom_customizer_panel">

            </div>
            <?php
        });
    }

}
