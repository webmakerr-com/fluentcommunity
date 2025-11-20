<?php

namespace FluentCommunity\Modules\Theming;

use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Support\Arr;

class TemplateLoader
{
    public function register()
    {
        add_filter('fluent_community/general_portal_vars', function ($vars) {
            $templateName = get_option('template');
            if ($templateName == 'blocksy') {
                $vars['color_switch_cookie_name'] = 'blocksy_current_theme';
            }

            if ($templateName == 'kadence' && defined('KTP_VERSION')) {
                if (function_exists('\Kadence\kadence') && \Kadence\kadence()->option('dark_mode_enable') && apply_filters('kadence_dark_mode_enable', true)) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                    $cookie_name = substr(base_convert(md5(get_site_url()), 16, 32), 0, 12) . '-paletteCookie';
                    $vars['color_switch_cookie_name'] = $cookie_name;
                }
            }

            return $vars;
        });

        add_filter('theme_page_templates', [$this, 'registerTemplate'], 9999);
        add_filter('template_include', [$this, 'maybeIncludeTemplate'], 99999);

        add_action('fluent_community/theme_content', [$this, 'renderWpContent'], 10, 2);

        // support for blocksy
        add_action('fluent_community/theme_body_atts', function ($themeName) {
            if ($themeName == 'blocksy') {
                echo wp_kses_post(blocksy_body_attr());
            }
        });

        add_action('fluent_community/template_footer', [$this, 'renderMobileMenu']);
    }

    public function registerTemplate($templates)
    {
        if (wp_is_block_theme()) {
            return $templates;
        }

        $templates['fluent-community-frame.php'] = esc_html__('FluentCommunity Frame', 'fluent-community');
        $templates['fluent-community-frame-full.php'] = esc_html__('FluentCommunity Full Width Frame', 'fluent-community');
        return $templates;
    }

    public function maybeIncludeTemplate($template)
    {
        // check if the current theme is block based theme or not
        if (wp_is_block_theme()) {
            $template_slug = get_page_template_slug();
            if ($template_slug != 'wp-custom-template-community-template') {
                return $template;
            }
            return $this->loadBlockBasedTemplateAssets($template);
        }

        global $post;

        if (isset($post->ID)) {
            $template_slug = get_page_template_slug($post->ID);

            $fluentTemplates = [
                'fluent-community-frame.php',
                'fluent-community-frame-full.php',
            ];

            $template_slug = apply_filters('fluent_community/template_slug', $template_slug);

            if (in_array($template_slug, $fluentTemplates)) {
                // Determine the absolute path to the template file within your plugin.
                $plugin_template = plugin_dir_path(__FILE__) . 'templates/' . $template_slug;

                // If the file exists, return its path.
                if (file_exists($plugin_template)) {
                    $this->loadScriptsAndStyles();
                    return $plugin_template;
                }
            }
        }

        // Otherwise, return the default theme template.
        return $template;
    }

    public function loadBlockBasedTemplateAssets($template)
    {
        $this->loadScriptsAndStyles();
        return $template;
    }

    public function renderWpContent($themeName, $wrapperType = 'default')
    {
        switch ($themeName) {
            case 'blocksy':
                $this->renderBlocksy();
                break;
            case 'astra':
                $this->renderAstra();
                break;
            case 'kadence':
                $this->renderKadence();
                break;
            case 'generatepress':
                $this->renderGeneratePress();
                break;
            case 'oceanwp':
                $this->renderOceanWP();
                break;
            case 'neve':
                $this->renderNeve();
                break;
            case 'hello-elementor':
                get_template_part('template-parts/single');
                break;
            case 'bricks':
                $this->renderBricks($wrapperType);
                break;
            case 'breakdance-zero':
                $this->renderBreakdance();
                break;
            default:
                $this->renderFallBack($wrapperType);
                break;
        }
    }

    public function loadScriptsAndStyles()
    {
        $themeName = get_option('template');

        add_filter('body_class', function ($classes) use ($themeName) {
            $classes[] = 'fluent_com_wp_pages';

            if (
                $themeName === 'breakdance-zero' &&
                !in_array('breakdance', $classes, true)
            ) {
                $classes[] = 'breakdance';
            }

            return $classes;
        });

        add_action('wp_head', function () use ($themeName) {
            $isAlignSupportedTheme = in_array($themeName, ['blocksy'], true);
            ?>
            <style>
                html {
                    font-size: 100%;
                }

                .feed_layout {
                    --global-vw: var(--fcom-main-content-width, 100vw);
                }

                <?php if(!$isAlignSupportedTheme): ?>
                .fcom_wp_content .alignfull,
                .ast-plain-container.ast-no-sidebar .fcom_wp_content .entry-content > .alignfull,
                .no-sidebar .fcom_wp_content .entry-content .alignfull,
                body.single-post.content-max-width .fcom_wp_content .entry-content .alignfull, body.page.content-max-width .fcom_wp_content .entry .alignfull /*for neve */
                {
                    margin-left: calc(50% - (var(--global-vw, 100vw) / 2)) !important;
                    margin-right: calc(50% - (var(--global-vw, 100vw) / 2)) !important;
                    max-width: 100vw !important;
                    width: var(--global-vw, 100vw);
                    padding-left: 0;
                    padding-right: 0;
                    clear: both;
                }

                <?php endif; ?>
            </style>

            <?php if(Helper::hasColorScheme()): ?>
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
            <?php endif; ?>

            <?php
        });

        do_action('fluent_community/enqueue_global_assets', true);
    }

    private function renderBlocksy()
    {
        ?>
        <main <?php echo wp_kses_post(blocksy_main_attr()); ?>>
            <?php
            if (is_singular()) {
                do_action('blocksy:content:top'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                blocksy_before_current_template();
                get_template_part('template-parts/single');
                blocksy_after_current_template();
                do_action('blocksy:content:bottom'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            } else {
                get_template_part('template-parts/archive');
            }
            ?>
        </main>
        <?php
    }

    private function renderAstra()
    {
        $layout = astra_page_layout();
        ?>
        <div id="content" class="site-content">
            <div class="ast-container">
                <?php $layout == 'left-sidebar' && get_sidebar(); ?>
                <div id="primary" <?php astra_primary_class(); ?>>
                    <?php
                    if (is_singular()):
                        astra_primary_content_top();
                        astra_content_page_loop();
                        astra_primary_content_bottom();
                    else:
                        astra_primary_content_top();
                        astra_archive_header();
                        astra_content_loop();
                        astra_pagination();
                        astra_primary_content_bottom();
                    endif;
                    ?>
                </div><!-- #primary -->
                <?php $layout == 'right-sidebar' && get_sidebar(); ?>
            </div>
        </div>
        <?php
    }

    private function renderKadence()
    {
        \Kadence\kadence()->print_styles('kadence-content');
        if (is_singular()) {
            do_action('kadence_single'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        } else {
            do_action('kadence_archive'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        }
    }

    private function renderGeneratePress()
    {
        ?>
        <div <?php generate_do_attr('page'); ?>>
            <?php do_action('generate_inside_site_container'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound ?>
            <div <?php generate_do_attr('site-content'); ?>>
                <?php do_action('generate_inside_container'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound ?>
                <div <?php generate_do_attr('content'); ?>>
                    <main <?php generate_do_attr('main'); ?>>
                        <?php if (is_singular()): ?>
                            <?php
                            do_action('generate_before_main_content'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                            if (generate_has_default_loop()) {
                                while (have_posts()) :
                                    the_post();
                                    generate_do_template_part('page');
                                endwhile;
                            }

                            do_action('generate_after_main_content'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                            ?>
                        <?php else: ?>
                            <?php
                            do_action('generate_before_main_content'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                            if (generate_has_default_loop()) {
                                if (have_posts()) :
                                    do_action('generate_archive_title'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                                    do_action('generate_before_loop', 'archive'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                                    while (have_posts()) :
                                        the_post();
                                        generate_do_template_part('archive');
                                    endwhile;
                                    do_action('generate_after_loop', 'archive'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                                else :
                                    generate_do_template_part('none');
                                endif;
                            }
                            do_action('generate_after_main_content'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                            ?>
                        <?php endif; ?>
                    </main>
                </div>
                <?php
                do_action('generate_after_primary_content_area'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                generate_construct_sidebars(); ?>
            </div>
        </div>
        <?php
    }

    private function renderOceanWP()
    {
        ?>
        <div id="outer-wrap" class="site clr">
            <div id="wrap" class="clr">
                <?php do_action('ocean_before_main'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound ?>
                <main id="main" class="site-main clr"<?php oceanwp_schema_markup('main'); ?> role="main">
                    <?php do_action('ocean_page_header'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound ?>
                    <?php do_action('ocean_before_content_wrap'); ?>
                    <div id="content-wrap" class="container clr">
                        <?php do_action('ocean_before_primary'); ?>
                        <div id="primary" class="content-area clr">
                            <?php do_action('ocean_before_content'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound ?>
                            <div id="content" class="site-content clr">
                                <?php do_action('ocean_before_content_inner'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound ?>
                                <?php if (is_singular()): ?>
                                    <?php
                                    // Elementor `single` location.
                                    if (!function_exists('elementor_theme_do_location') || !elementor_theme_do_location('single')) {
                                        // Start loop.
                                        while (have_posts()) :
                                            the_post();
                                            get_template_part('partials/page/layout');
                                        endwhile;
                                    }
                                    ?>

                                <?php else: ?>

                                    <?php if (have_posts()) :
                                        if (!function_exists('elementor_theme_do_location') || !elementor_theme_do_location('archive')) {
                                            ?>
                                            <div id="blog-entries" class="<?php oceanwp_blog_wrap_classes(); ?>">
                                                <?php
                                                // Define counter for clearing floats.
                                                $oceanwp_count = 0;
                                                ?>

                                                <?php
                                                // Loop through posts.
                                                while (have_posts()) :
                                                    the_post();
                                                    ?>

                                                    <?php
                                                    // Add to counter.
                                                    $oceanwp_count++;
                                                    ?>

                                                    <?php
                                                    // Get post entry content.
                                                    get_template_part('partials/entry/layout', get_post_type());
                                                    ?>

                                                    <?php
                                                    // Reset counter to clear floats.
                                                    if (oceanwp_blog_entry_columns() === $oceanwp_count) {
                                                        $oceanwp_count = 0;
                                                    }
                                                    ?>

                                                <?php endwhile; ?>

                                            </div><!-- #blog-entries -->

                                            <?php
                                            // Display post pagination.
                                            oceanwp_blog_pagination();
                                        }
                                        ?>

                                    <?php
                                    // No posts found.
                                    else :
                                        ?>

                                        <?php
                                        // Display no post found notice.
                                        get_template_part('partials/none');
                                        ?>

                                    <?php endif; ?>

                                <?php endif; ?>
                                <?php do_action('ocean_after_content_inner'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound ?>
                            </div><!-- #content -->
                            <?php do_action('ocean_after_content'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound ?>
                        </div><!-- #primary -->
                        <?php do_action('ocean_after_primary'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound ?>
                    </div><!-- #content-wrap -->
                    <?php do_action('ocean_after_content_wrap'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound ?>
                </main>
                <?php do_action('ocean_after_main'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound ?>
            </div>
        </div>
        <?php
    }

    private function renderNeve()
    {
        $container_class = apply_filters('neve_container_class_filter', 'container', 'single-page'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        $context = class_exists('WooCommerce', false) && (is_cart() || is_checkout() || is_account_page()) ? 'woo-page' : 'single-page';
        ?>
        <div class="<?php echo esc_attr($container_class); ?> single-page-container">
            <div class="row">
                <?php do_action('neve_do_sidebar', $context, 'left'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound ?>
                <div class="nv-single-page-wrap col">
                    <?php
                    do_action('neve_before_page_header'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                    do_action('neve_page_header', $context); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                    do_action('neve_before_content', $context); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

                    if (have_posts()) {
                        while (have_posts()) {
                            the_post();
                            get_template_part('template-parts/content', 'page');
                        }
                    } else {
                        get_template_part('template-parts/content', 'none');
                    }
                    do_action('neve_after_content', $context); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
                    ?>
                </div>
                <?php do_action('neve_do_sidebar', $context, 'right'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound ?>
            </div>
        </div>
        <?php
    }

    private function renderBricks($wrapperType)
    {
        if (defined('BRICKS_VERSION')) {
            $bricks_data = \Bricks\Helpers::get_bricks_data(get_the_ID(), 'content');
            if ($bricks_data) {
                \Bricks\Frontend::render_content($bricks_data);
                return;
            }
        }

        $this->renderFallback($wrapperType);
    }

    private function renderBreakdance()
    {
        if (!function_exists('\\Breakdance\\Render\\render')) {
            $this->renderFallback();
            return;
        }

        $html = \Breakdance\Render\render(get_the_ID());
        if ($html) {
            echo wp_kses_post($html);
        }
    }

    private function renderFallback($wrapperType = 'default')
    {
        if (have_posts()) :
            while (have_posts()) :
                the_post();
                ?>
                <div class="wp_content_wrapper wp_fallback_theme">
                    <?php if ($wrapperType == 'default'): ?>
                        <div class="fcom_wp_content_title">
                            <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
                        </div>
                    <?php endif; ?>
                    <div class="fcom_wp_content_body">
                        <?php the_content(); ?>
                    </div>
                </div>
            <?php
            endwhile;
        endif;
    }

    public function renderMobileMenu()
    {
        $menuItems = Helper::getMobileMenuItems('wp');
        if (!$menuItems) {
            return;
        }
        ?>
        <div class="fcom_mobile_menu">
            <div class="focm_menu_items">
                <?php
                foreach ($menuItems as $menuItem):
                    $permalink = Arr::get($menuItem, 'permalink');
                    if (!$permalink) {
                        $permalink = Helper::getUrlByJsRoute(Arr::get($menuItem, 'route', []));
                    }
                    ?>
                    <div>
                        <a class="focm_menu_item" href="<?php echo esc_url($permalink); ?>">
                        <span class="el-icon">
                            <?php echo wp_kses_post(Arr::get($menuItem, 'icon_svg')); ?>
                        </span>
                            <?php if ($label = Arr::get($menuItem, 'html')): ?>
                                <span><?php echo wp_kses_post($label); ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}
