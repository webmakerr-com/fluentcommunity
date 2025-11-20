<?php

namespace FluentCommunity\App\Hooks\Handlers;

use FluentCommunity\App\App;
use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\Space;
use FluentCommunity\App\Models\Feed;
use FluentCommunity\App\Models\BaseSpace;
use FluentCommunity\App\Models\SpaceGroup;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\App\Services\CustomSanitizer;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\App\Services\ProfileHelper;
use FluentCommunity\App\Services\TransStrings;
use FluentCommunity\App\Vite;
use FluentCommunity\App\Models\NotificationSubscriber;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\App\Services\FeedsHelper;
use FluentCommunity\Modules\Auth\AuthHelper;
use FluentCommunity\Modules\Course\Model\CourseLesson;

class PortalHandler
{
    protected $currentPath = '';

    protected $slug = null;

    public function register()
    {
        /**
         * Register the portal route
         */
        add_action('init', function () {
            $this->slug = Helper::getPortalSlug();
            if ($this->slug !== '') {
                add_rewrite_rule('^' . $this->slug . '/?$', 'index.php?fcom_route=portal_home', 'top'); // For /hooks
                add_rewrite_rule('^' . $this->slug . '/(.+)/?', 'index.php?fcom_route=$matches[1]', 'top');
            }
        });

        add_filter('query_vars', function ($vars) {
            $vars[] = 'fcom_route';
            return $vars;
        });

        add_action('template_redirect', function () {
            $hook_path = get_query_var('fcom_route');

            if ($hook_path) {
                $this->currentPath = $hook_path;
                $this->renderFullApp();
            }

            if ($this->slug === '') {
                global $wp;
                $currentRequest = $wp->request;
                if ($hookPath = Helper::getPortalRequestPath($currentRequest)) {
                    $this->currentPath = $hookPath;
                    $this->renderFullApp();
                }
            }
        }, 1);

        add_action('fluent_community/portal_html', function () {
            App::make('view')->render('portal.portal');
        });

        add_action('fluent_community/portal_header', function ($context) {
            $this->getPortalHeader(true, $context);
        });

        add_action('fluent_community/portal_sidebar', function ($context) {
            $this->getPortalSidebar(true, $context);
        });

        add_action('fluent_community/enqueue_global_assets', function ($useDefaultTheme = true) {
            $isRtl = Helper::isRtl();
            wp_enqueue_style('fluent_community_global', Vite::getDynamicSrcUrl('global.scss', $isRtl), [], FLUENT_COMMUNITY_PLUGIN_VERSION, 'screen');
            if ($useDefaultTheme) {
                wp_enqueue_style('fluent_community_default_theme', Vite::getDynamicSrcUrl('theme-default.scss', $isRtl), [], FLUENT_COMMUNITY_PLUGIN_VERSION, 'screen');
            }
            $css = Utility::getColorCssVariables();
            wp_add_inline_style('fluent_community_global', $css);
            wp_enqueue_script('portal_general', Vite::getStaticSrcUrl('portal_general.js'), [], FLUENT_COMMUNITY_PLUGIN_VERSION, [
                'in_footer' => true
            ]);
            wp_localize_script('portal_general', 'fcom_portal_general', $this->getGlobalScriptVars());
        });

        add_action('admin_bar_menu', function ($wp_admin_bar) {
            if (!\FluentCommunity\App\Services\Helper::canAccessPortal()) {
                return;
            }

            // Add custom link within site name dropdown
            $wp_admin_bar->add_node(array(
                'id'     => 'fluent-community-link',
                'title'  => __('Visit Community Portal', 'fluent-community'),
                'href'   => \FluentCommunity\App\Services\Helper::baseUrl('/'),
                'parent' => 'site-name'
            ));
        }, 99);

        add_action('fluent_community/top_menu_right_items', [$this, 'renderTopMenuRightItems']);

        add_action('fluent_community/after_portal_sidebar', function () {

            if (did_action('fluent_community/after_header_right_menu_items')) {
                echo '<div role="region" aria-label="Portal Settings" class="fcom_side_footer">';
                if (Helper::isSiteAdmin()) {
                    ?>
                    <div style="display: flex;justify-content: space-between;" class="fcom_admin_menu">
                        <?php if (!defined('FLUENT_COMMUNITY_PRO')): ?>
                            <a title="Upgrade to Pro" target="_blank" rel="noopener" class="fcom_inline_icon_link_item"
                               href="<?php echo esc_url(Utility::getProductUrl(true)) ?>">
                                <span class="el-icon">
                                    <svg width="126" height="125" viewBox="0 0 126 125" fill="none"><rect x="0.22139"
                                                                                                          width="125"
                                                                                                          height="125"
                                                                                                          rx="17.8571"
                                                                                                          fill="#4A5FE0"/>
                                        <path
                                            d="M47.9424 75.1832L61.6888 67.2467L69.6253 80.9931C62.0334 85.3763 52.3256 82.7751 47.9424 75.1832Z"
                                            fill="white"/>
                                        <path fill-rule="evenodd" clip-rule="evenodd"
                                              d="M75.4348 59.3101L61.6884 67.2466L69.6249 80.993L83.3713 73.0565L75.4348 59.3101ZM89.1821 51.3734L75.4356 59.3099L83.3721 73.0564L97.1186 65.1199L89.1821 51.3734Z"
                                              fill="white"/>
                                        <path
                                            d="M89.182 51.3736C92.978 49.182 97.8319 50.4826 100.023 54.2786L103.992 61.1518L97.1185 65.12L89.182 51.3736Z"
                                            fill="white"/>
                                        <path
                                            d="M64.593 56.4052L50.8466 64.3417L42.9101 50.5953L56.6565 42.6588L64.593 56.4052Z"
                                            fill="white"/>
                                        <path
                                            d="M78.3397 48.4683L64.5933 56.4048L56.6568 42.6584C64.2487 38.2752 73.9565 40.8764 78.3397 48.4683Z"
                                            fill="white"/>
                                        <path
                                            d="M50.847 64.3418L37.1006 72.2783L29.1641 58.5318L42.9105 50.5953L50.847 64.3418Z"
                                            fill="white"/>
                                        <path
                                            d="M37.1011 72.2783C33.3051 74.4699 28.4512 73.1693 26.2596 69.3733L22.2913 62.5001L29.1646 58.5319L37.1011 72.2783Z"
                                            fill="white"/>
                                    </svg>
                                </span>
                                <span><?php esc_html_e('Upgrade', 'fluent-community'); ?></span>
                            </a>
                        <?php else: ?>
                            <a title="Go to /wp-admin" class="fcom_inline_icon_link_item fcom_wp_admin_link"
                               href="<?php echo esc_url(admin_url()); ?>">
                                <span class="el-icon">
                                    <svg viewBox="0 0 122.52 122.523"><g fill="currentColor">
                                        <path
                                            d="m8.708 61.26c0 20.802 12.089 38.779 29.619 47.298l-25.069-68.686c-2.916 6.536-4.55 13.769-4.55 21.388z"/>
                                        <path
                                            d="m96.74 58.608c0-6.495-2.333-10.993-4.334-14.494-2.664-4.329-5.161-7.995-5.161-12.324 0-4.831 3.664-9.328 8.825-9.328.233 0 .454.029.681.042-9.35-8.566-21.807-13.796-35.489-13.796-18.36 0-34.513 9.42-43.91 23.688 1.233.037 2.395.063 3.382.063 5.497 0 14.006-.667 14.006-.667 2.833-.167 3.167 3.994.337 4.329 0 0-2.847.335-6.015.501l19.138 56.925 11.501-34.493-8.188-22.434c-2.83-.166-5.511-.501-5.511-.501-2.832-.166-2.5-4.496.332-4.329 0 0 8.679.667 13.843.667 5.496 0 14.006-.667 14.006-.667 2.835-.167 3.168 3.994.337 4.329 0 0-2.853.335-6.015.501l18.992 56.494 5.242-17.517c2.272-7.269 4.001-12.49 4.001-16.989z"/>
                                        <path
                                            d="m62.184 65.857-15.768 45.819c4.708 1.384 9.687 2.141 14.846 2.141 6.12 0 11.989-1.058 17.452-2.979-.141-.225-.269-.464-.374-.724z"/>
                                        <path
                                            d="m107.376 36.046c.226 1.674.354 3.471.354 5.404 0 5.333-.996 11.328-3.996 18.824l-16.053 46.413c15.624-9.111 26.133-26.038 26.133-45.426.001-9.137-2.333-17.729-6.438-25.215z"/>
                                        <path
                                            d="m61.262 0c-33.779 0-61.262 27.481-61.262 61.26 0 33.783 27.483 61.263 61.262 61.263 33.778 0 61.265-27.48 61.265-61.263-.001-33.779-27.487-61.26-61.265-61.26zm0 119.715c-32.23 0-58.453-26.223-58.453-58.455 0-32.23 26.222-58.451 58.453-58.451 32.229 0 58.45 26.221 58.45 58.451 0 32.232-26.221 58.455-58.45 58.455z"/></g>
                                    </svg>
                                </span>
                            </a>
                        <?php endif; ?>
                        <a title="Portal Settings" class="fcom_inline_icon_link_item"
                           href="<?php echo esc_url(Helper::baseUrl('admin/settings')); ?>">
                            <span class="el-icon">
                                <svg viewBox="0 0 1024 1024" data-v-d2e47025="">
                                    <path fill="currentColor"
                                          d="M600.704 64a32 32 0 0 1 30.464 22.208l35.2 109.376c14.784 7.232 28.928 15.36 42.432 24.512l112.384-24.192a32 32 0 0 1 34.432 15.36L944.32 364.8a32 32 0 0 1-4.032 37.504l-77.12 85.12a357.12 357.12 0 0 1 0 49.024l77.12 85.248a32 32 0 0 1 4.032 37.504l-88.704 153.6a32 32 0 0 1-34.432 15.296L708.8 803.904c-13.44 9.088-27.648 17.28-42.368 24.512l-35.264 109.376A32 32 0 0 1 600.704 960H423.296a32 32 0 0 1-30.464-22.208L357.696 828.48a351.616 351.616 0 0 1-42.56-24.64l-112.32 24.256a32 32 0 0 1-34.432-15.36L79.68 659.2a32 32 0 0 1 4.032-37.504l77.12-85.248a357.12 357.12 0 0 1 0-48.896l-77.12-85.248A32 32 0 0 1 79.68 364.8l88.704-153.6a32 32 0 0 1 34.432-15.296l112.32 24.256c13.568-9.152 27.776-17.408 42.56-24.64l35.2-109.312A32 32 0 0 1 423.232 64H600.64zm-23.424 64H446.72l-36.352 113.088-24.512 11.968a294.113 294.113 0 0 0-34.816 20.096l-22.656 15.36-116.224-25.088-65.28 113.152 79.68 88.192-1.92 27.136a293.12 293.12 0 0 0 0 40.192l1.92 27.136-79.808 88.192 65.344 113.152 116.224-25.024 22.656 15.296a294.113 294.113 0 0 0 34.816 20.096l24.512 11.968L446.72 896h130.688l36.48-113.152 24.448-11.904a288.282 288.282 0 0 0 34.752-20.096l22.592-15.296 116.288 25.024 65.28-113.152-79.744-88.192 1.92-27.136a293.12 293.12 0 0 0 0-40.256l-1.92-27.136 79.808-88.128-65.344-113.152-116.288 24.96-22.592-15.232a287.616 287.616 0 0 0-34.752-20.096l-24.448-11.904L577.344 128zM512 320a192 192 0 1 1 0 384 192 192 0 0 1 0-384m0 64a128 128 0 1 0 0 256 128 128 0 0 0 0-256"></path>
                                </svg>
                            </span>
                            <span><?php esc_html_e('Settings', 'fluent-community'); ?></span>
                        </a>
                    </div>
                    <?php
                } else if (Utility::isCustomizationEnabled('show_powered_by')) {
                    ?>
                    <a target="_blank" rel="noopener" style="font-size: 80%; cursor: pointer;"
                       class="fcom_inline_icon_link_item" href="<?php echo esc_url(Utility::getProductUrl(true)) ?>">
                        <?php echo esc_html__('Powered by FluentCommunity', 'fluent-community'); ?>
                    </a>
                    <?php
                }
                echo '</div>';
                return;
            }

            if (Helper::isSiteAdmin()) {
                add_action('fluent_community/before_header_menu_items', function () {
                    ?>
                    <li class="top_menu_item fcom_icon_link">
                        <a href="<?php echo esc_url(Helper::baseUrl('admin/settings')); ?>">
                            <span class="el-icon">
                            <svg viewBox="0 0 1024 1024" data-v-d2e47025="">
                                <path fill="currentColor"
                                      d="M600.704 64a32 32 0 0 1 30.464 22.208l35.2 109.376c14.784 7.232 28.928 15.36 42.432 24.512l112.384-24.192a32 32 0 0 1 34.432 15.36L944.32 364.8a32 32 0 0 1-4.032 37.504l-77.12 85.12a357.12 357.12 0 0 1 0 49.024l77.12 85.248a32 32 0 0 1 4.032 37.504l-88.704 153.6a32 32 0 0 1-34.432 15.296L708.8 803.904c-13.44 9.088-27.648 17.28-42.368 24.512l-35.264 109.376A32 32 0 0 1 600.704 960H423.296a32 32 0 0 1-30.464-22.208L357.696 828.48a351.616 351.616 0 0 1-42.56-24.64l-112.32 24.256a32 32 0 0 1-34.432-15.36L79.68 659.2a32 32 0 0 1 4.032-37.504l77.12-85.248a357.12 357.12 0 0 1 0-48.896l-77.12-85.248A32 32 0 0 1 79.68 364.8l88.704-153.6a32 32 0 0 1 34.432-15.296l112.32 24.256c13.568-9.152 27.776-17.408 42.56-24.64l35.2-109.312A32 32 0 0 1 423.232 64H600.64zm-23.424 64H446.72l-36.352 113.088-24.512 11.968a294.113 294.113 0 0 0-34.816 20.096l-22.656 15.36-116.224-25.088-65.28 113.152 79.68 88.192-1.92 27.136a293.12 293.12 0 0 0 0 40.192l1.92 27.136-79.808 88.192 65.344 113.152 116.224-25.024 22.656 15.296a294.113 294.113 0 0 0 34.816 20.096l24.512 11.968L446.72 896h130.688l36.48-113.152 24.448-11.904a288.282 288.282 0 0 0 34.752-20.096l22.592-15.296 116.288 25.024 65.28-113.152-79.744-88.192 1.92-27.136a293.12 293.12 0 0 0 0-40.256l-1.92-27.136 79.808-88.128-65.344-113.152-116.288 24.96-22.592-15.232a287.616 287.616 0 0 0-34.752-20.096l-24.448-11.904L577.344 128zM512 320a192 192 0 1 1 0 384 192 192 0 0 1 0-384m0 64a128 128 0 1 0 0 256 128 128 0 0 0 0-256"></path>
                            </svg>
                        </a>
                    </li>
                    <?php
                });
            }

            // echo '<div class="fcom_side_footer">';
            // $this->renderTopMenuRightItems();
            // echo '</div>';
        });

        add_action('fluent_community/portal_action_reactivate_account', function ($data) {
            $xprofile = Helper::getCurrentProfile();

            if ($xprofile && $xprofile->status == '' && Utility::getPrivacySetting('can_deactive_account') === 'yes') {

                $nonce = Arr::get($data, '__nonce', '');
                if (!wp_verify_nonce($nonce, 'fcom_reactivate_account')) {
                    $this->viewErrorPage('Invalid request', 'Security check failed. Please try again.');
                }

                // we need to reactivate the account
                $xprofile->status = 'active';
                $xprofile->save();
                do_action('fluent_community/reactivate_account', $xprofile);
                delete_user_meta($xprofile->user_id, '_fcom_deactivated_at');
            }

            // redirect to the portal home
            wp_safe_redirect(Helper::baseUrl('/'));
            exit;
        });
    }

    public function renderTopMenuRightItems($context = 'headless')
    {
        $auth_url = $this->getAuthUrl();
        $auth = Helper::getCurrentProfile();
        $has_color_scheme = Helper::hasColorScheme();
        $profileLinks = $this->getProfileLinks($auth);
        ?>
        <ul class="fcom_user_context_menu_items">
            <?php do_action('fluent_community/before_header_right_menu_items', $auth); ?>
            <?php if ($has_color_scheme): ?>
                <li class="top_menu_item fcom_color_mode">
                    <button class="fcom_color_mode_core fcom_menu_button">
                        <span class="fcom_color_mode_action fcom_mode_switch el-icon">
                            <svg class="show_on_light" width="20" height="20" viewBox="0 0 20 20" fill="none"
                                 xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M17.9163 11.7317C16.9166 12.2654 15.7748 12.5681 14.5623 12.5681C10.6239 12.5681 7.43128 9.37543 7.43128 5.43705C7.43128 4.22456 7.73388 3.08274 8.2677 2.08301C4.72272 2.91382 2.08301 6.09562 2.08301 9.89393C2.08301 14.3246 5.67476 17.9163 10.1054 17.9163C13.9038 17.9163 17.0855 15.2767 17.9163 11.7317Z"
                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round"/>
                            </svg>
                            <svg class="show_on_dark" width="20" height="20" viewBox="0 0 20 20" fill="none"
                                 xmlns="http://www.w3.org/2000/svg">
                                <g clip-path="url(#clip0_2906_20675)">
                                    <path
                                        d="M14.1663 10.0007C14.1663 12.3018 12.3009 14.1673 9.99967 14.1673C7.69849 14.1673 5.83301 12.3018 5.83301 10.0007C5.83301 7.69946 7.69849 5.83398 9.99967 5.83398C12.3009 5.83398 14.1663 7.69946 14.1663 10.0007Z"
                                        stroke="currentColor" stroke-width="1.5"/>
                                    <path
                                        d="M9.99984 1.66699V2.91699M9.99984 17.0837V18.3337M15.8922 15.8931L15.0083 15.0092M4.99089 4.99137L4.107 4.10749M18.3332 10.0003H17.0832M2.9165 10.0003H1.6665M15.8926 4.10758L15.0087 4.99147M4.99129 15.0093L4.10741 15.8932"
                                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </g>
                                <defs>
                                    <clipPath id="clip0_2906_20675">
                                        <rect width="20" height="20" fill="currentColor"/>
                                    </clipPath>
                                </defs>
                            </svg>
                        </span>
                    </button>
                </li>
            <?php endif; ?>
            <li class="top_menu_item fcom_search_holder"></li>
            <?php if ($auth): ?>
                <li class="top_menu_item fcom_notification_holder fcom_countable_notification_holder">
                    <?php if ($context != 'headless'): ?>
                        <?php
                        $notificationCount = NotificationSubscriber::unread()
                            ->where('user_id', $auth->user_id)
                            ->count();
                        ?>
                        <a href="<?php echo esc_url(Helper::baseUrl('/notifications')); ?>" type="button"
                           class="fcom_menu_button fcom_theme_button">
                            <i class="el-icon">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                     xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M2.10794 11.9954C1.93073 13.1229 2.72301 13.9055 3.69305 14.2955C7.41201 15.7908 12.5873 15.7908 16.3063 14.2955C17.2763 13.9055 18.0686 13.1229 17.8914 11.9954C17.7825 11.3024 17.244 10.7254 16.845 10.162C16.3224 9.41493 16.2705 8.60009 16.2704 7.73317C16.2704 4.38291 13.4629 1.66699 9.99968 1.66699C6.53645 1.66699 3.72895 4.38291 3.72895 7.73317C3.72887 8.60009 3.67694 9.41493 3.15435 10.162C2.75537 10.7254 2.21685 11.3024 2.10794 11.9954Z"
                                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                        stroke-linejoin="round"></path>
                                    <path
                                        d="M7.5 17.5C8.16345 18.0182 9.03956 18.3333 10 18.3333C10.9604 18.3333 11.8366 18.0182 12.5 17.5"
                                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                        stroke-linejoin="round"></path>
                                </svg>
                            </i>
                            <?php if ($notificationCount): ?>
                                <sup class="el-badge__content fcomc_unread_badge el-badge__content--danger is-fixed"><?php echo $notificationCount > 20 ? '20+' : esc_html($notificationCount); ?></sup>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endif; ?>
            <?php do_action('fluent_community/before_header_menu_items', $auth, $context); ?>
            <?php if ($auth): ?>
                <li class="top_menu_item fcom_menu_item_user">
                    <div class="fcom_user_menu_item">
                        <div class="fcom_profile_extend">
                            <div class="fcom_profile_menu">
                                <div class="user_avatar">
                                    <img alt="User Photo" src="<?php echo esc_url($auth->avatar); ?>"/>
                                    <span class="avatar_icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" height="8" width="8"
                                             viewBox="0 0 1024 1024">
                                            <path fill="currentColor"
                                                  d="M104.704 338.752a64 64 0 0 1 90.496 0l316.8 316.8 316.8-316.8a64 64 0 0 1 90.496 90.496L557.248 791.296a64 64 0 0 1-90.496 0L104.704 429.248a64 64 0 0 1 0-90.496z"></path>
                                        </svg>
                                    </span>
                                </div>
                                <span class="user_name">
                                    <?php echo esc_html($auth->display_name); ?>
                                </span>
                                <ul class="fcom_profile_sub_menu">
                                    <li class="fcom_profile_block">
                                        <a href="<?php echo esc_url(Helper::baseUrl('/u/' . $auth->username)); ?>">
                                            <div class="fcom_profile_avatar">
                                                <img src="<?php echo esc_url($auth->avatar); ?>"
                                                     alt="<?php echo esc_attr($auth->display_name); ?>">
                                            </div>
                                            <div class="fcom_user_info">
                                                <p class="fcom_user_name"><?php echo esc_html($auth->display_name); ?></p>
                                                <p class="fcom_user_email"><?php echo esc_html($auth->user->user_email); ?></p>
                                            </div>
                                        </a>
                                    </li>
                                    <?php foreach ($profileLinks as $link): ?>
                                        <li>
                                            <?php Helper::renderLink($link, 'fcom_menu_link', ''); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                </li>
            <?php else: ?>
                <?php
                if (AuthHelper::isRegistrationEnabled()) {
                    $btnText = __('Login / Signup', 'fluent-community');
                } else {
                    $btnText = __('Login', 'fluent-community');
                }
                ?>

                <li style="margin-right: 5px" class="top_menu_item fcom_login_li">
                    <a class="fcom_login_btn el-button fcom_primary_button" href="<?php echo esc_url($auth_url); ?>">
                        <span><?php echo esc_html($btnText); ?></span>
                    </a>
                </li>
            <?php endif; ?>
            <?php do_action('fluent_community/after_header_right_menu_items', $auth); ?>
        </ul>
        <?php
    }

    public function appVars()
    {
        $userModel = User::where('ID', get_current_user_id())->first();
        $xprofile = null;

        if ($userModel) {
            $xprofile = XProfile::where('user_id', get_current_user_id())->first();
            if (!$xprofile) {
                $xprofile = $userModel->syncXProfile();
            }
        }

        $authData = null;
        $spaceSlugs = [];
        $spaceGroups = [];

        if ($userModel && $xprofile) {
            $wpUser = get_user_by('ID', $userModel->ID);

            if ($userModel->isCommunityModerator()) {
                $userSpaces = Space::orderBy('serial', 'ASC')->get();
                $spaceGroups = SpaceGroup::orderBy('serial', 'ASC')
                    ->select(['id', 'title', 'slug', 'settings'])
                    ->get();
            } else {
                $userSpaces = Space::orderBy('serial', 'ASC')
                    ->whereHas('members', function ($q) {
                        $q->where('user_id', get_current_user_id())
                            ->where('status', 'active');
                    })
                    ->get();

                $userSpaceParentIds = $userSpaces->pluck('parent_id')->filter()->unique()->toArray();

                $spaceGroups = SpaceGroup::orderBy('serial', 'ASC')
                    ->select(['id', 'title', 'slug', 'settings'])
                    ->whereIn('id', $userSpaceParentIds)
                    ->get();
            }

            $userSpaces->each(function ($space) use ($userModel) {
                $space = $space->formatSpaceData($userModel);
                do_action_ref_array('fluent_community/space', [&$space]);
            });

            $userSpaces = $userSpaces->keyBy('slug');

            $userModel->cacheAccessSpaces();

            $spaceSlugs = $userSpaces->pluck('slug')->toArray();

            $authData = [
                'id'                => $xprofile->user_id,
                'user_id'           => $xprofile->user_id,
                'username'          => $xprofile->username,
                'display_name'      => $xprofile->display_name,
                'first_name'        => $wpUser->first_name ? $wpUser->first_name : $xprofile->getFirstName(),
                'avatar'            => $xprofile->avatar,
                'total_points'      => $xprofile->total_points,
                'last_activity'     => $xprofile->last_activity,
                'email'             => $userModel->user_email,
                'spaces'            => $userSpaces,
                'community_roles'   => $userModel->getCommunityRoles(),
                'is_verified'       => $xprofile->is_verified,
                'compilation_score' => $xprofile->getCompletionScore(),
                'meta'              => $xprofile->meta
            ];
        } else {
            $authData = [
                'id'                => 0,
                'user_id'           => 0,
                'spaces'            => (object)[],
                'community_roles'   => [],
                'compilation_score' => 100
            ];
        }

        $settings = Helper::generalSettings();

        $onboardSettings = Helper::getOnboardingSettings();

        global $wp_version;

        $isRtl = Helper::isRtl();

        $isAbsoluteUrl = defined('FLUENT_COMMUNITY_PORTAL_SLUG') && FLUENT_COMMUNITY_PORTAL_SLUG == '';

        $portalVars = apply_filters('fluent_community/portal_vars', [
            'portal_notices'             => apply_filters('fluent_community/portal_notices', []),
            'i18n'                       => TransStrings::getStrings(),
            'auth'                       => $authData,
            'ajaxurl'                    => admin_url('admin-ajax.php'),
            'ajax_nonce'                 => wp_create_nonce('fluent_community_ajax_nonce'),
            'slug'                       => 'fluent-community',
            'rest'                       => $this->getRestInfo(),
            'user_id'                    => $userModel ? $userModel->ID : null,
            'assets_url'                 => FLUENT_COMMUNITY_PLUGIN_URL . 'assets/',
            'permissions'                => $userModel ? $userModel->getPermissions() : ['read' => true],
            'logo'                       => Arr::get($settings, 'logo'),
            'site_title'                 => Arr::get($settings, 'site_title'),
            'access_level'               => Arr::get($settings, 'access.acess_level'),
            'user_membership_slugs'      => $spaceSlugs,
            'block_editor_assets'        => [
                'scripts' => [
                    'react'                 => Vite::getStaticSrcUrl('libs/isolated-editor/react.production.min.js'),
                    'react-dom'             => Vite::getStaticSrcUrl(
                        'libs/isolated-editor/react-dom.production.min.js'
                    ),
                    'isolated-block-editor' => Vite::getStaticSrcUrl('libs/isolated-editor/isolated-block-editor.min.js')
                ],
                'styles'  => [
                    $isRtl ? Vite::getStaticSrcUrl('libs/isolated-editor/core.rtl.css') : Vite::getStaticSrcUrl('libs/isolated-editor/core.css'),
                    $isRtl ? Vite::getStaticSrcUrl('libs/isolated-editor/isolated-block-editor.rtl.css') : Vite::getStaticSrcUrl('libs/isolated-editor/isolated-block-editor.css'),
                    includes_url('css/dist/block-editor/content.min.css?ver=' . $wp_version),
                ]
            ],
            'features'                   => [
                'disable_global_posts'   => Arr::get($settings, 'disable_global_posts', '') == 'yes',
                'has_survey_poll'        => true,
                'is_onboarding_enabled'  => Arr::get($onboardSettings, 'is_onboarding_enabled', 'no') == 'yes',
                'can_switch_layout'      => true,
                'mention_mail'           => Utility::hasEmailAnnouncementEnabled(),
                'max_media_per_post'     => apply_filters('fluent_community/max_media_per_post', Utility::getCustomizationSetting('max_media_per_post')),
                'has_post_title'         => Utility::postTitlePref(),
                'has_course'             => Helper::isFeatureEnabled('course_module'),
                'followers_module'       => Helper::isFeatureEnabled('followers_module'),
                'skicky_sidebar'         => Utility::isCustomizationEnabled('fixed_sidebar'),
                'post_layout'            => Utility::getCustomizationSetting('rich_post_layout'),
                'member_list_layout'     => Utility::getCustomizationSetting('member_list_layout'),
                'default_feed_layout'    => Utility::getCustomizationSetting('default_feed_layout'),
                'disable_feed_sort_by'   => Utility::getCustomizationSetting('disable_feed_sort_by'),
                'default_feed_sort_by'   => Utility::getCustomizationSetting('default_feed_sort_by'),
                'video_embeder'          => apply_filters('fluent_community/has_video_embeder', true),
                'has_topics'             => !!Utility::getTopics(),
                'show_post_modal'        => Utility::isCustomizationEnabled('show_post_modal'),
                'has_analytics'          => Utility::hasAnalyticsEnabled(),
                'can_deactivate_account' => Utility::getPrivacySetting('can_deactive_account') === 'yes',
            ],
            'route_classes'              => array_filter([
                'fcom_sticky_header'           => Utility::isCustomizationEnabled('fixed_page_header'),
                'fcom_sticky_sidebar'          => Utility::isCustomizationEnabled('fixed_sidebar'),
                'fcom_has_icon_on_header_menu' => Utility::isCustomizationEnabled('icon_on_header_menu')
            ]),
            'urls'                       => [
                'site_url'      => home_url(),
                'portal_base'   => Helper::baseUrl('/'),
                'global_search' => Helper::baseUrl(),
            ],
            'last_feed_id'               => FeedsHelper::getLastFeedId(),
            'unread_notification_count'  => $userModel ? $userModel->getUnreadNotificationCount() : 0,
            'unread_feed_ids'            => $userModel ? $userModel->getUnreadNotificationFeedIds() : [],
            'date_offset'                => time() - current_time('timestamp'),
            'date_formatter'             => Helper::getDateFormatter(true),
            'time_formatter'             => Helper::getTimeFormatter(true),
            'date_time_formatter'        => Helper::getDateFormatter(true) . ' ' . Helper::getTimeFormatter(true),
            'portal_slug'                => Helper::getPortalSlug(true),
            'socialLinkProviders'        => ProfileHelper::socialLinkProviders(true),
            'space_groups'               => $spaceGroups,
            'mobileMenuItems'            => Helper::getMobileMenuItems(),
            'feed_links'                 => Helper::getEnabledFeedLinks(),
            'post_order_by_options'      => Helper::getPostOrderOptions('feed'),
            'user_post_order_by_options' => Helper::getPostOrderOptions('user'),
            'routing_system'             => Helper::getPortalRouteType(),
            'portal_url'                 => Helper::baseUrl('/'),
            'upgrade_url'                => 'https://fluentcommunity.co/discount-deal/?utm_source=wp&utm_medium=upgrade&utm_campaign=upgrade',
            'dateTime18n'                => apply_filters('fluent_community/date_time_i18n', [
                /* translators: weekday. Please keep the serial and format */
                'weekdays'           => __('Sunday_Monday_Tuesday_Wednesday_Thursday_Friday_Saturday', 'fluent-community'),
                /* translators: Months Please keep the serial and format*/
                'months'             => __('January_February_March_April_May_June_July_August_September_October_November_December', 'fluent-community'),
                /* translators: weekday short Please keep the serial and format*/
                'weekdaysShort'      => __('Sun_Mon_Tue_Wed_Thu_Fri_Sat', 'fluent-community'),
                /* translators: Months short Please keep the serial and format*/
                'monthsShort'        => __('Jan_Feb_Mar_Apr_May_Jun_Jul_Aug_Sep_Oct_Nov_Dec', 'fluent-community'),
                /* translators: weekday min Please keep the serial and format*/
                'weekdaysMin'        => __('Su_Mo_Tu_We_Th_Fr_Sa', 'fluent-community'),
                'relativeTime'       => [
                    /* translators: Relative Date Formats. Please do not alter %s*/
                    'future' => __('in %s', 'fluent-community'),
                    /* translators: Relative Date Formats. Please do not alter %s*/
                    'past'   => __('%s ago', 'fluent-community'),
                    /* translators: Relative Date Formats.*/
                    's'      => __('a few seconds', 'fluent-community'),
                    /* translators: Relative Date Formats.*/
                    'm'      => __('a minute', 'fluent-community'),
                    /* translators: Relative Date Formats. Don't alter %d*/
                    'mm'     => __('%d minutes', 'fluent-community'),
                    /* translators: Relative Date Formats*/
                    'h'      => __('an hour', 'fluent-community'),
                    /* translators: Relative Date Formats. Don't alter %d*/
                    'hh'     => __('%d hours', 'fluent-community'),
                    /* translators: Relative Date Formats*/
                    'd'      => __('a day', 'fluent-community'),
                    /* translators: Relative Date Formats. Don't alter %d*/
                    'dd'     => __('%d days', 'fluent-community'),
                    /* translators: Relative Date Formats*/
                    'M'      => __('a month', 'fluent-community'),
                    /* translators: Relative Date Formats. Don't alter %d*/
                    'MM'     => __('%d months', 'fluent-community'),
                    /* translators: Relative Date Formats*/
                    'y'      => __('a year', 'fluent-community'),
                    /* translators: Relative Date Formats. Don't alter %d*/
                    'yy'     => __('%d years', 'fluent-community')
                ],
                'relativeTimeMobile' => [
                    /* translators: Relative Date Formats. Please do not alter %s*/
                    'future' => __('in %s', 'fluent-community'),
                    /* translators: Relative Date Formats. Please do not alter %s*/
                    'past'   => __('%s ago', 'fluent-community'),
                    /* translators: Relative Date Formats.*/
                    's'      => __('few sec', 'fluent-community'),
                    /* translators: Relative Date Formats.*/
                    'm'      => __('1min', 'fluent-community'),
                    /* translators: Relative Date Formats. Don't alter %d*/
                    'mm'     => __('%dmin', 'fluent-community'),
                    /* translators: Relative Date Formats*/
                    'h'      => __('1h', 'fluent-community'),
                    /* translators: Relative Date Formats. Don't alter %d*/
                    'hh'     => __('%dh', 'fluent-community'),
                    /* translators: Relative Date Formats*/
                    'd'      => __('1d', 'fluent-community'),
                    /* translators: Relative Date Formats. Don't alter %d*/
                    'dd'     => __('%dd', 'fluent-community'),
                    /* translators: Relative Date Formats*/
                    'M'      => __('1mo', 'fluent-community'),
                    /* translators: Relative Date Formats. Don't alter %d*/
                    'MM'     => __('%dmo', 'fluent-community'),
                    /* translators: Relative Date Formats*/
                    'y'      => __('1y', 'fluent-community'),
                    /* translators: Relative Date Formats. Don't alter %d*/
                    'yy'     => __('%dy', 'fluent-community')
                ]
            ]),
            'topicsConfig'               => Helper::getTopicsConfig(),
            'moderationConfig'           => Helper::getModerationConfig(),
            'is_absolute_url'            => $isAbsoluteUrl,
            'portal_paths'               => $isAbsoluteUrl ? Helper::portalRoutePaths() : [],
            'suggestedColors'            => Utility::getSuggestedColors(),
            'view_leaderboard_members'   => Utility::canViewLeaderboardMembers(),
            'report_reasons'             => Helper::getReportReasons(),
            'el_i18n'                    => [
                'pagination' => [
                    /* translators: %s is replaced by the page number */
                    'currentPage'        => \sprintf(__('page %s', 'fluent-community'), '{pager}'),
                    'deprecationWarning' => 'Deprecated usages detected',
                    'goto'               => __('Go to', 'fluent-community'),
                    'next'               => __('Go to next page', 'fluent-community'),
                    /* translators: %s is replaced by the number of pages */
                    'nextPages'          => \sprintf(__('Next %s pages', 'fluent-community'), ' {pager}'),
                    'page'               => __('Page', 'fluent-community'),
                    'pageClassifier'     => '',
                    'pagesize'           => __('/page', 'fluent-community'),
                    'prev'               => __('Go to previous page', 'fluent-community'),
                    /* translators: %s is replaced by the number of pages */
                    'prevPages'          => \sprintf(__('Previous %s pages', 'fluent-community'), '{pager}'),
                    /* translators: %s is replaced by the total number of items */
                    'total'              => \sprintf(__('Total %s', 'fluent-community'), '{total}'),
                ],
                'table'      => [
                    'clearFilter'   => __('All', 'fluent-community'),
                    'confirmFilter' => __('Confirm', 'fluent-community'),
                    'emptyText'     => __('No Data', 'fluent-community'),
                    'resetFilter'   => __('Reset', 'fluent-community'),
                    'sumText'       => __('Sum', 'fluent-community'),
                ],
                'image'      => [
                    'error' => __('Failed to Load', 'fluent-community'),
                ],
                'upload'     => [
                    'continue'  => __('Continue', 'fluent-community'),
                    'delete'    => __('Delete', 'fluent-community'),
                    'deleteTip' => __('press delete to remove', 'fluent-community'),
                    'preview'   => __('Preview', 'fluent-community'),
                ],
                'select'     => [
                    'loading'     => __('Loading', 'fluent-community'),
                    'noData'      => __('No data', 'fluent-community'),
                    'noMatch'     => __('No matching data', 'fluent-community'),
                    'placeholder' => __('Select', 'fluent-community'),
                ],
                'datepicker' => [
                    'now'              => __('Now', 'fluent-community'),
                    'today'            => __('Today', 'fluent-community'),
                    'cancel'           => __('Cancel', 'fluent-community'),
                    'clear'            => __('Clear', 'fluent-community'),
                    'confirm'          => __('OK', 'fluent-community'),
                    'dateTablePrompt'  => __('Use the arrow keys and enter to select the day of the month', 'fluent-community'),
                    'monthTablePrompt' => __('Use the arrow keys and enter to select the month', 'fluent-community'),
                    'yearTablePrompt'  => __('Use the arrow keys and enter to select the year', 'fluent-community'),
                    'selectedDate'     => __('Selected date', 'fluent-community'),
                    'selectDate'       => __('Select date', 'fluent-community'),
                    'selectTime'       => __('Select time', 'fluent-community'),
                    'startDate'        => __('Start Date', 'fluent-community'),
                    'startTime'        => __('Start Time', 'fluent-community'),
                    'endDate'          => __('End Date', 'fluent-community'),
                    'endTime'          => __('End Time', 'fluent-community'),
                    'prevYear'         => __('Previous Year', 'fluent-community'),
                    'nextYear'         => __('Next Year', 'fluent-community'),
                    'prevMonth'        => __('Previous Month', 'fluent-community'),
                    'nextMonth'        => __('Next Month', 'fluent-community'),
                    'year'             => __('year', 'fluent-community'),
                    'month1'           => __('January', 'fluent-community'),
                    'month2'           => __('February', 'fluent-community'),
                    'month3'           => __('March', 'fluent-community'),
                    'month4'           => __('April', 'fluent-community'),
                    'month5'           => __('May', 'fluent-community'),
                    'month6'           => __('June', 'fluent-community'),
                    'month7'           => __('July', 'fluent-community'),
                    'month8'           => __('August', 'fluent-community'),
                    'month9'           => __('September', 'fluent-community'),
                    'month10'          => __('October', 'fluent-community'),
                    'month11'          => __('November', 'fluent-community'),
                    'month12'          => __('December', 'fluent-community'),
                    'week'             => __('week', 'fluent-community'),
                    'weeks'            => [
                        'sun' => __('Sun', 'fluent-community'),
                        'mon' => __('Mon', 'fluent-community'),
                        'tue' => __('Tue', 'fluent-community'),
                        'wed' => __('Wed', 'fluent-community'),
                        'thu' => __('Thu', 'fluent-community'),
                        'fri' => __('Fri', 'fluent-community'),
                        'sat' => __('Sat', 'fluent-community'),
                    ],
                    'weeksFull'        => [
                        'sun' => __('Sunday', 'fluent-community'),
                        'mon' => __('Monday', 'fluent-community'),
                        'tue' => __('Tuesday', 'fluent-community'),
                        'wed' => __('Wednesday', 'fluent-community'),
                        'thu' => __('Thursday', 'fluent-community'),
                        'fri' => __('Friday', 'fluent-community'),
                        'sat' => __('Saturday', 'fluent-community'),
                    ],
                    'months'           => [
                        'jan' => __('Jan', 'fluent-community'),
                        'feb' => __('Feb', 'fluent-community'),
                        'mar' => __('Mar', 'fluent-community'),
                        'apr' => __('Apr', 'fluent-community'),
                        'may' => __('May', 'fluent-community'),
                        'jun' => __('Jun', 'fluent-community'),
                        'jul' => __('Jul', 'fluent-community'),
                        'aug' => __('Aug', 'fluent-community'),
                        'sep' => __('Sep', 'fluent-community'),
                        'oct' => __('Oct', 'fluent-community'),
                        'nov' => __('Nov', 'fluent-community'),
                        'dec' => __('Dec', 'fluent-community'),
                    ],
                ]
            ],
            'course_sections_collapsed'  => apply_filters('fluent_community/course_section_collapse_default', 'no'),
            'course_lesson_fullscreen'   => apply_filters('fluent_community/course_lesson_fullscreen_default', 'no'),
            'default_profile_tab'        => apply_filters('fluent_community/default_profile_tab_route', ''),
            'wp_lesson_editor_frame'     => site_url('?fluent_community_block_editor=1'),
            'lazy_styles'                => [
                'wp-block-library-css'           => includes_url('css/dist/block-library/style.min.css?version=' . $wp_version),
                'fcom-block-content-styling-css' => FLUENT_COMMUNITY_PLUGIN_URL . 'Modules/Gutenberg/editor/content_styling.css?version=' . FLUENT_COMMUNITY_PLUGIN_VERSION
            ]
        ]);

        $portalVars['welcome_banner'] = Helper::getWelcomeBanner($userModel ? 'login' : 'logout');

        if (!$xprofile) {
            $portalVars['auth_url'] = $this->getAuthUrl();
            $portalVars['allow_signup'] = !!AuthHelper::isRegistrationEnabled();
        }

        return $portalVars;
    }

    public function getGlobalScriptVars($scope = 'wp')
    {
        return apply_filters('fluent_community/general_portal_vars', [
            'scope'                    => $scope,
            'theme'                    => get_option('template'),
            'default_color'            => 'light',
            'color_switch_cookie_name' => '',
            'has_color_scheme'         => Helper::hasColorScheme(),
        ]);
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

    protected function renderFullApp()
    {
        do_action('litespeed_control_set_nocache', 'fluentcommunity api request'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        // set no cache headers
        nocache_headers();

        if (isset($_REQUEST['fcom_action'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $action = sanitize_text_field(wp_unslash($_REQUEST['fcom_action'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            do_action('fluent_community/portal_action_' . $action, $_GET); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        $pathParts = explode('/', $this->currentPath);
        do_action('fluent_community/rendering_path_ssr_' . $pathParts[0], $pathParts);

        $userId = get_current_user_id();

        if (!$userId && !Helper::isPublicAccessible()) {
            $url = home_url(add_query_arg($_REQUEST, $GLOBALS['wp']->request)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $settings = Helper::generalSettings();
            $authUrl = Arr::get($settings, 'auth_url', '');
            if ($authUrl) {
                $authUrl = add_query_arg([
                    'redirect_to' => $url
                ], $authUrl);
            } else {
                $authUrl = $this->getAuthUrl();
            }

            do_action('fluent_community/portal/not_logged_in', $authUrl);
            wp_safe_redirect($authUrl);
            exit();
        }

        do_action('fluent_community/portal/viewed');
        $xprofile = Helper::getCurrentProfile();

        if ($xprofile && $xprofile->status != 'active') {

            if ($xprofile->status == 'pending') {
                $this->viewErrorPage(__('You request is on pending', 'fluent-community'), __('An admin need to approve your join request. Please contact with an admin to get approval', 'fluent-community'));
            } else if ($xprofile->status == '') { // it's a self deactiavted account
                $this->viewErrorPage(__('Your account is deactivated', 'fluent-community'), __('You have deactivated your community profile.', 'fluent-community'), true, [
                    'btn_url'  => Helper::baseUrl('?fcom_action=reactivate_account&__nonce=' . wp_create_nonce('fcom_reactivate_account')),
                    'btn_text' => __('Activate my account', 'fluent-community')
                ]);
            } else {
                $this->viewErrorPage(__('Access denied', 'fluent-community'), __('Sorry, You can not access to this community', 'fluent-community'));
            }
        }

        if ($xprofile) {
            if (!Helper::canAccessPortal()) {
                $generalSettings = Helper::generalSettings();
                $this->viewErrorPage(__('Access Denied', 'fluent-community'), Arr::get($generalSettings, 'restricted_role_content'));
            }
            if ($xprofile->user) {
                $xprofile->user->cacheAccessSpaces();
            }

            /*
             * Just in case if username is not sanitized
             */
            if ($xprofile->username != CustomSanitizer::sanitizeUserName($xprofile->username)) {
                $xprofile->username = ProfileHelper::generateUserName($xprofile->user_id);
                $xprofile->save();
            }

            do_action('fluent_community/portal_render_for_user', $xprofile);
        }

        $data = $this->getAppData();
        $data = $this->maybePushDynamicMetaData($data);

        $data['isHeadless'] = Helper::isHeadless();

        if (!$data['isHeadless']) {
            $this->loadClassicPortalAssets($data);
        } else {
            do_action('fluent_community/rendering_headless_portal', $data);
        }

        do_action('fluent_community/before_portal_rendered', $data);

        status_header(200);
        App::make('view')->render('portal_page', $data);
        exit();
    }

    public function loadClassicPortalAssets($data)
    {
        $cssFiles = $data['css_files'];

        unset($cssFiles['fcom_theme_default']);

        foreach ($cssFiles as $fileName => $cssFile) {
            wp_enqueue_style($fileName, $cssFile['url'], [], FLUENT_COMMUNITY_PLUGIN_VERSION, 'screen');
        }

        $jsFiles = $data['js_files'];
        $jsTags = array_keys($jsFiles);
        add_filter('script_loader_tag', function ($tag, $handle) use ($jsTags) {
            if (!in_array($handle, $jsTags)) {
                return $tag;
            }
            $tag = str_replace(' src', ' type="module" src', $tag);
            return $tag;
        }, 10, 2);
        foreach ($jsFiles as $fileName => $jsFile) {
            wp_enqueue_script($fileName, $jsFile['url'], $jsFile['deps'], FLUENT_COMMUNITY_PLUGIN_VERSION, [
                'in_footer' => true,
                'strategy'  => 'defer',
                'type'      => 'module'
            ]);
        }

        foreach ($data['js_vars'] as $varKey => $values) {
            wp_localize_script('fcom_strat', $varKey, $values);
        }
    }

    public function getAppData()
    {
        $isRtl = Helper::isRtl();

        $userId = get_current_user_id();
        $url = home_url(add_query_arg($_REQUEST, $GLOBALS['wp']->request)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $appVars = $this->appVars();
        $generalSettings = Helper::generalSettings();

        $dataVars = [
            'title'           => Arr::get($generalSettings, 'site_title'),
            'description'     => get_bloginfo('description'),
            'featured_image'  => Arr::get($generalSettings, 'featured_image', ''),
            'header_js_files' => [],
            'js_files'        => [
                'fcom_general' => [
                    'url'  => Vite::getStaticSrcUrl('portal_general.js'),
                    'deps' => []
                ],
                'fcom_strat'   => [
                    'url'  => Vite::getStaticSrcUrl('start.js'),
                    'deps' => []
                ],
                'fcom_app'     => [
                    'url'  => Vite::getStaticSrcUrl('app.js'),
                    'deps' => []
                ]
            ],
            'js_vars'         => [
                'fluentComAdmin'      => $appVars,
                'fcom_portal_general' => $this->getGlobalScriptVars('portal'),
            ],
            'css_files'       => array_filter([
                'fcom_theme_default' => [
                    'url' => Vite::getDynamicSrcUrl('theme-default.scss', $isRtl)
                ],
                'fcom_global'        => [
                    'url' => Vite::getDynamicSrcUrl('global.scss', $isRtl)
                ]
            ]),
            'url'             => $url,
            'current_route'   => $this->currentPath,
            'contact'         => $appVars['auth'],
            'user'            => $userId ? get_user_by('ID', $userId) : null,
            'route_group'     => Helper::getRouteNameByRequestPath($this->currentPath)
        ];

        if (!Utility::isDev()) {
            if ($isRtl) {
                $fileName = 'app.rtl.css';
            } else {
                $fileName = 'app.css';
            }

            $dataVars['css_files']['fcom_vendor'] = [
                'url' => Vite::getStaticSrcUrl($fileName)
            ];
        } else {
            $dataVars['css_files']['fcom_vendor'] = [
                'url' => Vite::getDynamicSrcUrl('app_css.scss')
            ];
        }

        return apply_filters('fluent_community/portal_data_vars', $dataVars);
    }

    protected function maybePushDynamicMetaData($data)
    {
        $dynamicRoute = Arr::get($data, 'route_group');

        $data['landing_route'] = $dynamicRoute;

        $validGroups = [
            'user_profile',
            'community_view',
            'course_view',
            'feed_view',
            'lesson_view'
        ];

        if (!in_array($dynamicRoute, $validGroups)) {
            return $data;
        }

        if ($dynamicRoute == 'user_profile') {
            $uriParts = explode('/', $this->currentPath);
            if (count($uriParts) >= 2) {
                $userName = $uriParts[1];
                $xProfile = XProfile::where('username', $userName)->first();
                if ($xProfile) {
                    $data['title'] = 'User ' . esc_html($xProfile->display_name) . ' - ' . $data['title'];
                    if ($xProfile->short_description) {
                        $data['description'] = Helper::getHumanExcerpt($xProfile->short_description, 100);
                    }
                    if ($coverPhoto = Arr::get($xProfile->meta, 'cover_photo')) {
                        $data['featured_image'] = $coverPhoto;
                    }
                }
            }
            return $data;
        }

        if ($dynamicRoute == 'community_view' || $dynamicRoute == 'course_view') {
            $uriParts = explode('/', $this->currentPath);
            if (count($uriParts) >= 2) {
                $paceSlug = $uriParts[1];
                $space = BaseSpace::query()->onlyMain()->where('slug', $paceSlug)->first();
                if ($space && $space->privacy != 'secret') {

                    if ($dynamicRoute == 'course_view') {
                        /* translators: %s is replaced by the title of the space */
                        $data['title'] = sprintf(__('Enroll %s', 'fluent-community'), esc_html($space->title) . ' - ' . $data['title']);
                    } else {
                        /* translators: %s is replaced by the title of the space */
                        $data['title'] = sprintf(__('Join %s', 'fluent-community'), esc_html($space->title) . ' - ' . $data['title']);
                    }

                    if ($space->description) {
                        $data['description'] = Helper::getHumanExcerpt($space->description, 100);
                    }
                    if ($ogImage = Arr::get($space->settings, 'og_image')) {
                        $data['featured_image'] = $ogImage;
                    } else if ($space->cover_photo) {
                        $data['featured_image'] = $space->cover_photo;
                    }

                    if ($dynamicRoute == 'course_view') {
                        $data['json_ld'] = apply_filters('fluent_community/course_view_json_ld', [], $space, $data);
                    }
                }
            }
            return $data;
        }

        if ($dynamicRoute == 'feed_view') {
            $uriParts = explode('/', $this->currentPath);
            if (count($uriParts) >= 2) {
                $postSlug = end($uriParts);
                $feed = Feed::query()->withoutGlobalScopes()->where('slug', $postSlug)
                    ->with([
                        'xprofile' => function ($q) {
                            $q->select(ProfileHelper::getXProfilePublicFields());
                        },
                        'space'
                    ])
                    ->byUserAccess(get_current_user_id())
                    ->first();

                if (!$feed) {
                    return $data;
                }

                if ($feed->title) {
                    $data['title'] = esc_html($feed->title) . ' - ' . $data['title'];
                } else {
                    $data['title'] = esc_html($feed->xprofile ? $feed->xprofile->display_name : 'Someone') . ' posted at ' . $data['title'];
                }

                $feedUrl = $feed->getPermalink();
                $data['canonical_url'] = $feedUrl;

                if ($feed->message) {
                    $data['description'] = esc_html(Helper::getHumanExcerpt($feed->message, 160));
                }

                if ($mediaPreview = Arr::get($feed->meta, 'media_preview.image')) {
                    $data['featured_image'] = esc_url($mediaPreview);
                } else if ($feed->space) {
                    $space = $feed->space;
                    if ($ogImage = Arr::get($space->settings, 'og_image')) {
                        $data['featured_image'] = $ogImage;
                    } else if ($space->cover_photo) {
                        $data['featured_image'] = $space->cover_photo;
                    }
                }

                $data['json_ld'] = apply_filters('fluent_community/feed_view_json_ld', [], $feed, $data);
            }
            return $data;
        }

        if ($dynamicRoute == 'lesson_view') {
            $uriParts = explode('/', $this->currentPath);

            $lessonSlug = Arr::get($uriParts, 3);
            if (!$lessonSlug) {
                return $data;
            }

            $lesson = CourseLesson::query()->where('slug', $lessonSlug)->first();

            if (!$lesson) {
                return $data;
            }

            $data['title'] = esc_html($lesson->title) . ' - ' . $data['title'];
            $data['description'] = esc_html(Helper::getHumanExcerpt($lesson->message, 120));

            return $data;
        }

        return $data;
    }

    protected function getMainMenuItems($scope = 'sidebar')
    {
        $menuGroups = Helper::getMenuItemsGroup('view');

        $items = Arr::get($menuGroups, 'mainMenuItems', []);

        return apply_filters('fluent_community/main_menu_items', $items, $scope);
    }

    public function getPortalHeader($echo = false, $context = 'headless')
    {
        $settings = Helper::generalSettings();
        $userId = get_current_user_id();

        $xprofile = null;
        if ($userId) {
            $userModel = Helper::getCurrentUser();
            if ($userModel) {
                $xprofile = $userModel->syncXProfile(false);
            }
        }

        $authUrl = $this->getAuthUrl();

        if ($xprofile) {
            $xprofile->logout_url = wp_logout_url(Helper::baseUrl());
        }

        $logo = Arr::get($settings, 'logo', '');
        $whiteLogo = Arr::get($settings, 'white_logo', '');
        if (!$whiteLogo) {
            $whiteLogo = $logo;
        }

        $logoPermalink = Helper::baseUrl('/');
        $logoPermalinkType = Arr::get($settings, 'logo_permalink_type');
        if ($logoPermalinkType == 'custom') {
            $logoPermalink = Arr::get($settings, 'logo_permalink');
        }

        $data = apply_filters('fluent_community/header_vars', [
            'portal_url'     => Helper::baseUrl('/'),
            'logo'           => $logo,
            'white_logo'     => $whiteLogo,
            'logo_permalink' => $logoPermalink,
            'site_title'     => Arr::get($settings, 'site_title'),
            'profile_url'    => $xprofile ? Helper::baseUrl('u/' . $xprofile->username . '/') : '',
            'auth'           => $xprofile ? $xprofile : null,
            'auth_url'       => $authUrl,
            'menuItems'      => $this->getMainMenuItems('header'),
            'context'        => $context
        ]);

        if ($echo) {
            App::make('view')->render('portal.header', $data);
            return;
        }

        return (string)App::make('view')->make('portal.header', $data);
    }

    private function getProfileLinks($xprofile = null)
    {
        if (!$xprofile) {
            $xprofile = Helper::getCurrentProfile();
        }

        $profileLinks = [];
        $menuGroups = Helper::getMenuItemsGroup('view');
        if ($xprofile) {
            $profileLinks = Arr::get($menuGroups, 'profileDropdownItems', []);

            $replaces = [
                '#{{user_url}}'   => Helper::baseUrl('u/' . $xprofile->username),
                '#user_url'       => Helper::baseUrl('u/' . $xprofile->username),
                '#{{logout_url}}' => wp_logout_url(Helper::baseUrl()),
                '#logout_url'     => wp_logout_url(Helper::baseUrl())
            ];

            foreach ($profileLinks as &$link) {
                $slug = Arr::get($link, 'slug', '');

                if ($slug == 'my_spaces') {
                    $link['permalink'] = Helper::baseUrl('u/' . $xprofile->username . '/spaces');
                } else if ($slug == 'logout') {
                    $link['permalink'] = wp_logout_url(Helper::baseUrl());
                } else if ($slug == 'profile') {
                    $link['permalink'] = Helper::baseUrl('u/' . $xprofile->username);
                }

                $link['permalink'] = str_replace(array_keys($replaces), array_values($replaces), $link['permalink']);
            }
        }

        return $profileLinks;
    }

    public function getPortalSidebar($echo = false, $context = 'headless')
    {
        $data = Utility::getPortalSidebarData('sidebar');

        if ($echo) {
            App::make('view')->render('portal.main_sidebar', $data);
            return;
        }

        return (string)App::make('view')->make('portal.main_sidebar', $data);
    }

    protected function getAuthUrl()
    {
        return apply_filters('fluent_community/auth/login_url', Helper::getAuthUrl());
    }

    public function viewErrorPage($title, $message = '', $showBtn = true, $btnData = [])
    {
        $data = [
            'title'       => $title,
            'description' => $message,
            'btn_txt'     => Arr::get($btnData, 'btn_text', __('Go to home page', 'fluent-community')),
            'url'         => Arr::get($btnData, 'btn_url', home_url())
        ];

        if (!$showBtn) {
            $data['btn_txt'] = '';
        }

        status_header(200);
        App::make('view')->render('error_page', $data);
        exit();
    }
}
