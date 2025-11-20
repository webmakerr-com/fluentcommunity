<?php

namespace FluentCommunity\App\Hooks\Handlers;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\BaseSpace;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\Modules\Course\Model\CourseLesson;

class FluentBlockEditorHandler
{
    public function register()
    {
        add_action('init', function () {
            register_post_type('fcom-dummy', [
                'label'        => 'Lesson',
                'public'       => false,
                'show_in_rest' => true,
                'supports'     => ['title', 'editor', 'thumbnail'],
            ]);

            register_post_type('fcom-lockscreen', [
                'label'        => 'Lockscreen',
                'public'       => false,
                'show_in_rest' => true,
                'supports'     => ['editor'],
            ]);

            if (!isset($_REQUEST['fluent_community_block_editor'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                return;
            }

            if (!defined('IFRAME_REQUEST')) {
                define('IFRAME_REQUEST', true); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
            }

            remove_action('enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets');
            add_action('fluent_community/block_editor_head', function () {
                $url = FLUENT_COMMUNITY_PLUGIN_URL . 'Modules/Gutenberg/editor/index.css';
                ?>
                <link rel="stylesheet" href="<?php echo esc_url($url); ?>?version=<?php echo esc_attr(FLUENT_COMMUNITY_PLUGIN_VERSION); ?>" media="screen"/> <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet ?>
                <?php
            });
            add_filter('should_load_separate_core_block_assets', '__return_false', 20);
            $this->renderCustomEditor($_REQUEST); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

            add_action('template_redirect', function () {
                $this->renderPage();
                exit(200);
            }, -1000);
        }, 2);
    }

    public function renderCustomEditor($data = [])
    {
        do_action('litespeed_control_set_nocache', 'fluentcommunity api request'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        // set no cache headers
        nocache_headers();

        $hasAccess = false;
        $postType = 'fcom-dummy';
        $context = Arr::get($data, 'context');

        if ($context === 'course_lesson') {
            $lessonId = Arr::get($data, 'lesson_id');
            if ($lessonId) {
                $lesson = CourseLesson::find($lessonId);
                $hasAccess = $lesson && $lesson->course && $lesson->course->isCourseAdmin();
            }
        }

        if ($context === 'lockscreen') {
            $postType = 'fcom-lockscreen';
            $spaceId = Arr::get($data, 'space_id');
            if ($spaceId) {
                $space = BaseSpace::query()->onlyMain()->find($spaceId);
                $hasAccess = $space && $space->isAdmin(get_current_user_id(), true);
            }
        }

        if (!$hasAccess) {
            echo '<h3 style="padding: 100px; text-align: center;">Sorry, you do not have access to this page.</h3>';
            exit(200);
        }

        add_filter('should_load_separate_core_block_assets', '__return_false', 20);
        show_admin_bar(false);

        $firstPost = Utility::getApp('db')->table('posts')
            ->where('post_type', $postType)
            ->first();

        if ($firstPost) {
            $simulatedPost = get_post($firstPost->ID);
            $simulatedPost->post_content = '<!-- wp:paragraph --><p> </p><!-- /wp:paragraph -->';
        } else {
            $newPostId = wp_insert_post(array(
                'post_title'   => $context == 'course_lesson' ? 'Demo Lesson Title' : '',
                'post_content' => '<!-- wp:paragraph --><p> </p><!-- /wp:paragraph -->',
                'post_type'    => $postType,
                'post_status'  => 'draft',
            ));

            $simulatedPost = get_post($newPostId);
        }

        global $post;
        $post = $simulatedPost;

        add_action('wp_enqueue_scripts', function () use ($post) {
            wp_enqueue_script('postbox', admin_url('js/postbox.min.js'), array('jquery-ui-sortable'), FLUENT_COMMUNITY_PLUGIN_VERSION, true);
            wp_enqueue_style('dashicons');
            wp_enqueue_style('media');
            wp_enqueue_style('admin-menu');
            wp_enqueue_style('admin-bar');
            wp_enqueue_style('l10n');

            wp_add_inline_script(
                'wp-api-fetch',
                \sprintf(
                    'wp.apiFetch.use( wp.apiFetch.createPreloadingMiddleware( %s ) );',
                    wp_json_encode(
                        array(
                            '/wp/v2/' . $post->post_type . '/' . $post->ID . '?context=edit' => array(
                                'body' => array(
                                    'id'                 => $post->ID,
                                    'title'              => array('raw' => $post->post_title),
                                    'content'            => array(
                                        'block_format' => 1,
                                        'raw'          => $post->post_content,
                                    ),
                                    'excerpt'            => array('raw' => ''),
                                    'date'               => '',
                                    'date_gmt'           => '',
                                    'modified'           => '',
                                    'modified_gmt'       => '',
                                    'link'               => home_url('/'),
                                    'guid'               => array(),
                                    'parent'             => 0,
                                    'menu_order'         => 0,
                                    'author'             => 0,
                                    'featured_media'     => 0,
                                    'comment_status'     => 'closed',
                                    'ping_status'        => 'closed',
                                    'template'           => '',
                                    'meta'               => array(),
                                    '_links'             => array(),
                                    'type'               => $post->post_type,
                                    'status'             => 'pending', // pending is the best state to remove draft saving possibilities.
                                    'slug'               => '',
                                    'generated_slug'     => '',
                                    'permalink_template' => home_url('/'),
                                ),
                            ),
                        )
                    )
                ),
                'after'
            );
        }, 11);

        add_action('wp_enqueue_scripts', function ($hook) use ($post) {
            // Gutenberg requires the post-locking functions defined within:
            // See `show_post_locked_dialog` and `get_post_metadata` filters below.
            include_once ABSPATH . 'wp-admin/includes/post.php';
            $this->gutenberg_editor_scripts_and_styles($hook, $post);
        });

        // Disable post locking dialogue.
        add_filter('show_post_locked_dialog', '__return_false');

        // Everyone can richedit! This avoids a case where a page can be cached where a user can't richedit.
        $GLOBALS['wp_rich_edit'] = true;
        add_filter('user_can_richedit', '__return_true', 1000);

        // Homepage is always locked by @wordpressdotorg
        // This prevents other logged-in users taking a lock of the post on the front-end.
        add_filter('get_post_metadata', function ($value, $post_id, $meta_key) {
            if ($meta_key !== '_edit_lock') {
                return $value;
            }
            return time() . ':' . get_current_user_id(); // WordPressdotorg user ID
        }, 10, 3);

        // Disable Jetpack Blocks for now.
        add_filter('jetpack_gutenberg', '__return_false');
    }

    function gutenberg_editor_scripts_and_styles($hook, $post)
    {
        $initial_edits = array(
            'title'   => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
        );

        $editor_settings = $this->getEditorSettings($post);
        
        $init_script = 
            "(function() {
                window._wpLoadBlockEditor = new Promise(function(resolve) {
                    wp.domReady(function() {
                        resolve(wp.editPost.initializeEditor('editor', \"%s\", %d, %s, %s));
                    });
                });
            })();";

        $script = sprintf(
            $init_script,
            $post->post_type,
            $post->ID,
            wp_json_encode($editor_settings),
            wp_json_encode($initial_edits)
        );
        wp_add_inline_script('wp-edit-post', $script);

        /**
         * Scripts
         */
        wp_enqueue_media(
            array(
                'post' => null
            )
        );

        add_filter('user_can_richedit', '__return_true');
        wp_tinymce_inline_scripts();
        wp_enqueue_editor();


        /**
         * Styles
         */
        wp_enqueue_style('wp-edit-post');

        /*
        These styles are usually registered by Gutenberg and register properly when the user is signed in.
        However, if the use is not registered they are not added. For now, include them, but this isn't a good long term strategy

        See: https://github.com/WordPress/wporg-gutenberg/issues/26
        */
        wp_enqueue_style('global-styles');
        wp_enqueue_style('wp-block-library');
        wp_enqueue_style('wp-block-image');
        wp_enqueue_style('wp-block-group');
        wp_enqueue_style('wp-block-heading');
        wp_enqueue_style('wp-block-button');
        wp_enqueue_style('wp-block-paragraph');
        wp_enqueue_style('wp-block-separator');
        wp_enqueue_style('wp-block-columns');
        wp_enqueue_style('wp-block-cover');
        wp_enqueue_style('global-styles-css-custom-properties');
        wp_enqueue_style('wp-block-spacer');

        wp_register_style('fluent_com_editor_styles', FLUENT_COMMUNITY_PLUGIN_URL . 'Modules/Gutenberg/editor/style.css', false, FLUENT_COMMUNITY_PLUGIN_VERSION, 'all');

        // add_editor_style('fluent_com_editor_styles.css');

        // wp_enqueue_style('fluent_com_editor_styles', FLUENT_COMMUNITY_PLUGIN_URL . 'Modules/Gutenberg/editor/style-editor.css', false, FLUENT_COMMUNITY_PLUGIN_VERSION, 'all');

        // wp_dequeue_style('global-styles');


        // add_action('fluent_enqueue_block_editor_assets', 'enqueue_editor_block_styles_assets');
        add_action('fluent_enqueue_block_editor_assets', 'wp_enqueue_editor_format_library_assets');
        // add_action('fluent_enqueue_block_editor_assets', 'wp_enqueue_global_styles_css_custom_properties');

        /**
         * Fires after block assets have been enqueued for the editing interface.
         *
         * Call `add_action` on any hook before 'admin_enqueue_scripts'.
         *
         * In the function call you supply, simply use `wp_enqueue_script` and
         * `wp_enqueue_style` to add your functionality to the Gutenberg editor.
         *
         * @since 0.4.0
         */
        // do_action('enqueue_block_editor_assets');
        do_action('fluent_enqueue_block_editor_assets');

        wp_enqueue_script('fcom_editor_custom', FLUENT_COMMUNITY_PLUGIN_URL . 'Modules/Gutenberg/editor/index.js', ['react', 'wp-components', 'wp-compose', 'wp-data', 'wp-edit-post', 'wp-i18n', 'wp-plugins'], FLUENT_COMMUNITY_PLUGIN_VERSION . time(), true);
    }

    function gutenberg_get_available_image_sizes()
    {
        $size_names = apply_filters(
            'fluent_community/image_size_names_choose',
            array(
                'thumbnail' => __('Thumbnail', 'fluent-community'),
                'medium'    => __('Medium', 'fluent-community'),
                'large'     => __('Large', 'fluent-community'),
                'full'      => __('Full Size', 'fluent-community'),
            )
        );
        $all_sizes = array();
        foreach ($size_names as $size_slug => $size_name) {
            $all_sizes[] = array(
                'slug' => $size_slug,
                'name' => $size_name,
            );
        }
        return $all_sizes;
    }

    protected function renderPage()
    {
        add_action('fluent_community/block_editor_footer', function () {
            wp_underscore_playlist_templates();
            wp_print_footer_scripts();
            wp_print_media_templates();
            wp_enqueue_global_styles();
            wp_enqueue_stored_styles();
            wp_maybe_inline_styles();
        });

        add_action( 'fluent_block_editor/head', 'wp_enqueue_scripts', 1 );
        add_action( 'fluent_block_editor/head', 'wp_resource_hints', 2 );
        add_action( 'fluent_block_editor/head', 'wp_preload_resources', 1 );
        add_action( 'fluent_block_editor/head', 'wp_print_styles', 8 );
        add_action( 'fluent_block_editor/head', 'wp_print_head_scripts', 9 );
        add_action( 'fluent_block_editor/head', 'wp_custom_css_cb', 101 );

        $this->unloadOtherScripts();
        ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <title>FluentCommuynity Block Editor</title>
    <meta charset='utf-8'>
    <meta name="viewport"
          content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=0,viewport-fit=cover"/>
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="robots" content="noindex">
    <?php do_action('fluent_block_editor/head'); ?>
    <?php //wp_head(); ?>
    <?php do_action('fluent_community/block_editor_head'); ?>
</head>
<body class="fcom_custom_editor">
<div class="wp-site-blocks">
    <div id="editor" class="gutenberg__editor"></div>
</div>
<?php
do_action('fluent_community/block_editor_footer');
?>
</body>
</html>
        <?php
    }

    private function unloadOtherScripts()
    {
        $isSkip = apply_filters('fluent_com_editor/skip_no_conflict', false);
        if ($isSkip) {
            return;
        }

        /**
         * Define the list of approved slugs for FluentCRM assets.
         *
         * This filter allows modification of the list of slugs that are approved for FluentCRM assets.
         *
         * @param array $approvedSlugs An array of approved slugs for FluentCRM assets.
         */
        $approvedSlugs = apply_filters('fluent_com_editor/asset_listed_slugs', [
            '\/gutenberg\/'
        ]);
        $approvedSlugs[] = 'fluent-community';
        $approvedSlugs = array_unique($approvedSlugs);
        $approvedSlugs = implode('|', $approvedSlugs);

        $pluginUrl = str_replace(['http:', 'https:'], '', plugins_url());

        $themesUrl = str_replace(['http:', 'https:'], '', get_theme_root_uri());

        add_filter('script_loader_src', function ($src, $handle) use ($approvedSlugs, $pluginUrl, $themesUrl) {
            if (!$src) {
                return $src;
            }

            $willSkip = (strpos($src, $pluginUrl) !== false) && !preg_match('/' . $approvedSlugs . '/', $src);
            if ($willSkip) {
                return false;
            }

            $willSkip = (strpos($src, $themesUrl) !== false) && !preg_match('/' . $approvedSlugs . '/', $src);

            if ($willSkip) {
                return false;
            }

            return $src;
        }, 1, 2);

        add_action('wp_print_scripts', function () use ($approvedSlugs, $pluginUrl, $themesUrl) {
            global $wp_scripts;
            if (!$wp_scripts) {
                return;
            }

            foreach ($wp_scripts->queue as $script) {
                if (empty($wp_scripts->registered[$script]) || empty($wp_scripts->registered[$script]->src)) {
                    continue;
                }

                $src = $wp_scripts->registered[$script]->src;
                $isMatched = (strpos($src, $pluginUrl) !== false) && !preg_match('/' . $approvedSlugs . '/', $src);
                if (!$isMatched) {
                    continue;
                }

                $isMatched = (strpos($src, $themesUrl) !== false) && !preg_match('/' . $approvedSlugs . '/', $src);
                if (!$isMatched) {
                    continue;
                }

                wp_dequeue_script($wp_scripts->registered[$script]->handle);
            }
        }, 1);

        add_action('wp_print_styles', function () {
            $isSkip = apply_filters('fluent_community/skip_no_conflict', false, 'styles');

            if ($isSkip) {
                return;
            }

            global $wp_styles;
            if (!$wp_styles) {
                return;
            }

            //    dd($wp_styles);

            $approvedSlugs = apply_filters('fluent_community/asset_listed_slugs', [
                '\/gutenberg\/',
            ]);

            $approvedSlugs[] = '\/fluent-community\/';

            $approvedSlugs = array_unique($approvedSlugs);
            $approvedSlugs = implode('|', $approvedSlugs);

            $pluginUrl = plugins_url();
            $themeUrl = get_theme_root_uri();

            $pluginUrl = str_replace(['http:', 'https:'], '', $pluginUrl);
            $themeUrl = str_replace(['http:', 'https:'], '', $themeUrl);

            foreach ($wp_styles->queue as $script) {

                if (empty($wp_styles->registered[$script]) || empty($wp_styles->registered[$script]->src)) {
                    continue;
                }

                $src = $wp_styles->registered[$script]->src;
                $pluginMatched = (strpos($src, $pluginUrl) !== false) && !preg_match('/' . $approvedSlugs . '/', $src);
                $themeMatched = (strpos($src, $themeUrl) !== false) && !preg_match('/' . $approvedSlugs . '/', $src);

                if (!$pluginMatched && !$themeMatched) {
                    continue;
                }

                wp_dequeue_style($wp_styles->registered[$script]->handle);
            }
        }, 999999);
    }

    private function getEditorSettings($post)
    {
        // Media settings.
        $max_upload_size = wp_max_upload_size();
        if (!$max_upload_size) {
            $max_upload_size = 0;
        }

        $lock_details = array(
            'isLocked' => false,
            'user'     => '',
        );

        $editor_settings = array(
            'maxUploadFileSize'      => $max_upload_size,
            'allowedMimeTypes'       => get_allowed_mime_types(),
            'postLock'               => $lock_details,
            'postLockUtils'          => array(
                'nonce'       => wp_create_nonce('lock-post_' . $post->ID),
                'unlockNonce' => wp_create_nonce('update-post_' . $post->ID),
                'ajaxUrl'     => admin_url('admin-ajax.php'),
            ),
            '__experimentalFeatures'=> array(
                'appearanceTools'               => true,
                'useRootPaddingAwareAlignments' => false,
                'border'                        => [
                    'color'  => 1,
                    'radius' => 1,
                    'style'  => 1,
                    'width'  => 1,
                ],
                'color'                         => [
                    'background'       => true,
                    'button'           => 1,
                    'caption'          => 1,
                    'customDuotone'    => 0,
                    'defaultDuotone'   => 0,
                    'defaultGradients' => 0,
                    'defaultPalette'   => [],
                    'duotone'          => [],
                    'gradients'        => [],
                    'heading'          => 1,
                    'link'             => 1,
                    'palette'          => [
                        'default' => [],
                        'theme'   => [
                            [
                                'name'  => 'Accent',
                                'slug'  => 'theme-palette-color-1',
                                'color' => 'var(--theme-palette-color-1)',
                            ],
                            [
                                'name'  => 'Accent - alt',
                                'slug'  => 'theme-palette-color-2',
                                'color' => 'var(--theme-palette-color-2)',
                            ],
                            [
                                'name'  => 'Strongest text',
                                'slug'  => 'theme-palette-color-3',
                                'color' => 'var(--theme-palette-color-3)',
                            ],
                            [
                                'name'  => 'Strong Text',
                                'slug'  => 'theme-palette-color-4',
                                'color' => 'var(--theme-palette-color-4)',
                            ],
                            [
                                'name'  => 'Medium text',
                                'slug'  => 'theme-palette-color-5',
                                'color' => 'var(--theme-palette-color-5)',
                            ],
                            [
                                'name'  => 'Subtle Text',
                                'slug'  => 'theme-palette-color-6',
                                'color' => 'var(--theme-palette-color-6)',
                            ],
                            [
                                'name'  => 'Subtle Background',
                                'slug'  => 'theme-palette-color-7',
                                'color' => 'var(--theme-palette-color-7)',
                            ],
                            [
                                'name'  => 'Lighter Background',
                                'slug'  => 'theme-palette-color-8',
                                'color' => 'var(--theme-palette-color-8)',
                            ]
                        ]
                    ],
                    'text'             => true,
                ],
                'dimensions'                    => [
                    'defaultAspectRatios' => true,
                    'aspectRatios'        => [
                        'default' => [
                            [
                                'name'  => 'Square - 1:1',
                                'slug'  => 'square',
                                'ratio' => '1',
                            ],
                            [
                                'name'  => 'Standard - 4:3',
                                'slug'  => '4-3',
                                'ratio' => '4/3',
                            ],
                            [
                                'name'  => 'Portrait - 3:4',
                                'slug'  => '3-4',
                                'ratio' => '3/4',
                            ],
                            [
                                'name'  => 'Classic - 3:2',
                                'slug'  => '3-2',
                                'ratio' => '3/2',
                            ],
                            [
                                'name'  => 'Classic Portrait - 2:3',
                                'slug'  => '2-3',
                                'ratio' => '2/3',
                            ],
                            [
                                'name'  => 'Wide - 16:9',
                                'slug'  => '16-9',
                                'ratio' => '16/9',
                            ],
                            [
                                'name'  => 'Tall - 9:16',
                                'slug'  => '9-16',
                                'ratio' => '9/16',
                            ],
                        ]
                    ],
                    'aspectRatio'         => 1,
                    'minHeight'           => 1,
                ],
                'shadow'                        => [
                    'defaultPresets' => true,
                    'presets'        => [
                        'default' => [
                            [
                                'name'   => 'Natural',
                                'slug'   => 'natural',
                                'shadow' => '6px 6px 9px rgba(0, 0, 0, 0.2)',
                            ],
                            [
                                'name'   => 'Deep',
                                'slug'   => 'deep',
                                'shadow' => '12px 12px 50px rgba(0, 0, 0, 0.4)',
                            ],
                            [
                                'name'   => 'Sharp',
                                'slug'   => 'sharp',
                                'shadow' => '6px 6px 0px rgba(0, 0, 0, 0.2)',
                            ],
                            [
                                'name'   => 'Outlined',
                                'slug'   => 'outlined',
                                'shadow' => '6px 6px 0px -3px rgba(255, 255, 255, 1), 6px 6px rgba(0, 0, 0, 1)',
                            ],
                            [
                                'name'   => 'Crisp',
                                'slug'   => 'crisp',
                                'shadow' => '6px 6px 0px rgba(0, 0, 0, 1)',
                            ],
                        ],
                    ],
                ],
                'spacing'                       => [
                    'blockGap'            => 1,
                    'margin'              => 1,
                    'padding'             => 1,
                    'defaultSpacingSizes' => true,
                    'spacingScale'        => [
                        'default' => [
                            'operator'   => '*',
                            'increment'  => 1.5,
                            'steps'      => 7,
                            'mediumStep' => 1.5,
                            'unit'       => 'rem',
                        ],
                    ],
                    'spacingSizes'        => [
                        'default' => [
                            [
                                'name' => '2X-Small',
                                'slug' => '20',
                                'size' => '0.44rem',
                            ],
                            [
                                'name' => 'X-Small',
                                'slug' => '30',
                                'size' => '0.67rem',
                            ],
                            [
                                'name' => 'Small',
                                'slug' => '40',
                                'size' => '1rem',
                            ],
                            [
                                'name' => 'Medium',
                                'slug' => '50',
                                'size' => '1.5rem',
                            ],
                            [
                                'name' => 'Large',
                                'slug' => '60',
                                'size' => '2.25rem',
                            ],
                            [
                                'name' => 'X-Large',
                                'slug' => '70',
                                'size' => '3.38rem',
                            ],
                            [
                                'name' => '2X-Large',
                                'slug' => '80',
                                'size' => '5.06rem',
                            ],
                        ],
                    ],
                ],
                'typography'                    => [
                    'defaultFontSizes' => NULL,
                    'dropCap'          => true,
                    'fontSizes'        => [
                        'default' => [
                            [
                                'name' => 'Small',
                                'slug' => 'small',
                                'size' => '13px',
                            ],
                            [
                                'name' => 'Medium',
                                'slug' => 'medium',
                                'size' => '20px',
                            ],
                            [
                                'name' => 'Large',
                                'slug' => 'large',
                                'size' => '36px',
                            ],
                            [
                                'name' => 'Extra Large',
                                'slug' => 'x-large',
                                'size' => '42px',
                            ],
                        ],
                        'theme'   => [
                            [
                                'name' => 'Small',
                                'slug' => 'small',
                                'size' => 'var(--fcom-font-size-small)',
                            ],
                            [
                                'name' => 'Medium',
                                'slug' => 'medium',
                                'size' => 'var(--fcom-font-size-medium)',
                            ],
                            [
                                'name' => 'Large',
                                'slug' => 'large',
                                'size' => 'var(--fcom-font-size-large)',
                            ],
                            [
                                'name' => 'Larger',
                                'slug' => 'larger',
                                'size' => 'var(--fcom-font-size-larger)',
                            ],
                            [
                                'name' => 'XX-Large',
                                'slug' => 'xxlarge',
                                'size' => 'var(--fcom-font-size-xxlarge)',
                            ],
                        ],
                    ],
                    'fontStyle'        => true,
                    'fontWeight'       => true,
                    'letterSpacing'    => true,
                    'textAlign'        => true,
                    'textDecoration'   => true,
                    'textTransform'    => true,
                    'writingMode'      => false,
                    'fluid'            => 0,
                ],
                'blocks'                        => [
                    'core/button'    => [
                        'border' => [
                            'radius' => true,
                        ]
                    ],
                    'core/image'     => [
                        'lightbox' => [
                            'allowEditing' => true,
                        ]
                    ],
                    'core/pullquote' => [
                        'border' => [
                            'color'  => true,
                            'radius' => true,
                            'style'  => true,
                            'width'  => true,
                        ]
                    ],
                    'core/paragraph' => [
                        'spacing' => [
                            'margin'  => 1,
                            'padding' => 1,
                        ]
                    ]
                ],
                'layout'                        => [
                    'contentSize' => 'var(--theme-block-max-width)',
                    'wideSize'    => 'var(--theme-block-wide-max-width)',
                ],
                'background'                    => [
                    'backgroundImage' => 1,
                    'backgroundSize'  => 1,
                ],
                'position'                      => [
                    'sticky' => 0,
                ]
            ),
            'colors' => [
                [
                    'color' => 'var(--theme-palette-color-1)',
                    'name'  => 'Accent',
                    'slug'  => 'theme-palette-color-1'
                ],
                [
                    'color' => 'var(--theme-palette-color-2)',
                    'name'  => 'Accent - alt',
                    'slug'  => 'theme-palette-color-2'
                ],
                [
                    'color' => 'var(--theme-palette-color-3)',
                    'name'  => 'Strongest text',
                    'slug'  => 'theme-palette-color-3'
                ],
                [
                    'color' => 'var(--theme-palette-color-4)',
                    'name'  => 'Strong Text',
                    'slug'  => 'theme-palette-color-4'
                ],
                [
                    'color' => 'var(--theme-palette-color-5)',
                    'name'  => 'Medium text',
                    'slug'  => 'theme-palette-color-5'
                ],
                [
                    'color' => 'var(--theme-palette-color-6)',
                    'name'  => 'Subtle Text',
                    'slug'  => 'theme-palette-color-6'
                ],
                [
                    'color' => 'var(--theme-palette-color-7)',
                    'name'  => 'Subtle Background',
                    'slug'  => 'theme-palette-color-7'
                ],
                [
                    'color' => 'var(--theme-palette-color-8)',
                    'name'  => 'Lighter Background',
                    'slug'  => 'theme-palette-color-8'
                ]
            ],
            '__experimentalDiscussionSettings'=> [
                'avatarURL'            => 'https://secure.gravatar.com/avatar/?s=96&f=y&r=g',
                'commentOrder'         => 'asc',
                'commentsPerPage'      => '50',
                'defaultCommentsPage'  => 'newest',
                'defaultCommentStatus' => 'open',
                'pageComments'         => '',
                'threadComments'       => '1',
                'threadCommentsDepth'  => '5'
            ],
            '__unstableGalleryWithImageBlocks' => false,
            '__unstableIsBlockBasedTheme' => false,
            'enableCustomUnits' => [
                'px',
                'em',
                'rem',
                '%',
                'vh',
                'vw'
            ],
            'fontSizes' => [
                [
                    'name' => 'Small',
                    'size' => 'var(--fcom-font-size-small)',
                    'slug' => 'small'
                ],
                [
                    'name' => 'Medium',
                    'size' => 'var(--fcom-font-size-medium)',
                    'slug' => 'medium'
                ],
                [
                    'name' => 'Large',
                    'size' => 'var(--fcom-font-size-large)',
                    'slug' => 'large'
                ],
                [
                    'name' => 'Larger',
                    'size' => 'var(--fcom-font-size-larger)',
                    'slug' => 'larger'
                ],
                [
                    'name' => 'XX-Large',
                    'size' => 'var(--fcom-font-size-xxlarge)',
                    'slug' => 'xxlarge'
                ]
            ],
            'fullscreenMode' => 1,
            'enableCustomSpacing' => 1,
            'enableCustomLineHeight' => 1,
            'enableCustomFields' => false,
            'disablePostFormats' => true,
            'disableLayoutStyles' => false,
            'disableCustomSpacingSizes' => false,
            'disableCustomGradients' => 1,
            'alignWide' => true,
            'disableCustomFontSizes' => false,
            'disableCustomColors' => false,
            'canUpdateBlockBindings' => false,
            'bodyPlaceholder' => __('Start writing or type / to choose a block for your lesson content', 'fluent-community'),
            'allowedBlockTypes' => apply_filters('fluent_community/allowed_block_types', [
                'core/audio',
                'core/block',
                'core/buttons',
                'core/button',
                'core/code',
                'core/columns',
                'core/column',
                'core/cover',
                'core/embed',
                'core/footnotes',
                'core/freeform',
                'core/gallery',
                'core/group',
                'core/heading',
                'core/html',
                'core/image',
                'core/latest-posts',
                'core/list',
                'core/list-item',
                'core/media-text',
                'core/missing',
                'core/paragraph',
                'core/preformatted',
                'core/pullquote',
                'core/quote',
                'core/rss',
                'core/separator',
                'core/social-link',
                'core/social-links',
                'core/spacer',
                'core/table',
                'core/text-columns',
                'core/verse',
                'core/freeform'
            ]),
            'gradients' => [],
            'imageDefaultSize' => 'large',
            'imageEditing' => true,
            'isRTL' => Helper::isRtl(),
            'autosaveInterval' => 999,
            'localAutosaveInterval' => 999,
            'richEditingEnabled' => true,
            'spacingSizes' => [
                [
                    'name' => '2X-Small',
                    'size' => '0.44rem',
                    'slug' => '20'
                ],
                [
                    'name' => 'X-Small',
                    'size' => '0.67rem',
                    'slug' => '30'
                ],
                [
                    'name' => 'Small',
                    'size' => '1rem',
                    'slug' => '40'
                ],
                [
                    'name' => 'Medium',
                    'size' => '1.5rem',
                    'slug' => '50'
                ],
                [
                    'name' => 'Large',
                    'size' => '2.25rem',
                    'slug' => '60'
                ],
                [
                    'name' => 'X-Large',
                    'size' => '3.38rem',
                    'slug' => '70'
                ],
                [
                    'name' => '2X-Large',
                    'size' => '5.06rem',
                    'slug' => '80'
                ]
            ],
            'titlePlaceholder' => __('Add Lesson title', 'fluent-community')
        );

        $colorSchema = Utility::getColorSchemaConfig();
        $lightSchemaConfig = Arr::get($colorSchema, 'light');
        $colorSchmeaCss = ':root {';
        foreach (Arr::get($lightSchemaConfig, 'body', []) as $colorKey => $value) {
            if($value) {
                $cssVar = ' --fcom-' . str_replace('_', '-', $colorKey);
                $colorSchmeaCss .= $cssVar . ':' . $value . '; ';
            }
        }
        $colorSchmeaCss .= '}';

        $editor_settings['styles'] = [
            [
                '__unstableType' => 'presets',
                'css'            => ':root{--wp--preset--aspect-ratio--square: 1;--wp--preset--aspect-ratio--4-3: 4/3;--wp--preset--aspect-ratio--3-4: 3/4;--wp--preset--aspect-ratio--3-2: 3/2;--wp--preset--aspect-ratio--2-3: 2/3;--wp--preset--aspect-ratio--16-9: 16/9;--wp--preset--aspect-ratio--9-16: 9/16;--wp--preset--color--theme-palette-color-1: var(--theme-palette-color-1);--wp--preset--color--theme-palette-color-2: var(--theme-palette-color-2);--wp--preset--color--theme-palette-color-3: var(--theme-palette-color-3);--wp--preset--color--theme-palette-color-4: var(--theme-palette-color-4);--wp--preset--color--theme-palette-color-5: var(--theme-palette-color-5);--wp--preset--color--theme-palette-color-6: var(--theme-palette-color-6);--wp--preset--color--theme-palette-color-7: var(--theme-palette-color-7);--wp--preset--color--theme-palette-color-8: var(--theme-palette-color-8);--wp--preset--gradient--vivid-cyan-blue-to-vivid-purple: linear-gradient(135deg,rgba(6,147,227,1) 0%,rgb(155,81,224) 100%);--wp--preset--gradient--light-green-cyan-to-vivid-green-cyan: linear-gradient(135deg,rgb(122,220,180) 0%,rgb(0,208,130) 100%);--wp--preset--gradient--luminous-vivid-amber-to-luminous-vivid-orange: linear-gradient(135deg,rgba(252,185,0,1) 0%,rgba(255,105,0,1) 100%);--wp--preset--gradient--luminous-vivid-orange-to-vivid-red: linear-gradient(135deg,rgba(255,105,0,1) 0%,rgb(207,46,46) 100%);--wp--preset--gradient--very-light-gray-to-cyan-bluish-gray: linear-gradient(135deg,rgb(238,238,238) 0%,rgb(169,184,195) 100%);--wp--preset--gradient--cool-to-warm-spectrum: linear-gradient(135deg,rgb(74,234,220) 0%,rgb(151,120,209) 20%,rgb(207,42,186) 40%,rgb(238,44,130) 60%,rgb(251,105,98) 80%,rgb(254,248,76) 100%);--wp--preset--gradient--blush-light-purple: linear-gradient(135deg,rgb(255,206,236) 0%,rgb(152,150,240) 100%);--wp--preset--gradient--blush-bordeaux: linear-gradient(135deg,rgb(254,205,165) 0%,rgb(254,45,45) 50%,rgb(107,0,62) 100%);--wp--preset--gradient--luminous-dusk: linear-gradient(135deg,rgb(255,203,112) 0%,rgb(199,81,192) 50%,rgb(65,88,208) 100%);--wp--preset--gradient--pale-ocean: linear-gradient(135deg,rgb(255,245,203) 0%,rgb(182,227,212) 50%,rgb(51,167,181) 100%);--wp--preset--gradient--electric-grass: linear-gradient(135deg,rgb(202,248,128) 0%,rgb(113,206,126) 100%);--wp--preset--gradient--midnight: linear-gradient(135deg,rgb(2,3,129) 0%,rgb(40,116,252) 100%);--wp--preset--gradient--juicy-peach: linear-gradient(to right, #ffecd2 0%, #fcb69f 100%);--wp--preset--gradient--young-passion: linear-gradient(to right, #ff8177 0%, #ff867a 0%, #ff8c7f 21%, #f99185 52%, #cf556c 78%, #b12a5b 100%);--wp--preset--gradient--true-sunset: linear-gradient(to right, #fa709a 0%, #fee140 100%);--wp--preset--gradient--morpheus-den: linear-gradient(to top, #30cfd0 0%, #330867 100%);--wp--preset--gradient--plum-plate: linear-gradient(135deg, #667eea 0%, #764ba2 100%);--wp--preset--gradient--aqua-splash: linear-gradient(15deg, #13547a 0%, #80d0c7 100%);--wp--preset--gradient--love-kiss: linear-gradient(to top, #ff0844 0%, #ffb199 100%);--wp--preset--gradient--new-retrowave: linear-gradient(to top, #3b41c5 0%, #a981bb 49%, #ffc8a9 100%);--wp--preset--gradient--plum-bath: linear-gradient(to top, #cc208e 0%, #6713d2 100%);--wp--preset--gradient--high-flight: linear-gradient(to right, #0acffe 0%, #495aff 100%);--wp--preset--gradient--teen-party: linear-gradient(-225deg, #FF057C 0%, #8D0B93 50%, #321575 100%);--wp--preset--gradient--fabled-sunset: linear-gradient(-225deg, #231557 0%, #44107A 29%, #FF1361 67%, #FFF800 100%);--wp--preset--gradient--arielle-smile: radial-gradient(circle 248px at center, #16d9e3 0%, #30c7ec 47%, #46aef7 100%);--wp--preset--gradient--itmeo-branding: linear-gradient(180deg, #2af598 0%, #009efd 100%);--wp--preset--gradient--deep-blue: linear-gradient(to right, #6a11cb 0%, #2575fc 100%);--wp--preset--gradient--strong-bliss: linear-gradient(to right, #f78ca0 0%, #f9748f 19%, #fd868c 60%, #fe9a8b 100%);--wp--preset--gradient--sweet-period: linear-gradient(to top, #3f51b1 0%, #5a55ae 13%, #7b5fac 25%, #8f6aae 38%, #a86aa4 50%, #cc6b8e 62%, #f18271 75%, #f3a469 87%, #f7c978 100%);--wp--preset--gradient--purple-division: linear-gradient(to top, #7028e4 0%, #e5b2ca 100%);--wp--preset--gradient--cold-evening: linear-gradient(to top, #0c3483 0%, #a2b6df 100%, #6b8cce 100%, #a2b6df 100%);--wp--preset--gradient--mountain-rock: linear-gradient(to right, #868f96 0%, #596164 100%);--wp--preset--gradient--desert-hump: linear-gradient(to top, #c79081 0%, #dfa579 100%);--wp--preset--gradient--ethernal-constance: linear-gradient(to top, #09203f 0%, #537895 100%);--wp--preset--gradient--happy-memories: linear-gradient(-60deg, #ff5858 0%, #f09819 100%);--wp--preset--gradient--grown-early: linear-gradient(to top, #0ba360 0%, #3cba92 100%);--wp--preset--gradient--morning-salad: linear-gradient(-225deg, #B7F8DB 0%, #50A7C2 100%);--wp--preset--gradient--night-call: linear-gradient(-225deg, #AC32E4 0%, #7918F2 48%, #4801FF 100%);--wp--preset--gradient--mind-crawl: linear-gradient(-225deg, #473B7B 0%, #3584A7 51%, #30D2BE 100%);--wp--preset--gradient--angel-care: linear-gradient(-225deg, #FFE29F 0%, #FFA99F 48%, #FF719A 100%);--wp--preset--gradient--juicy-cake: linear-gradient(to top, #e14fad 0%, #f9d423 100%);--wp--preset--gradient--rich-metal: linear-gradient(to right, #d7d2cc 0%, #304352 100%);--wp--preset--gradient--mole-hall: linear-gradient(-20deg, #616161 0%, #9bc5c3 100%);--wp--preset--gradient--cloudy-knoxville: linear-gradient(120deg, #fdfbfb 0%, #ebedee 100%);--wp--preset--gradient--soft-grass: linear-gradient(to top, #c1dfc4 0%, #deecdd 100%);--wp--preset--gradient--saint-petersburg: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);--wp--preset--gradient--everlasting-sky: linear-gradient(135deg, #fdfcfb 0%, #e2d1c3 100%);--wp--preset--gradient--kind-steel: linear-gradient(-20deg, #e9defa 0%, #fbfcdb 100%);--wp--preset--gradient--over-sun: linear-gradient(60deg, #abecd6 0%, #fbed96 100%);--wp--preset--gradient--premium-white: linear-gradient(to top, #d5d4d0 0%, #d5d4d0 1%, #eeeeec 31%, #efeeec 75%, #e9e9e7 100%);--wp--preset--gradient--clean-mirror: linear-gradient(45deg, #93a5cf 0%, #e4efe9 100%);--wp--preset--gradient--wild-apple: linear-gradient(to top, #d299c2 0%, #fef9d7 100%);--wp--preset--gradient--snow-again: linear-gradient(to top, #e6e9f0 0%, #eef1f5 100%);--wp--preset--gradient--confident-cloud: linear-gradient(to top, #dad4ec 0%, #dad4ec 1%, #f3e7e9 100%);--wp--preset--gradient--glass-water: linear-gradient(to top, #dfe9f3 0%, white 100%);--wp--preset--gradient--perfect-white: linear-gradient(-225deg, #E3FDF5 0%, #FFE6FA 100%);--wp--preset--font-size--small: var(--fcom-font-size-small);--wp--preset--font-size--medium: var(--fcom-font-size-medium);--wp--preset--font-size--large: var(--fcom-font-size-large);--wp--preset--font-size--x-large: 42px;--wp--preset--font-size--larger: var(--fcom-font-size-larger);--wp--preset--font-size--xxlarge: var(--fcom-font-size-xxlarge);--wp--preset--spacing--20: 0.44rem;--wp--preset--spacing--30: 0.67rem;--wp--preset--spacing--40: 1rem;--wp--preset--spacing--50: 1.5rem;--wp--preset--spacing--60: 2.25rem;--wp--preset--spacing--70: 3.38rem;--wp--preset--spacing--80: 5.06rem;--wp--preset--shadow--natural: 6px 6px 9px rgba(0, 0, 0, 0.2);--wp--preset--shadow--deep: 12px 12px 50px rgba(0, 0, 0, 0.4);--wp--preset--shadow--sharp: 6px 6px 0px rgba(0, 0, 0, 0.2);--wp--preset--shadow--outlined: 6px 6px 0px -3px rgba(255, 255, 255, 1), 6px 6px rgba(0, 0, 0, 1);--wp--preset--shadow--crisp: 6px 6px 0px rgba(0, 0, 0, 1);}',
                'isGlobalStyles' => true
            ],
            [
                '__unstableType' => 'presets',
                'css'            => '.has-theme-palette-color-1-color{color: var(--wp--preset--color--theme-palette-color-1) !important;}.has-theme-palette-color-2-color{color: var(--wp--preset--color--theme-palette-color-2) !important;}.has-theme-palette-color-3-color{color: var(--wp--preset--color--theme-palette-color-3) !important;}.has-theme-palette-color-4-color{color: var(--wp--preset--color--theme-palette-color-4) !important;}.has-theme-palette-color-5-color{color: var(--wp--preset--color--theme-palette-color-5) !important;}.has-theme-palette-color-6-color{color: var(--wp--preset--color--theme-palette-color-6) !important;}.has-theme-palette-color-7-color{color: var(--wp--preset--color--theme-palette-color-7) !important;}.has-theme-palette-color-8-color{color: var(--wp--preset--color--theme-palette-color-8) !important;}.has-theme-palette-color-1-background-color{background-color: var(--wp--preset--color--theme-palette-color-1) !important;}.has-theme-palette-color-2-background-color{background-color: var(--wp--preset--color--theme-palette-color-2) !important;}.has-theme-palette-color-3-background-color{background-color: var(--wp--preset--color--theme-palette-color-3) !important;}.has-theme-palette-color-4-background-color{background-color: var(--wp--preset--color--theme-palette-color-4) !important;}.has-theme-palette-color-5-background-color{background-color: var(--wp--preset--color--theme-palette-color-5) !important;}.has-theme-palette-color-6-background-color{background-color: var(--wp--preset--color--theme-palette-color-6) !important;}.has-theme-palette-color-7-background-color{background-color: var(--wp--preset--color--theme-palette-color-7) !important;}.has-theme-palette-color-8-background-color{background-color: var(--wp--preset--color--theme-palette-color-8) !important;}.has-theme-palette-color-1-border-color{border-color: var(--wp--preset--color--theme-palette-color-1) !important;}.has-theme-palette-color-2-border-color{border-color: var(--wp--preset--color--theme-palette-color-2) !important;}.has-theme-palette-color-3-border-color{border-color: var(--wp--preset--color--theme-palette-color-3) !important;}.has-theme-palette-color-4-border-color{border-color: var(--wp--preset--color--theme-palette-color-4) !important;}.has-theme-palette-color-5-border-color{border-color: var(--wp--preset--color--theme-palette-color-5) !important;}.has-theme-palette-color-6-border-color{border-color: var(--wp--preset--color--theme-palette-color-6) !important;}.has-theme-palette-color-7-border-color{border-color: var(--wp--preset--color--theme-palette-color-7) !important;}.has-theme-palette-color-8-border-color{border-color: var(--wp--preset--color--theme-palette-color-8) !important;}.has-vivid-cyan-blue-to-vivid-purple-gradient-background{background: var(--wp--preset--gradient--vivid-cyan-blue-to-vivid-purple) !important;}.has-light-green-cyan-to-vivid-green-cyan-gradient-background{background: var(--wp--preset--gradient--light-green-cyan-to-vivid-green-cyan) !important;}.has-luminous-vivid-amber-to-luminous-vivid-orange-gradient-background{background: var(--wp--preset--gradient--luminous-vivid-amber-to-luminous-vivid-orange) !important;}.has-luminous-vivid-orange-to-vivid-red-gradient-background{background: var(--wp--preset--gradient--luminous-vivid-orange-to-vivid-red) !important;}.has-very-light-gray-to-cyan-bluish-gray-gradient-background{background: var(--wp--preset--gradient--very-light-gray-to-cyan-bluish-gray) !important;}.has-cool-to-warm-spectrum-gradient-background{background: var(--wp--preset--gradient--cool-to-warm-spectrum) !important;}.has-blush-light-purple-gradient-background{background: var(--wp--preset--gradient--blush-light-purple) !important;}.has-blush-bordeaux-gradient-background{background: var(--wp--preset--gradient--blush-bordeaux) !important;}.has-luminous-dusk-gradient-background{background: var(--wp--preset--gradient--luminous-dusk) !important;}.has-pale-ocean-gradient-background{background: var(--wp--preset--gradient--pale-ocean) !important;}.has-electric-grass-gradient-background{background: var(--wp--preset--gradient--electric-grass) !important;}.has-midnight-gradient-background{background: var(--wp--preset--gradient--midnight) !important;}.has-juicy-peach-gradient-background{background: var(--wp--preset--gradient--juicy-peach) !important;}.has-young-passion-gradient-background{background: var(--wp--preset--gradient--young-passion) !important;}.has-true-sunset-gradient-background{background: var(--wp--preset--gradient--true-sunset) !important;}.has-morpheus-den-gradient-background{background: var(--wp--preset--gradient--morpheus-den) !important;}.has-plum-plate-gradient-background{background: var(--wp--preset--gradient--plum-plate) !important;}.has-aqua-splash-gradient-background{background: var(--wp--preset--gradient--aqua-splash) !important;}.has-love-kiss-gradient-background{background: var(--wp--preset--gradient--love-kiss) !important;}.has-new-retrowave-gradient-background{background: var(--wp--preset--gradient--new-retrowave) !important;}.has-plum-bath-gradient-background{background: var(--wp--preset--gradient--plum-bath) !important;}.has-high-flight-gradient-background{background: var(--wp--preset--gradient--high-flight) !important;}.has-teen-party-gradient-background{background: var(--wp--preset--gradient--teen-party) !important;}.has-fabled-sunset-gradient-background{background: var(--wp--preset--gradient--fabled-sunset) !important;}.has-arielle-smile-gradient-background{background: var(--wp--preset--gradient--arielle-smile) !important;}.has-itmeo-branding-gradient-background{background: var(--wp--preset--gradient--itmeo-branding) !important;}.has-deep-blue-gradient-background{background: var(--wp--preset--gradient--deep-blue) !important;}.has-strong-bliss-gradient-background{background: var(--wp--preset--gradient--strong-bliss) !important;}.has-sweet-period-gradient-background{background: var(--wp--preset--gradient--sweet-period) !important;}.has-purple-division-gradient-background{background: var(--wp--preset--gradient--purple-division) !important;}.has-cold-evening-gradient-background{background: var(--wp--preset--gradient--cold-evening) !important;}.has-mountain-rock-gradient-background{background: var(--wp--preset--gradient--mountain-rock) !important;}.has-desert-hump-gradient-background{background: var(--wp--preset--gradient--desert-hump) !important;}.has-ethernal-constance-gradient-background{background: var(--wp--preset--gradient--ethernal-constance) !important;}.has-happy-memories-gradient-background{background: var(--wp--preset--gradient--happy-memories) !important;}.has-grown-early-gradient-background{background: var(--wp--preset--gradient--grown-early) !important;}.has-morning-salad-gradient-background{background: var(--wp--preset--gradient--morning-salad) !important;}.has-night-call-gradient-background{background: var(--wp--preset--gradient--night-call) !important;}.has-mind-crawl-gradient-background{background: var(--wp--preset--gradient--mind-crawl) !important;}.has-angel-care-gradient-background{background: var(--wp--preset--gradient--angel-care) !important;}.has-juicy-cake-gradient-background{background: var(--wp--preset--gradient--juicy-cake) !important;}.has-rich-metal-gradient-background{background: var(--wp--preset--gradient--rich-metal) !important;}.has-mole-hall-gradient-background{background: var(--wp--preset--gradient--mole-hall) !important;}.has-cloudy-knoxville-gradient-background{background: var(--wp--preset--gradient--cloudy-knoxville) !important;}.has-soft-grass-gradient-background{background: var(--wp--preset--gradient--soft-grass) !important;}.has-saint-petersburg-gradient-background{background: var(--wp--preset--gradient--saint-petersburg) !important;}.has-everlasting-sky-gradient-background{background: var(--wp--preset--gradient--everlasting-sky) !important;}.has-kind-steel-gradient-background{background: var(--wp--preset--gradient--kind-steel) !important;}.has-over-sun-gradient-background{background: var(--wp--preset--gradient--over-sun) !important;}.has-premium-white-gradient-background{background: var(--wp--preset--gradient--premium-white) !important;}.has-clean-mirror-gradient-background{background: var(--wp--preset--gradient--clean-mirror) !important;}.has-wild-apple-gradient-background{background: var(--wp--preset--gradient--wild-apple) !important;}.has-snow-again-gradient-background{background: var(--wp--preset--gradient--snow-again) !important;}.has-confident-cloud-gradient-background{background: var(--wp--preset--gradient--confident-cloud) !important;}.has-glass-water-gradient-background{background: var(--wp--preset--gradient--glass-water) !important;}.has-perfect-white-gradient-background{background: var(--wp--preset--gradient--perfect-white) !important;}.has-small-font-size{font-size: var(--wp--preset--font-size--small) !important;}.has-medium-font-size{font-size: var(--wp--preset--font-size--medium) !important;}.has-large-font-size{font-size: var(--wp--preset--font-size--large) !important;}.has-x-large-font-size{font-size: var(--wp--preset--font-size--x-large) !important;}.has-larger-font-size{font-size: var(--wp--preset--font-size--larger) !important;}.has-xxlarge-font-size{font-size: var(--wp--preset--font-size--xxlarge) !important;}',
                'isGlobalStyles' => true
            ],
            [
                '__unstableType' => 'theme',
                'css'            => ':root { --wp--style--global--content-size: var(--theme-block-max-width);--wp--style--global--wide-size: var(--theme-block-wide-max-width); }:where(body) { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }:where(.wp-site-blocks) > * { margin-block-start: var(--theme-content-spacing); margin-block-end: 0; }:where(.wp-site-blocks) > :first-child { margin-block-start: 0; }:where(.wp-site-blocks) > :last-child { margin-block-end: 0; }:root { --wp--style--block-gap: var(--theme-content-spacing); }:root :where(.is-layout-flow) > :first-child{margin-block-start: 0;}:root :where(.is-layout-flow) > :last-child{margin-block-end: 0;}:root :where(.is-layout-flow) > *{margin-block-start: var(--theme-content-spacing);margin-block-end: 0;}:root :where(.is-layout-constrained) > :first-child{margin-block-start: 0;}:root :where(.is-layout-constrained) > :last-child{margin-block-end: 0;}:root :where(.is-layout-constrained) > *{margin-block-start: var(--theme-content-spacing);margin-block-end: 0;}:root :where(.is-layout-flex){gap: var(--theme-content-spacing);}:root :where(.is-layout-grid){gap: var(--theme-content-spacing);}.is-layout-flow > .alignleft{float: left;margin-inline-start: 0;margin-inline-end: 2em;}.is-layout-flow > .alignright{float: right;margin-inline-start: 2em;margin-inline-end: 0;}.is-layout-flow > .aligncenter{margin-left: auto !important;margin-right: auto !important;}.is-layout-constrained > .alignleft{float: left;margin-inline-start: 0;margin-inline-end: 2em;}.is-layout-constrained > .alignright{float: right;margin-inline-start: 2em;margin-inline-end: 0;}.is-layout-constrained > .aligncenter{margin-left: auto !important;margin-right: auto !important;}.is-layout-constrained > :where(:not(.alignleft):not(.alignright):not(.alignfull)){max-width: var(--wp--style--global--content-size);margin-left: auto !important;margin-right: auto !important;}.is-layout-constrained > .alignwide{max-width: var(--wp--style--global--wide-size);}body .is-layout-flex{display: flex;}.is-layout-flex{flex-wrap: wrap;align-items: center;}.is-layout-flex > :is(*, div){margin: 0;}body .is-layout-grid{display: grid;}.is-layout-grid > :is(*, div){margin: 0;}body{padding-top: 0px;padding-right: 0px;padding-bottom: 0px;padding-left: 0px;}',
                'isGlobalStyles' => true
            ],
            [
                '__unstableType' => 'user',
                'css'            => $colorSchmeaCss." :root{--theme-block-max-width: 700px;--global-calc-content-width: 700px;--theme-block-wide-max-width: 820px;--theme-font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif, \"Apple Color Emoji\", \"Segoe UI Emoji\", \"Segoe UI Symbol\";--theme-font-weight: 400;--theme-text-transform: none;--theme-text-decoration: none;--theme-font-size: 16px;--theme-line-height: 1.60;--theme-letter-spacing: 0em;--theme-button-font-weight: 500;--theme-button-font-size: 16px;--theme-palette-color-1: #4F46E5;--theme-palette-color-2: #7C3AED;--theme-palette-color-3: #1F2937;--theme-palette-color-4: #374151;--theme-palette-color-5: #6B7280;--theme-palette-color-6: #9CA3AF;--theme-palette-color-7: #E5E7EB;--theme-palette-color-8: #ffffff;--theme-text-color: var(--fcom-primary-text, #19283a);--theme-link-initial-color: var(--theme-palette-color-1);--theme-link-hover-color: var(--theme-palette-color-2);--theme-selection-text-color: #ffffff;--theme-selection-background-color: var(--theme-palette-color-1);--theme-border-color: var(--theme-palette-color-5);--theme-headings-color: var(--theme-palette-color-4);--theme-content-spacing: 1.5em;--theme-button-min-height: 40px;--theme-button-shadow: none;--theme-button-transform: none;--theme-button-text-initial-color: #ffffff;--theme-button-text-hover-color: #ffffff;--theme-button-background-initial-color: var(--theme-palette-color-1);--theme-button-background-hover-color: var(--theme-palette-color-2);--theme-button-border: none;--theme-button-padding: 5px 20px;--theme-normal-container-max-width: 1290px;--theme-content-vertical-spacing: 60px;--theme-container-edge-spacing: 90vw;--theme-narrow-container-max-width: 750px;--theme-wide-offset: 130px;--fcom-font-size-small: 16px;--fcom-font-size-medium: 18px;--fcom-font-size-large: 22px;--fcom-font-size-larger: 26px;--fcom-font-size-xxlarge: 32px;--wp--preset--spacing--20: 0.44rem;--wp--preset--spacing--30: 0.67rem;--wp--preset--spacing--40: 1rem;--wp--preset--spacing--50: 1.5rem;--wp--preset--spacing--60: 2.25rem;--wp--preset--spacing--70: 3.38rem;--wp--preset--spacing--80: 5.06rem}body .has-theme-palette-color-1-color{color:var(--theme-palette-color-1)}body .has-theme-palette-color-2-color{color:var(--theme-palette-color-2)}body .has-theme-palette-color-3-color{color:var(--theme-palette-color-3)}body .has-theme-palette-color-4-color{color:var(--theme-palette-color-4)}body .has-theme-palette-color-5-color{color:var(--theme-palette-color-5)}body .has-theme-palette-color-6-color{color:var(--theme-palette-color-6)}body .has-theme-palette-color-7-color{color:var(--theme-palette-color-7)}body .has-theme-palette-color-8-color{color:var(--theme-palette-color-8)}body .has-theme-palette-color-1-background-color{background-color:var(--theme-palette-color-1)}body .has-theme-palette-color-2-background-color{background-color:var(--theme-palette-color-2)}body .has-theme-palette-color-3-background-color{background-color:var(--theme-palette-color-3)}body .has-theme-palette-color-4-background-color{background-color:var(--theme-palette-color-4)}body .has-theme-palette-color-5-background-color{background-color:var(--theme-palette-color-5)}body .has-theme-palette-color-6-background-color{background-color:var(--theme-palette-color-6)}body .has-theme-palette-color-7-background-color{background-color:var(--theme-palette-color-7)}body .has-theme-palette-color-8-background-color{background-color:var(--theme-palette-color-8)}body .has-small-font-size{font-size:var(--fcom-font-size-small)}body .has-medium-font-size{font-size:var(--fcom-font-size-medium)}body .has-large-font-size{font-size:var(--fcom-font-size-large)}body .has-larger-font-size{font-size:var(--fcom-font-size-larger)}body .has-xxlarge-font-size{font-size:var(--fcom-font-size-xxlarge)}body .is-root-container>.alignfull{margin-inline:var(--has-wide, -20px)}body .is-root-container>.wp-block.alignleft{margin-inline-start:calc((100% - min(var(--theme-block-max-width),100%))/2)}body .is-root-container>.wp-block.alignright{margin-inline-end:calc((100% - min(var(--theme-block-max-width),100%))/2)}body :root .wp-element-button{font-family:var(--theme-button-font-family, var(--theme-font-family));font-size:var(--theme-button-font-size);font-weight:var(--theme-button-font-weight);font-style:var(--theme-button-font-style);line-height:var(--theme-button-line-height);letter-spacing:var(--theme-button-letter-spacing);text-transform:var(--theme-button-text-transform);-webkit-text-decoration:var(--theme-button-text-decoration);text-decoration:var(--theme-button-text-decoration)}body :root .wp-block-button[style*=font-weight] .wp-element-button{font-weight:inherit}body .wp-block-columns:last-child{margin-bottom:0}body .has-drop-cap:not(:focus):first-letter{font-size:5.8em;font-weight:700;margin:.1em .12em .05em 0}body figcaption{text-align:center;margin-block:.5em 0}body .wp-block-code,body .wp-block-verse,body .wp-block-preformatted{box-sizing:border-box;tab-size:4;padding:15px 20px;border-radius:3px;background:var(--theme-palette-color-7)}body blockquote{margin-inline:0}body blockquote:where(:not(.is-style-plain)):where(:not(.has-text-align-center):not(.has-text-align-right)){border-inline-start:4px solid var(--theme-palette-color-1)}body blockquote:where(:not(.is-style-plain)).has-text-align-center{padding-block:1.5em;border-block:3px solid var(--theme-palette-color-1)}body blockquote:where(:not(.is-style-plain)).has-text-align-right{border-inline-end:4px solid var(--theme-palette-color-1)}body blockquote:where(:not(.is-style-plain):not(.has-text-align-center):not(.has-text-align-right)){padding-inline-start:1.5em}body blockquote.has-text-align-right{padding-inline-end:1.5em}body blockquote p:last-child{margin-bottom:0}body blockquote cite{font-size:14px}body .wp-block-list{padding-left:30px}body .wp-block-pullquote{position:relative;padding:70px;text-align:initial;border-width:10px;border-style:solid;border-color:var(--theme-palette-color-1)}body .wp-block-pullquote blockquote{border:0;padding:0;margin:0;position:relative;isolation:isolate}body .wp-block-pullquote blockquote p{margin-top:0;margin-bottom:1em}body .wp-block-pullquote blockquote p:last-child{margin-bottom:0}body .wp-block-pullquote blockquote cite{font-size:16px;font-weight:500}body [data-align=left] .wp-block-pullquote,body [data-align=right] .wp-block-pullquote{max-width:50%;margin-top:.3em;margin-bottom:.3em}body .wp-block-table table{border-width:1px}body .wp-block-table table:not(.has-border-color) thead,body .wp-block-table table:not(.has-border-color) tfoot,body .wp-block-table table:not(.has-border-color) td,body .wp-block-table table:not(.has-border-color) th{border-color:var(--theme-table-border-color, var(--theme-border-color))}body .wp-block-table th:not([class*=has-text-align]){text-align:inherit}body .wp-block-table.is-style-stripes{border:0}body .wp-block-button.is-style-outline .wp-element-button{padding:var(--theme-button-padding);border:2px solid;border-color:var(--theme-button-background-initial-color)}body .wp-block-button.is-style-outline .wp-element-button:not(.has-text-color){color:var(--theme-button-background-initial-color)}body .wp-block-button.is-style-outline .wp-element-button:hover{color:var(--theme-button-text-hover-color);border-color:var(--theme-button-background-hover-color);background-color:var(--theme-button-background-hover-color)}body .wp-block-separator{border:none;margin-inline:auto;color:var(--theme-form-field-border-initial-color)}body .wp-block-separator:not(:where(.is-style-wide,.is-style-dots,.alignfull,.alignwide)){max-width:100px !important}body .wp-block-separator:not(.is-style-dots){height:2px;background-color:currentColor}body :root :where(p.has-background,.wp-block-group.has-background){padding:30px;box-sizing:border-box}body h1.has-background,body h2.has-background,body h3.has-background,body h4.has-background,body h5.has-background,body h6.has-background{padding:1.25em 2.375em}body{background-color:var(--fcom-primary-bg, white);background-image:none;font-family:var(--theme-font-family);line-height:var(--theme-line-height)}body .is-root-container{font-size:var(--theme-font-size, 16px);font-family:var(--theme-font-family, -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif, \"Apple Color Emoji\", \"Segoe UI Emoji\", \"Segoe UI Symbol\")}.block-editor-iframe__html.is-zoomed-out .block-editor-iframe__body{padding:20px 30px}.editor-visual-editor__post-title-wrapper.edit-post-visual-editor__post-title-wrapper{margin-top:0px !important;padding-top:0;margin-bottom:30px;position:relative}.editor-visual-editor__post-title-wrapper.edit-post-visual-editor__post-title-wrapper h1{font-size:32px;font-weight:700}h1{--theme-font-weight: 700;--theme-font-size: 40px;--theme-line-height: 1.5}h2{--theme-font-weight: 700;--theme-font-size: 35px;--theme-line-height: 1.5}h3{--theme-font-weight: 700;--theme-font-size: 30px;--theme-line-height: 1.5}h4{--theme-font-weight: 700;--theme-font-size: 25px;--theme-line-height: 1.5}h5{--theme-font-weight: 700;--theme-font-size: 20px;--theme-line-height: 1.5}h6{--theme-font-weight: 700;--theme-font-size: 16px;--theme-line-height: 1.5}.wp-block-pullquote{--theme-font-family: Georgia;--theme-font-weight: 600;--theme-font-size: 25px}pre,code,samp,kbd{--theme-font-family: monospace;--theme-font-weight: 400;--theme-font-size: 16px}figcaption{--theme-font-size: 14px}li::marker{color:#959595}.editor-styles-wrapper{--true: initial;--false: ;--wp--style--global--content-size: var(--theme-block-max-width);--wp--style--global--wide-size: var(--theme-block-wide-max-width);box-sizing:border-box;border:var(--has-boxed, var(--theme-boxed-content-border));padding:var(--has-boxed, var(--theme-boxed-content-spacing));box-shadow:var(--has-boxed, var(--theme-boxed-content-box-shadow));border-radius:var(--has-boxed, var(--theme-boxed-content-border-radius));margin-inline:auto;margin-block:var(--has-boxed, 20px);width:calc(100% - 40px);max-width:100%}:is(.is-layout-flow,.is-layout-constrained)>*:where(:not(h1,h2,h3,h4,h5,h6)){margin-block-start:0;margin-block-end:var(--theme-content-spacing)}:is(.is-layout-flow,.is-layout-constrained) :where(h1,h2,h3,h4,h5,h6){margin-block-end:calc(var(--has-theme-content-spacing, 1)*(.3em + 10px))}:root{color:var(--theme-text-color)}:root a{color:var(--theme-link-initial-color)}.block-editor-block-list__layout.is-root-container>.alignwide{max-width:var(--theme-block-wide-max-width);box-sizing:border-box}.is-root-container{padding:0 20px}\n"
            ],
            [
                'css'            => file_get_contents(FLUENT_COMMUNITY_PLUGIN_DIR . 'Modules/Gutenberg/editor/editor.css'),
                '__unstableType' => 'user'
            ]
        ];

        $resovedStyles = [
            'wp-components-css'           => includes_url('/css/dist/components/style.min.css'),
            'wp-preferences-css'          => includes_url('/css/dist/preferences/style.min.css'),
            'wp-block-editor-css'         => includes_url('/css/dist/block-editor/style.min.css'),
            'wp-reusable-blocks-css'      => includes_url('/css/dist/reusable-blocks/style.min.css'),
            'wp-patterns-css'             => includes_url('/css/dist/patterns/style.min.css'),
            'wp-editor-css'               => includes_url('/css/dist/editor/style.min.css'),
            'wp-block-library-css'        => includes_url('/css/dist/block-library/style.min.css'),
            'wp-block-editor-content-css' => includes_url('/css/dist/block-editor/content.min.css'),
            'wp-edit-blocks-css'          => includes_url('/css/dist/block-library/editor.min.css'),
        ];

        global $wp_version;
        $cssFiles = '';
        foreach ($resovedStyles as $name => $file) {
            $cssFiles .= "<link rel='stylesheet' id='{$name}' href='{$file}?ver={$wp_version}' media='all' />\n"; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
        }

        $editor_settings['__unstableResolvedAssets'] = [
            'scripts' => '<script src="' . includes_url('/js/dist/vendor/wp-polyfill.min.js?ver=3.15.0') . '" id="wp-polyfill-js"></script>', // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
            'styles'  => $cssFiles
        ];

        $editor_settings['defaultEditorStyles'] = [
            [
                'css' => ':root{--wp-admin-theme-color:#007cba;--wp-admin-theme-color--rgb:0, 124, 186;--wp-admin-theme-color-darker-10:#006ba1;--wp-admin-theme-color-darker-10--rgb:0, 107, 161;--wp-admin-theme-color-darker-20:#005a87;--wp-admin-theme-color-darker-20--rgb:0, 90, 135;--wp-admin-border-width-focus:2px;--wp-block-synced-color:#7a00df;--wp-block-synced-color--rgb:122, 0, 223;--wp-bound-block-color:var(--wp-block-synced-color);}@media (min-resolution:192dpi){:root{--wp-admin-border-width-focus:1.5px;}}body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Oxygen-Sans,Ubuntu,Cantarell,Helvetica Neue,sans-serif;font-size:18px;line-height:1.5;--wp--style--block-gap:2em;}p{line-height:1.8;}.editor-post-title__block{font-size:2.5em;font-weight:800;margin-bottom:1em;margin-top:2em;}'
            ]
        ];
        $editor_settings['imageSizes'] = $this->gutenberg_get_available_image_sizes();

        $editor_settings = apply_filters('fluent_community/block_editor_settings', $editor_settings);
        return $editor_settings;
    }
}
