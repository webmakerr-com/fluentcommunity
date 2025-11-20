<?php

namespace FluentCommunity\Modules;

use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Foundation\Application;
use FluentCommunity\Framework\Support\Arr;

class FeaturesHandler
{
    public function register(Application $app)
    {
        add_filter('fluent_community/portal_page_headless', '__return_true');

        $this->registerModules($app);

        add_action('fluent_community/portal_header', [$this, 'maybePushDarkModeScript']);
        add_action('fluent_community/portal_sidebar', [$this, 'maybePushDarkModeScript']);
        add_action('init', [$this, 'maybeOembedRequest']);

        add_action('fluent_community/rendering_headless_portal', [$this, 'loadHeadlessPortalAssets']);
    }

    public function loadHeadlessPortalAssets($data)
    {
        add_action('fluent_community/portal_head', function () use ($data) {
            $cssFiles = Arr::get($data, 'css_files', []);
            foreach ($cssFiles as $file) {
                ?>
                <link rel="stylesheet" href="<?php echo esc_url($file['url']); ?>?version=<?php echo esc_attr(FLUENT_COMMUNITY_PLUGIN_VERSION); ?>" media="screen"/> <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet ?>
                <?php
            }
            $jsFiles = Arr::get($data, 'header_js_files', []);
            foreach ($jsFiles as $file) {
                $jsVars = Arr::get($file, 'vars', []);
                foreach ($jsVars as $varKey => $values) {
                    ?>
                    <script>
                        var <?php echo esc_attr($varKey); ?> = <?php echo wp_json_encode($values); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped  ?>;
                    </script>
                    <?php } ?>
                    <script type="module" src="<?php echo esc_url($file['url']); ?>?version=<?php echo esc_attr(FLUENT_COMMUNITY_PLUGIN_VERSION); ?>" defer="defer"></script> <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
                    <?php
            }
        });

        add_action('fluent_community/before_js_loaded', function () use ($data) {
            $jsVars = Arr::get($data, 'js_vars', []);
            ?>
            <script>
                <?php foreach ($jsVars as $varKey => $values): ?>
                var <?php echo esc_attr($varKey); ?> = <?php echo wp_json_encode($values); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped  ?>;
                <?php endforeach; ?>
            </script>
            <?php
        });

        add_action('fluent_community/portal_footer', function () use ($data) {
            $jsFiles = Arr::get($data, 'js_files', []);
            foreach ($jsFiles as $file) {
                ?>
                <script type="module" src="<?php echo esc_url($file['url']); ?>?version=<?php echo esc_attr(FLUENT_COMMUNITY_PLUGIN_VERSION); ?>" defer="defer"></script> <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
                <?php
            }
        });

    }

    public function maybePushDarkModeScript()
    {

    }

    public function maybeOembedRequest()
    {
        // this is temp hack
        if (!isset($_GET['url'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        if (defined('REST_REQUEST')) {
            return;
        }

        if (!is_user_logged_in()) {
            return;
        }

        $requestUrl = Arr::get($_SERVER, 'REQUEST_URI');
        if (!strpos($requestUrl, 'oembed/1.0/proxy')) {
            return;
        }

        global $wp_embed, $wp_scripts;
        $url = sanitize_url(wp_unslash($_GET['url'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $request = \FluentCommunity\App\App::make('request');

        $args = $request->all();
        unset($args['url']);

        // Copy maxwidth/maxheight to width/height since WP_oEmbed::fetch() uses these arg names.
        if (isset($args['maxwidth'])) {
            $args['width'] = $args['maxwidth'];
        } else {
            $args['maxwidth'] = 600;
        }

        if (isset($args['maxheight'])) {
            $args['height'] = $args['maxheight'];
        }

        // Short-circuit process for URLs belonging to the current site.
        $data = get_oembed_response_data_for_url($url, $args);

        if ($data) {
            wp_send_json($data, 200);
        }

        $data = _wp_oembed_get_object()->get_data($url, $args);

        if (false === $data) {
            // Try using a classic embed, instead.
            /* @var \WP_Embed $wp_embed */
            $html = $wp_embed->get_embed_handler_html($args, $url);

            if ($html) {
                // Check if any scripts were enqueued by the shortcode, and include them in the response.
                $enqueued_scripts = array();

                foreach ($wp_scripts->queue as $script) {
                    $enqueued_scripts[] = $wp_scripts->registered[$script]->src;
                }

                wp_send_json(array(
                    'provider_name' => __('Embed Handler', 'fluent-community'),
                    'html'          => $html,
                    'scripts'       => $enqueued_scripts,
                ), 200);
            }

            wp_send_json([
                'error' => 'not_found',
            ], 404);
        }

        /** This filter is documented in wp-includes/class-wp-oembed.php */
        $data->html = apply_filters('oembed_result', _wp_oembed_get_object()->data2html((object)$data, $url), $url, $args); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        wp_send_json($data, 200);
    }

    private function registerModules($app)
    {
        if (Helper::isFeatureEnabled('course_module')) {
            // Regitser the modules here
            (new \FluentCommunity\Modules\Course\CourseModule())->register($app);
        }

        if (apply_filters('fluent_community/use_editor_block', true)) {
            (new \FluentCommunity\Modules\Gutenberg\EditorBlock())->register($app);
        }

        (new \FluentCommunity\Modules\Auth\AuthModdule())->register($app);
    }
}
