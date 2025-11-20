<?php

namespace FluentCommunity\App\Hooks\Handlers;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Services\AdminTransStrings;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\App\Vite;
use FluentCommunity\Framework\Support\Arr;

class PortalSettingsHandler
{
    public function register()
    {
        add_filter('fluent_community/portal_data_vars', function ($vars) {
            if (Arr::get($vars, 'route_group') != 'admin') {
                return $vars;
            }

            $user = Helper::getCurrentUser();
            if (!$user) {
                wp_safe_redirect(Helper::baseUrl());
                exit();
            }

            $roles = $user->getCommunityRoles();

            $acceptedRoles = ['admin', 'moderator', 'course_admin', 'course_creator'];

            if(!$roles || !array_intersect($roles, $acceptedRoles)) {
                wp_safe_redirect(Helper::baseUrl());
                exit();
            }

            $isRtl = Helper::isRtl();
            unset($vars['js_files']['fcom_app_admin']);
            $vars['js_files']['fcom_app'] = [
                'url'  => Vite::getStaticSrcUrl('admin_app.js'),
                'deps' => []
            ];

            if (!Utility::isDev()) {
                if ($isRtl) {
                    $fileName = 'admin_app.rtl.css';
                } else {
                    $fileName = 'admin_app.css';
                }

                $vars['css_files']['fcom_admin_vendor'] = [
                    'url' => Vite::getStaticSrcUrl($fileName)
                ];
            }

            add_filter('fluent_community/header_vars', function ($vars) {
                $vars['menuItems'] = [];
                return $vars;
            });

            add_filter('fluent_community/will_render_default_sidebar_items', '__return_false');

            add_action('fluent_community/after_header_menu', function () {
                echo '<h4 style="margin: 0; font-size: 20px;">' . esc_html__('Portal Settings', 'fluent-community') . '</h4>';
            });

            $settingsMenuItems = apply_filters('fluent_community/portal_settings_menu_items', $this->getPortalSettingsMenuItems());

            $vars['js_vars']['fluentComAdmin']['portalSettingsMenus'] = $settingsMenuItems;

            $vars['js_vars']['fluentComAdmin']['portal_slug'] = ltrim(Helper::getPortalSlug(true), '/') . '/admin';

            $vars['js_vars']['fluentComAdmin']['verified_email_senders'] = Utility::getVerifiedSenders();

            return $vars;
        });

        add_action('admin_enqueue_scripts', function () {
            if (isset($_GET['page']) && $_GET['page'] === 'fluent-community') { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                wp_enqueue_style('fluent_community_admin', Vite::getStaticSrcUrl('onboarding.css'), [], FLUENT_COMMUNITY_PLUGIN_VERSION);
            }
        });

        // add a link to admin menu which will redirect to /portal
        add_action('admin_menu', function () {
            add_menu_page(
                'FluentCommunity',
                'FluentCommunity',
                'edit_posts',
                'fluent-community',
                [$this, 'showAdminPage'],
                $this->getMenuIcon(),
                130
            );
        });
    }

    public function getPortalSettingsMenuItems()
    {
        if (!Helper::isSiteAdmin()) {
            return [];
        }

        return [
            'settings'            => [
                'label'    => __('General', 'fluent-community'),
                'route'    => 'settings',
                'icon_svg' => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.08301 10.0002C2.08301 6.26821 2.08301 4.40223 3.24238 3.24287C4.40175 2.0835 6.26772 2.0835 9.99967 2.0835C13.7316 2.0835 15.5976 2.0835 16.757 3.24287C17.9163 4.40223 17.9163 6.26821 17.9163 10.0002C17.9163 13.7321 17.9163 15.5981 16.757 16.7575C15.5976 17.9168 13.7316 17.9168 9.99967 17.9168C6.26772 17.9168 4.40175 17.9168 3.24238 16.7575C2.08301 15.5981 2.08301 13.7321 2.08301 10.0002Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M8.33301 12.917C8.33301 13.6073 7.77336 14.167 7.08301 14.167C6.39265 14.167 5.83301 13.6073 5.83301 12.917C5.83301 12.2266 6.39265 11.667 7.08301 11.667C7.77336 11.667 8.33301 12.2266 8.33301 12.917Z" stroke="currentColor" stroke-width="1.5"/><path d="M14.166 7.0835C14.166 6.39314 13.6064 5.8335 12.916 5.8335C12.2257 5.8335 11.666 6.39314 11.666 7.0835C11.666 7.77385 12.2257 8.3335 12.916 8.3335C13.6064 8.3335 14.166 7.77385 14.166 7.0835Z" stroke="currentColor" stroke-width="1.5"/><path d="M7.08301 11.6668L7.08301 5.8335" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M12.916 8.33366L12.916 14.167" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>'
            ],
            'customization'       => [
                'label'    => __('Customizations', 'fluent-community'),
                'route'    => 'customization',
                'icon_svg' => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_3687_33463)"><path d="M18.3337 9.99984C18.3337 5.39746 14.6027 1.6665 10.0003 1.6665C5.39795 1.6665 1.66699 5.39746 1.66699 9.99984C1.66699 14.6022 5.39795 18.3332 10.0003 18.3332C10.7018 18.3332 11.667 18.4301 11.667 17.4998C11.667 16.9924 11.403 16.6008 11.1408 16.2119C10.7572 15.6428 10.3773 15.0793 10.8337 14.1665C11.3892 13.0554 12.3152 13.0554 13.7349 13.0554C14.4448 13.0554 15.2781 13.0554 16.2504 12.9165C18.0012 12.6664 18.3337 11.5902 18.3337 9.99984Z" stroke="currentColor" stroke-width="1.5"/><path d="M5.83398 12.502L5.84121 12.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><ellipse cx="7.91699" cy="7.08301" rx="1.25" ry="1.25" stroke="currentColor" stroke-width="1.5"/><ellipse cx="13.75" cy="7.9165" rx="1.25" ry="1.25" stroke="currentColor" stroke-width="1.5"/></g><defs><clipPath id="clip0_3687_33463"><rect width="20" height="20" fill="white"/></clipPath></defs></svg>'
            ],
            'admin_moderators'    => [
                'label'    => __('Managers', 'fluent-community'),
                'route'    => 'admin_moderators',
                'icon_svg' => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M17.3117 15C17.9361 15 18.4328 14.6071 18.8787 14.0576C19.7916 12.9329 18.2928 12.034 17.7211 11.5938C17.14 11.1463 16.4912 10.8928 15.8333 10.8333M15 9.16667C16.1506 9.16667 17.0833 8.23393 17.0833 7.08333C17.0833 5.93274 16.1506 5 15 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M2.68797 15C2.06355 15 1.5669 14.6071 1.12096 14.0576C0.208082 12.9329 1.7069 12.034 2.27855 11.5938C2.85965 11.1463 3.50849 10.8928 4.16634 10.8333M4.58301 9.16667C3.43241 9.16667 2.49967 8.23393 2.49967 7.08333C2.49967 5.93274 3.43241 5 4.58301 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M6.73618 12.593C5.8847 13.1195 3.65216 14.1946 5.01192 15.5398C5.67616 16.197 6.41594 16.667 7.34603 16.667H12.6533C13.5834 16.667 14.3232 16.197 14.9874 15.5398C16.3472 14.1946 14.1147 13.1195 13.2632 12.593C11.2665 11.3583 8.73289 11.3583 6.73618 12.593Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M12.9163 6.25016C12.9163 7.86099 11.6105 9.16683 9.99967 9.16683C8.38884 9.16683 7.08301 7.86099 7.08301 6.25016C7.08301 4.63933 8.38884 3.3335 9.99967 3.3335C11.6105 3.3335 12.9163 4.63933 12.9163 6.25016Z" stroke="currentColor" stroke-width="1.5"/></svg>'
            ],
            'email_notifications' => [
                'label'    => __('Email Settings', 'fluent-community'),
                'route'    => 'email_notifications',
                'icon_svg' => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5.83398 7.08301L8.28566 8.53253C9.715 9.37761 10.2863 9.37761 11.7156 8.53253L14.1673 7.08301" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M1.68013 11.2295C1.73461 13.7841 1.76185 15.0614 2.70446 16.0076C3.64706 16.9538 4.95894 16.9868 7.58268 17.0527C9.19975 17.0933 10.8009 17.0933 12.418 17.0527C15.0417 16.9868 16.3536 16.9538 17.2962 16.0076C18.2388 15.0614 18.266 13.7841 18.3205 11.2295C18.338 10.4081 18.338 9.59157 18.3205 8.77017C18.266 6.21555 18.2388 4.93825 17.2962 3.99206C16.3536 3.04586 15.0417 3.0129 12.418 2.94698C10.8009 2.90635 9.19975 2.90635 7.58268 2.94697C4.95893 3.01289 3.64706 3.04585 2.70445 3.99204C1.76184 4.93824 1.73461 6.21554 1.68013 8.77015C1.66261 9.59156 1.66261 10.4081 1.68013 11.2295Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>'
            ],
            'features'            => [
                'label'    => __('Features & Addons', 'fluent-community'),
                'route'    => 'features',
                'icon_svg' => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_3687_33503)"><path d="M3.33301 10.0002C3.33301 6.85746 3.33301 5.28612 4.30932 4.3098C5.28563 3.3335 6.85697 3.3335 9.99967 3.3335C13.1423 3.3335 14.7138 3.3335 15.69 4.3098C16.6663 5.28612 16.6663 6.85746 16.6663 10.0002C16.6663 13.1428 16.6663 14.7142 15.69 15.6905C14.7138 16.6668 13.1423 16.6668 9.99967 16.6668C6.85697 16.6668 5.28563 16.6668 4.30932 15.6905C3.33301 14.7142 3.33301 13.1428 3.33301 10.0002Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M6.4432 13.5567C7.0534 14.1668 8.03549 14.1668 9.99967 14.1668C10.6578 14.1668 11.2058 14.1668 11.6663 14.1438L14.1433 11.6668C14.1663 11.2062 14.1663 10.6583 14.1663 10.0002C14.1663 8.03598 14.1663 7.05389 13.5562 6.44369C12.9459 5.8335 11.9638 5.8335 9.99967 5.8335C8.03549 5.8335 7.0534 5.8335 6.4432 6.44369C5.83301 7.05389 5.83301 8.03598 5.83301 10.0002C5.83301 11.9643 5.83301 12.9464 6.4432 13.5567Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M6.66699 1.6665V3.33317M13.3337 1.6665V3.33317M10.0003 1.6665V3.33317M6.66699 16.6665V18.3332M10.0003 16.6665V18.3332M13.3337 16.6665V18.3332M18.3337 13.3332H16.667M3.33366 6.6665H1.66699M3.33366 13.3332H1.66699M3.33366 9.99984H1.66699M18.3337 6.6665H16.667M18.3337 9.99984H16.667" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></g><defs><clipPath id="clip0_3687_33503"><rect width="20" height="20" fill="white"/></clipPath></defs></svg>'
            ],
            'all_topics'          => [
                'label'    => __('Manage Topics', 'fluent-community'),
                'route'    => 'all_topics',
                'icon_svg' => '<svg viewBox="0 0 24 24" width="20" height="20" color="currentColor" fill="none"><path d="M2.73552 11.6867C1.78253 12.7511 1.76203 14.3569 2.63665 15.4865C4.37226 17.7281 6.2719 19.6277 8.51351 21.3633C9.64313 22.238 11.2489 22.2175 12.3133 21.2645C15.203 18.6771 17.8494 15.9731 20.4033 13.0016C20.6558 12.7078 20.8137 12.3477 20.8492 11.9619C21.0059 10.2561 21.3279 5.34144 19.9932 4.00675C18.6586 2.67207 13.7439 2.99408 12.0381 3.15083C11.6523 3.18627 11.2922 3.34421 10.9984 3.59671C8.02692 6.15064 5.32291 8.797 2.73552 11.6867Z" stroke="currentColor" stroke-width="1.5" /><path opacity="0.4" d="M7.5 14.5L9.5 16.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M18 6L22 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg>'
            ],
            'space_groups'        => [
                'label'    => __('Space Groups', 'fluent-community'),
                'route'    => 'space_groups',
                'icon_svg' => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_3687_33515)"><path d="M5.00033 3.33317C5.00033 4.25365 4.25413 4.99984 3.33366 4.99984C2.41318 4.99984 1.66699 4.25365 1.66699 3.33317C1.66699 2.4127 2.41318 1.6665 3.33366 1.6665C4.25413 1.6665 5.00033 2.4127 5.00033 3.33317Z" stroke="currentColor" stroke-width="1.5"/><path d="M18.3333 3.33317C18.3333 4.25365 17.5872 4.99984 16.6667 4.99984C15.7462 4.99984 15 4.25365 15 3.33317C15 2.4127 15.7462 1.6665 16.6667 1.6665C17.5872 1.6665 18.3333 2.4127 18.3333 3.33317Z" stroke="currentColor" stroke-width="1.5"/><path d="M18.3333 16.6667C18.3333 17.5872 17.5872 18.3333 16.6667 18.3333C15.7462 18.3333 15 17.5872 15 16.6667C15 15.7462 15.7462 15 16.6667 15C17.5872 15 18.3333 15.7462 18.3333 16.6667Z" stroke="currentColor" stroke-width="1.5"/><path d="M5.00033 16.6667C5.00033 17.5872 4.25413 18.3333 3.33366 18.3333C2.41318 18.3333 1.66699 17.5872 1.66699 16.6667C1.66699 15.7462 2.41318 15 3.33366 15C4.25413 15 5.00033 15.7462 5.00033 16.6667Z" stroke="currentColor" stroke-width="1.5"/><path d="M16.6663 5.00016V15.0002M14.9997 16.6668H4.99967M14.9997 3.3335H4.99967M3.33301 5.00016V15.0002" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M13.75 7.5C13.75 7.11172 13.75 6.91758 13.6866 6.76443C13.602 6.56024 13.4397 6.39801 13.2356 6.31343C13.0824 6.25 12.8882 6.25 12.5 6.25H7.5C7.11172 6.25 6.91758 6.25 6.76443 6.31343C6.56024 6.39801 6.39801 6.56024 6.31343 6.76443C6.25 6.91758 6.25 7.11172 6.25 7.5C6.25 7.88828 6.25 8.08242 6.31343 8.23557C6.39801 8.43975 6.56024 8.602 6.76443 8.68658C6.91758 8.75 7.11172 8.75 7.5 8.75H12.5C12.8882 8.75 13.0824 8.75 13.2356 8.68658C13.4397 8.602 13.602 8.43975 13.6866 8.23557C13.75 8.08242 13.75 7.88828 13.75 7.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M13.75 12.5C13.75 12.1117 13.75 11.9176 13.6866 11.7644C13.602 11.5603 13.4397 11.398 13.2356 11.3134C13.0824 11.25 12.8882 11.25 12.5 11.25H7.5C7.11172 11.25 6.91758 11.25 6.76443 11.3134C6.56024 11.398 6.39801 11.5603 6.31343 11.7644C6.25 11.9176 6.25 12.1117 6.25 12.5C6.25 12.8882 6.25 13.0824 6.31343 13.2356C6.39801 13.4397 6.56024 13.602 6.76443 13.6866C6.91758 13.75 7.11172 13.75 7.5 13.75H12.5C12.8882 13.75 13.0824 13.75 13.2356 13.6866C13.4397 13.602 13.602 13.4397 13.6866 13.2356C13.75 13.0824 13.75 12.8882 13.75 12.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></g><defs><clipPath id="clip0_3687_33515"><rect width="20" height="20" fill="white"/></clipPath></defs></svg>'
            ],
            'menu_settings'       => [
                'label'    => __('Menu Settings', 'fluent-community'),
                'route'    => 'menu_settings',
                'icon_svg' => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8.33301 4.1665L16.6663 4.1665" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M3.33301 10L16.6663 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M3.33301 15.833L11.6663 15.833" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
            ],
            'content_moderation'          => [
                'label'    => __('Content Moderation', 'fluent-community'),
                'route'    => 'content_moderation',
                'icon_svg' => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.99818 1.6665C7.4917 1.6665 5.86649 3.349 3.94444 3.96226C3.16291 4.21161 2.77215 4.33629 2.61401 4.51205C2.45587 4.6878 2.40956 4.94463 2.31694 5.45828C1.32587 10.9548 3.49209 16.0365 8.65825 18.0144C9.21333 18.2269 9.49087 18.3332 10.0009 18.3332C10.511 18.3332 10.7885 18.2269 11.3435 18.0144C16.5093 16.0365 18.6735 10.9548 17.6822 5.45828C17.5895 4.94454 17.5432 4.68767 17.385 4.51191C17.2268 4.33615 16.8361 4.21154 16.0546 3.96233C14.1318 3.34913 12.5047 1.6665 9.99818 1.6665Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M7.5 10.8333C7.5 10.8333 8.33333 10.8333 9.16667 12.5C9.16667 12.5 11.8137 8.33333 14.1667 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
            ],
            'privacy_settings'    => [
                'label'    => __('Privacy Settings', 'fluent-community'),
                'route'    => 'privacy_settings',
                'icon_svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" color="currentColor" fill="none"><path d="M10.8338 1.53871C11.5609 1.15376 12.4391 1.15376 13.1662 1.53871C14.3681 2.17509 16.8304 3.32075 19.7836 3.8341C20.873 4.02349 21.75 4.95478 21.75 6.12325V11.0511C21.75 14.5419 19.9704 17.2085 18.0079 19.0834C16.0479 20.9559 13.848 22.0975 12.8466 22.5619C12.3057 22.8127 11.6943 22.8127 11.1534 22.5619C10.152 22.0975 7.95205 20.9559 5.99214 19.0834C4.02964 17.2085 2.25 14.5419 2.25 11.0511V6.12325C2.25 4.95478 3.12696 4.02349 4.21644 3.8341C7.1696 3.32075 9.63189 2.17509 10.8338 1.53871Z" fill="currentColor" /></svg>'
            ],
            'crm_access_config'    => [
                'label'    => __('Access Management', 'fluent-community'),
                'route'    => 'crm_access_config',
                'icon_svg' => '<svg width="100%" height="100%" viewBox="0 0 300 235" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve" xmlns:serif="http://www.serif.com/" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:2;"><g><path d="M300,0c0,0 -211.047,56.55 -279.113,74.788c-12.32,3.301 -20.887,14.466 -20.887,27.221l0,38.719c0,0 169.388,-45.387 253.602,-67.952c27.368,-7.333 46.398,-32.134 46.398,-60.467c0,-7.221 0,-12.309 0,-12.309Z"/><path d="M184.856,124.521c0,-0 -115.6,30.975 -163.969,43.935c-12.32,3.302 -20.887,14.466 -20.887,27.221l0,38.719c0,0 83.701,-22.427 138.458,-37.099c27.368,-7.334 46.398,-32.134 46.398,-60.467c0,-7.221 0,-12.309 0,-12.309Z"/></g></svg>'
            ],
            'incoming_webhooks' => [
                'label' => __('Incoming Webhook', 'fluent-community'),
                'route' => 'incoming_webhooks',
                'icon_svg' => '<svg viewBox="0 0 24 24" width="20" height="20" color="currentColor" fill="none"><path d="M5.062 13C3.83229 13.6824 3 14.994 3 16.5C3 18.7091 4.79086 20.5 7 20.5C9.20914 20.5 11 18.7091 11 16.5H17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M12 7.5L15.0571 13.0027C15.6323 12.6825 16.2949 12.5 17 12.5C19.2091 12.5 21 14.2909 21 16.5C21 18.7091 19.2091 20.5 17 20.5C16.0541 20.5 15.1848 20.1716 14.5 19.6227" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /><path d="M12 8.5C12.5523 8.5 13 8.05228 13 7.5C13 6.94772 12.5523 6.5 12 6.5M12 8.5C11.4477 8.5 11 8.05228 11 7.5C11 6.94772 11.4477 6.5 12 6.5M12 8.5V6.5" stroke="currentColor" stroke-width="1.5" /><path d="M7 17.5C7.55228 17.5 8 17.0523 8 16.5C8 15.9477 7.55228 15.5 7 15.5M7 17.5C6.44772 17.5 6 17.0523 6 16.5C6 15.9477 6.44772 15.5 7 15.5M7 17.5V15.5" stroke="currentColor" stroke-width="1.5" /><path d="M17 17.5C17.5523 17.5 18 17.0523 18 16.5C18 15.9477 17.5523 15.5 17 15.5M17 17.5C16.4477 17.5 16 17.0523 16 16.5C16 15.9477 16.4477 15.5 17 15.5M17 17.5V15.5" stroke="currentColor" stroke-width="1.5" /><path d="M16 7.5C16 5.29086 14.2091 3.5 12 3.5C9.79086 3.5 8 5.29086 8 7.5C8 9.004 8.83007 10.3141 10.0571 10.9973L7 16.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>'
            ],
            'tools'               => [
                'label'    => __('Tools', 'fluent-community'),
                'route'    => 'tools',
                'icon_svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024" data-v-d2e47025=""><path fill="currentColor" d="M764.416 254.72a351.68 351.68 0 0 1 86.336 149.184H960v192.064H850.752a351.68 351.68 0 0 1-86.336 149.312l54.72 94.72-166.272 96-54.592-94.72a352.64 352.64 0 0 1-172.48 0L371.136 936l-166.272-96 54.72-94.72a351.68 351.68 0 0 1-86.336-149.312H64v-192h109.248a351.68 351.68 0 0 1 86.336-149.312L204.8 160l166.208-96h.192l54.656 94.592a352.64 352.64 0 0 1 172.48 0L652.8 64h.128L819.2 160l-54.72 94.72zM704 499.968a192 192 0 1 0-384 0 192 192 0 0 0 384 0"></path></svg>'
            ]
        ];
    }

    public function showAdminPage()
    {
        $jsTags = ['fluent_community_onboarding'];

        add_filter('script_loader_tag', function ($tag, $handle) use ($jsTags) {
            if (!in_array($handle, $jsTags)) {
                return $tag;
            }
            $tag = str_replace(' src', ' type="module" src', $tag);
            return $tag;
        }, 10, 2);

        wp_enqueue_script('fluent_community_onboarding', Vite::getDynamicSrcUrl('Onboarding/onboarding.js'), ['jquery'], FLUENT_COMMUNITY_PLUGIN_VERSION, [
            'in_footer' => true,
            'strategy'  => 'defer',
            'type'      => 'module'
        ]);

        wp_localize_script('fluent_community_onboarding', 'fluentComAdmin', [
            'ajaxurl'             => admin_url('admin-ajax.php'),
            'rest'                => $this->getRestInfo(),
            'urls'                => [
                'site_url'      => home_url('/'),
                'portal_base'   => Helper::baseUrl('/'),
                'permalink_url' => admin_url('options-permalink.php')
            ],
            'i18n'                => AdminTransStrings::getStrings(),
            'logo'                => Helper::assetUrl('images/logo.png'),
            'is_admin'            => Helper::isSiteAdmin(),
            'is_onboarded'        => !!Utility::getOption('onboarding_sub_settings'),
            'permalink_structure' => get_option('permalink_structure'),
            'is_slug_defined'     => defined('FLUENT_COMMUNITY_PORTAL_SLUG'),
            'has_pro'             => defined('FLUENT_COMMUNITY_PRO_VERSION'),
            'upgrade_url'         => Utility::getProductUrl(false),
            'settings_page_url'   => admin_url('admin.php?page=fluent-community'),
            'is_license_page'     => isset($_GET['license']) && $_GET['license'] === 'yes', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            'license_url_page'    => defined('FLUENT_COMMUNITY_PRO_VERSION') ? admin_url('admin.php?page=fluent-community&license=yes') : '',
        ]);

        echo '<div class="wrap"><div id="fcom_onboarding_app"></div></div>';
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

    protected function getRestInfo()
    {
        $app = fluentCommunityApp();
        $ns = $app->config->get('app.rest_namespace');
        $v = $app->config->get('app.rest_version');

        return [
            'base_url'  => esc_url_raw(rest_url()),
            'url'       => rest_url($ns . '/' . $v),
            'nonce'     => wp_create_nonce('wp_rest'),
            'namespace' => $ns,
            'version'   => $v,
        ];
    }
}
