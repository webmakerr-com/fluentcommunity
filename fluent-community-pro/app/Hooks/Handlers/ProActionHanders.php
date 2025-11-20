<?php

namespace FluentCommunityPro\App\Hooks\Handlers;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\Modules\Auth\Classes\InvitationService;
use FluentCommunityPro\App\Services\ProHelper;

class ProActionHanders
{
    public function register()
    {
        add_filter('fluent_community/color_schmea_config', function ($config, $context) {
            $existingConfig = \FluentCommunity\App\Functions\Utility::getOption('portal_color_config', $config);
            $config = wp_parse_args($existingConfig, $config);
            return $config;
        }, 10, 2);

        add_action('fluent_community/recache_color_schema', [$this, 'recacheColors']);

        add_filter('fluent_community/create_invitation_link', function ($invitation, $indivatationData) {
            return InvitationService::createLinkInvite($indivatationData);
        }, 1, 2);

        add_action('fluent_community/update_profile_link_providers', function ($config) {
            Utility::updateOption('enabled_profile_link_keys', $config);
        });

        add_filter('fluent_community/course/can_view_lesson', function ($canView, $lesson) {
            if ($lesson->is_free_preview) {
                return true;
            }
            return $canView;
        }, 10, 2);

        /*
         * Custom Snippet handlers
         */
        add_action('fluent_community/portal_head', [$this, 'addCustomCss'], 999);
        add_action('fluent_community/template_header', [$this, 'addCustomCss'], 999);
        add_action('fluent_community/portal_footer', [$this, 'addCustomJs'], 999);
    }

    public function recacheColors()
    {
        Utility::forgetCache('option_portal_color_config');
        $allSchemas = Utility::getColorSchemas();
        $settings = Utility::getOption('portal_color_config');
        $lightSchema = Arr::get($settings, 'light_schema', 'default');
        $darkSchema = Arr::get($settings, 'dark_schema', 'default');
        $lightCss = Utility::generateCss(Arr::get($allSchemas, "lightSkins.$lightSchema.selectors"));
        $darkCss = Utility::generateCss(Arr::get($allSchemas, "darkSkins.$darkSchema.selectors"), 'html.dark');
        $settings['cached_css'] = $lightCss . ' ' . $darkCss;
        $settings['version'] = FLUENT_COMMUNITY_PLUGIN_VERSION;
        Utility::updateOption('portal_color_config', $settings);
        return true;
    }

    public function addCustomCss()
    {
        $snippets = ProHelper::getSnippetsSettings();

        if (!Arr::get($snippets, 'custom_css')) {
            return;
        }

        ?>
        <style id="fcom_custom_css">
            <?php echo ProHelper::sanitizeCSS($snippets['custom_css']); ?>
        </style>
        <?php
    }

    public function addCustomJs()
    {
        $snippets = ProHelper::getSnippetsSettings();
        echo Arr::get($snippets, 'custom_js');
    }
}
