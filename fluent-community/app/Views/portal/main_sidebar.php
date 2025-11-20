<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly ?>
<?php
/**
 * @var $primaryItems array
 * @var $settingsItems array
 * @var $spaceGroups array
 * @var $topInlineLinks array
 * @var $bottomLinkGroups array
 * @var $is_admin bool
 * @var $has_color_scheme bool
 * @var $context string
 */

use FluentCommunity\App\Services\Helper;

$fluentCommunityShowFeedLink = \FluentCommunity\App\Functions\Utility::isCustomizationEnabled('feed_link_on_sidebar');

?>

<div id="fcom_sidebar_wrap" class="fcom_sidebar_wrap">
    <?php if (apply_filters('fluent_community/will_render_default_sidebar_items', true)) : ?>
        <nav aria-label="Main Sidebar Home menu">
            <ul class="fcom_sm_only fcom_general_menu fcom_home_link">
                <?php Helper::renderMenuItems($primaryItems, 'fcom_menu_link', '<span class="fcom_no_avatar"></span>', true); ?>
            </ul>
        </nav>
        <?php if($fluentCommunityShowFeedLink || $topInlineLinks): ?>
        <nav aria-label="Main Sidebar Mobile Menu">
            <ul class="fcom_general_menu">
                <?php if ($fluentCommunityShowFeedLink): ?>
                    <li class="fcom_menu_item_all_feeds fcom_desktop_only">
                        <a class="fcom_menu_link fcom_dashboard route_url"
                           href="<?php echo esc_url(Helper::baseUrl('/')); ?>">
                            <div class="community_avatar">
                            <span class="fcom_shape">
                                <i class="el-icon">
                                    <svg width="20" height="18" viewBox="0 0 20 18" fill="none">
                                        <path fill-rule="evenodd" d="M10 13.166H10.0075H10Z" fill="currentColor"/>
                                        <path d="M10 13.166H10.0075" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M16.6666 6.08301V10.2497C16.6666 13.3924 16.6666 14.9637 15.6903 15.94C14.714 16.9163 13.1426 16.9163 9.99992 16.9163C6.85722 16.9163 5.28587 16.9163 4.30956 15.94C3.33325 14.9637 3.33325 13.3924 3.33325 10.2497V6.08301" stroke="currentColor" stroke-width="1.5"/>
                                        <path d="M18.3333 7.74967L14.714 4.27925C12.4918 2.14842 11.3807 1.08301 9.99996 1.08301C8.61925 1.08301 7.50814 2.14842 5.28592 4.27924L1.66663 7.74967" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                    </svg>
                                </i>
                            </span>
                            </div>
                            <span class="community_name"><?php esc_html_e('Feed', 'fluent-community'); ?></span>
                        </a>
                    </li>
                <?php endif; ?>
                <?php Helper::renderMenuItems($topInlineLinks, 'fcom_menu_link fcom_custom_link', '<span class="fcom_no_avatar"></span>', true); ?>
            </ul>
        </nav>
        <?php endif; ?>

        <div class="fcom_sidebar_contents">
            <?php foreach ($spaceGroups as $fluentCommunitySpaceGroup): ?>
                <?php if($fluentCommunitySpaceGroup['children']): ?>
                <div class="fcom_communities_menu">
                    <div class="fcom_space_group_header fcom_group_title">
                        <h4 data-group_id="<?php echo (int)$fluentCommunitySpaceGroup['id']; ?>" class="space_section_title">
                            <span><?php echo esc_html($fluentCommunitySpaceGroup['title']); ?></span>
                            <i class="el-icon fcom_space_down">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024"><path fill="currentColor" d="M831.872 340.864 512 652.672 192.128 340.864a30.592 30.592 0 0 0-42.752 0 29.12 29.12 0 0 0 0 41.6L489.664 714.24a32 32 0 0 0 44.672 0l340.288-331.712a29.12 29.12 0 0 0 0-41.728 30.592 30.592 0 0 0-42.752 0z"></path></svg>
                            </i>
                        </h4>
                        <?php if ($is_admin): ?>
                            <div class="fcom_space_create">
                                <a class="fcom_space_create_link" data-parent_id="<?php echo (int)$fluentCommunitySpaceGroup['id']; ?>" href="<?php echo esc_url(Helper::baseUrl('discover/spaces/?create_space=yes&parent_id=' . $fluentCommunitySpaceGroup['id'])); ?>">+</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <nav aria-label="Group Menu for <?php echo esc_html($fluentCommunitySpaceGroup['title']); ?>">
                        <ul>
                            <?php foreach ($fluentCommunitySpaceGroup['children'] as $fluentCommunityLink): ?>
                                <li class="space_menu_item">
                                    <?php Helper::renderLink($fluentCommunityLink, 'fcom_menu_link'); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($bottomLinkGroups): ?>
                <?php foreach ($bottomLinkGroups as $fluentCommunityBottomLink): ?>
                    <div class="fcom_communities_menu">
                        <div class="fcom_space_group_header fcom_group_title">
                            <h4 role="region" aria-label="Link Groups" data-group_id="<?php echo esc_attr($fluentCommunityBottomLink['slug']); ?>"
                                class="space_section_title">
                                <span><?php echo esc_html($fluentCommunityBottomLink['title']); ?></span>
                                <i class="el-icon fcom_space_down">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024"><path fill="currentColor" d="M831.872 340.864 512 652.672 192.128 340.864a30.592 30.592 0 0 0-42.752 0 29.12 29.12 0 0 0 0 41.6L489.664 714.24a32 32 0 0 0 44.672 0l340.288-331.712a29.12 29.12 0 0 0 0-41.728 30.592 30.592 0 0 0-42.752 0z"></path></svg>
                                </i>
                            </h4>
                            <?php if ($is_admin): ?>
                                <div class="fcom_space_create">
                                    <a href="<?php echo esc_url(Helper::baseUrl('admin/settings/menu-settings')); ?>">
                                        +
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <nav aria-label="Group Menu for <?php echo esc_html($fluentCommunityBottomLink['title']); ?>">
                        <ul>
                            <?php foreach ($fluentCommunityBottomLink['items'] as $fluentCommunityButtomLink): ?>
                                <li class="space_menu_item">
                                    <?php Helper::renderLink($fluentCommunityButtomLink, 'fcom_menu_link space_menu_item route_url fcom_custom_link', '🔗'); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        </nav>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>


            <?php if ($settingsItems): ?>
                <div style="margin-top: 20px;">
                    <h4 class="space_section_title"><?php esc_html_e('# Manage', 'fluent-community'); ?></h4>
                    <ul style="margin-top: 20px;">
                        <?php Helper::renderSettingsMenuItems($settingsItems); ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>


<?php if ($context != 'ajax'): ?>
    <?php do_action('fluent_community/after_sidebar_wrap', $context); ?>
    <div id="fcom_menu_sidebar"></div>
    <?php do_action('fluent_community/after_portal_sidebar', $context); ?>
<?php endif; ?>
