<?php

namespace FluentCommunity\Modules\Gutenberg;

use FluentCommunity\App\Services\Helper;
use FluentCommunity\App\Vite;
use FluentCommunity\Framework\Support\Arr;

class EditorBlock
{
    public function register($app)
    {
        add_action('init', [$this, 'registerBlock']);
    }

    public function registerBlock()
    {
        global $pagenow;

        $asset_file = include(plugin_dir_path(__FILE__) . 'build/index.asset.php');
        wp_register_style(
            'custom-layout-block-editor-style',
            plugins_url('build/index.css', __FILE__),
            array(),
            $asset_file['version']
        );

        wp_register_style('fluent_community_global', Vite::getDynamicSrcUrl('global.scss', Helper::isRtl()), [], FLUENT_COMMUNITY_PLUGIN_VERSION, 'screen');

        wp_register_script(
            'custom-layout-block-editor',
            plugins_url('build/index.js', __FILE__),
            $asset_file['dependencies'],
            $asset_file['version'],
            true
        );

        wp_localize_script(
            'custom-layout-block-editor',
            'fluentCommunityBlockEditor',
            array(
                'blockEditor' => array(
                    'isGutenberg'   => true,
                    'isBlockEditor' => true,
                    'isPage'        => $pagenow == 'post.php' || $pagenow == 'post-new.php',
                    'isPostType'    => get_post_type(),
                ),
            )
        );

        if (function_exists('\register_block_type')) {
            \register_block_type(
                'fluent-community/page-layout',
                array(
                    'editor_script'   => 'custom-layout-block-editor',
                    'editor_style'    => 'custom-layout-block-editor-style',
                    //'style'           => 'fluent_community_global',
                    'render_callback' => [$this, 'render'],
                    'supports'        => [
                        'html' => false
                    ],
                    'attributes'      => array(
                        'showPageHeader'      => array(
                            'type'    => 'boolean',
                            'default' => true,
                        ),
                        'useFullWidth'        => array(
                            'type'    => 'boolean',
                            'default' => false,
                        ),
                        'hideCommunityHeader' => array(
                            'type'    => 'boolean',
                            'default' => false,
                        ),
                        'useBuildInTheme'     => array(
                            'type'    => 'boolean',
                            'default' => true,
                        ),
                    ),
                )
            );
        }

        $this->registerBuiltInTemplate();
    }

    private function registerBuiltInTemplate()
    {
        if (!wp_is_block_theme() || !function_exists('\register_block_template')) {
            return;
        }

        $supportedPostTypes = apply_filters('fluent_communuty/block_templates_post_types', ['page', 'post']);

        register_block_template('fluent-community//frame-template', [
            'title'       => __('FluentCommunity Page Template', 'fluent-community'),
            'description' => __('A Page Template that render your WP content into FluentCommunity UI Frame', 'fluent-community'),
            'content'     => '<!-- wp:fluent-community/page-layout --><!-- wp:post-title /--><!-- wp:post-content /--><!-- /wp:fluent-community/page-layout -->',
            'post_types'  => $supportedPostTypes
        ]);

        add_filter('get_block_templates', function ($templates) {
            if (isset($templates['fluent-community//frame-template'])) {
                return array_values($templates);
            }
            return $templates;
        });

    }

    public function render($attributes, $content)
    {
        static $isLoaded;
        if ($isLoaded) {
            return 'Layout is already loaded before';
        }

        if (!$isLoaded) {
            $isLoaded = true;
        }

        $contenx = 'wp';
        if (isset($_REQUEST['context']) && $_REQUEST['context'] == 'edit' && Helper::isSiteAdmin()) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $contenx = 'block_editor';
        }

        $useBuildInTheme = $attributes['useBuildInTheme'] ?? false;

        do_action('fluent_community/enqueue_global_assets', $useBuildInTheme);
        $useFullWidth = $attributes['useFullWidth'] ?? true;
        $hideCommunityHeader = false;
        $className = Arr::get($attributes, 'className', '');

        $widthClass = $useFullWidth ? 'fcom_template_full' : 'fcom_template_standard';

        $disableDarkMode = Arr::has($attributes, 'disableDarkMode');

        if ($disableDarkMode) {
            add_filter('body_class', function ($classes) {
                $classes[] = 'fcom_disable_dark_mode';
                return $classes;
            });
        } else if (Helper::hasColorScheme()) {
            add_action('wp_head', function () {
                ?>
                <script>
                    (function () {
                        var globalStates = localStorage.getItem('fcom_global_storage');
                        if (globalStates) {
                            globalStates = JSON.parse(globalStates);
                            if (globalStates && globalStates.fcom_color_mode == 'dark') {
                                document.documentElement.classList.add('dark');
                                document.documentElement.setAttribute('data-color-mode', 'dark');
                            }
                        }
                    })();
                </script>
                <?php
            });
        }

        ob_start();
        ?>
        <div class="fcom_wrap fcom_wp_frame">
            <?php do_action('fluent_community/before_portal_dom'); ?>
            <div class="fluent_com">
                <div class="fhr_wrap">
                    <?php if (!$hideCommunityHeader): ?>
                        <?php do_action('fluent_community/portal_header', $contenx); ?>
                    <?php endif; ?>
                    <div class="fhr_content">
                        <div class="fhr_home">
                            <div class="feed_layout">
                                <div class="spaces">
                                    <div id="fluent_community_sidebar_menu" class="space_contents">
                                        <?php do_action('fluent_community/portal_sidebar', $contenx); ?>
                                    </div>
                                </div>
                                <div
                                    class="feeds_main fcom_wp_content fcom_fallback_wp_content <?php echo esc_attr($className); ?> <?php echo esc_attr($widthClass); ?>">
                                    <?php echo wp_kses_post(do_blocks($content)); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function useCommunityTemplate($title, $contentCallback = null)
    {
        do_action('fluent_community/enqueue_global_assets', true);
        $useFullWidth = false;
        $hideCommunityHeader = false;

        ob_start();
        ?>
        <div class="fluent_com">
            <div class="fhr_wrap">
                <?php if (!$hideCommunityHeader): ?>
                    <?php do_action('fluent_community/portal_header', 'headless'); ?>
                <?php endif; ?>
                <div class="fhr_content">
                    <div class="fhr_home">
                        <div class="feed_layout">
                            <div class="spaces">
                                <div id="fluent_community_sidebar_menu" class="space_contents">
                                    <!--                                    start-->
                                    <?php do_action('fluent_community/portal_sidebar', 'headless'); ?>
                                    <!--                                    end-->
                                </div>
                            </div>
                            <div class="fcom_wp_page">
                                <?php if ($title): ?>
                                    <div class="fhr_content_layout_header">
                                        <div class="fhr_page_title"><?php echo wp_kses_post($title); ?></div>
                                    </div>
                                <?php endif; ?>
                                <div
                                    class="wp_content_wrapper <?php echo $useFullWidth ? 'wp_content_wrapper_full' : ''; ?>">
                                    <div class="wp_content">
                                        <?php if ($contentCallback): ?>
                                            <?php call_user_func($contentCallback); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
