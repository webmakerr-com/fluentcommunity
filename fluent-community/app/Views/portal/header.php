<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly ?>

<?php
/**
 *
 * @var string $portal_url
 * @var string $logo
 * @var string $white_logo
 * @var string $logo_permalink
 * @var string $site_title
 * @var string $profile_url
 * @var array | null $auth
 * @var string $auth_url
 * @var array $menuItems
 * @var array $profileLinks
 * @var bool $has_color_scheme
 * @var string $context
 **/
?>
<div class="fcom_top_menu">
    <div class="top_menu_left">
        <div class="space_opener">
            <button aria-label="Open Menu" class="fcom_space_opener_btn" style="background-color: transparent; cursor: pointer; border: 0 solid transparent;color: #5d5e61;" aria-disabled="false" type="button">
                <span>
                    <i class="el-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" color="currentColor" fill="none">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M3 4.5C3 3.94772 3.44772 3.5 4 3.5L20 3.5C20.5523 3.5 21 3.94772 21 4.5C21 5.05229 20.5523 5.5 20 5.5L4 5.5C3.44772 5.5 3 5.05228 3 4.5Z" fill="currentColor"/>
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M3 14.5C3 13.9477 3.44772 13.5 4 13.5L20 13.5C20.5523 13.5 21 13.9477 21 14.5C21 15.0523 20.5523 15.5 20 15.5L4 15.5C3.44772 15.5 3 15.0523 3 14.5Z" fill="currentColor"/>
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M3 9.5C3 8.94772 3.44772 8.5 4 8.5L20 8.5C20.5523 8.5 21 8.94772 21 9.5C21 10.0523 20.5523 10.5 20 10.5L4 10.5C3.44772 10.5 3 10.0523 3 9.5Z" fill="currentColor"/>
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M3 19.5C3 18.9477 3.44772 18.5 4 18.5L20 18.5C20.5523 18.5 21 18.9477 21 19.5C21 20.0523 20.5523 20.5 20 20.5L4 20.5C3.44772 20.5 3 20.0523 3 19.5Z"fill="currentColor"/>
                        </svg>                  
                    </i>
                </span>
            </button>
        </div>
        <div id="fcom_before_logo"></div>
        <?php do_action('fluent_community/before_header_logo', $auth); ?>
        <div class="fhr_logo">
            <a class="fcom_route" href="<?php echo esc_url($logo_permalink); ?>">
                <?php if ($logo): ?>
                    <img class="show_on_light" src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($site_title); ?>"/>
                    <img class="show_on_dark" src="<?php echo esc_url($white_logo); ?>" alt="<?php echo esc_attr($site_title); ?>"/>
                <?php else: ?>
                    <span><?php echo esc_html($site_title); ?></span>
                <?php endif; ?>
            </a>
        </div>
        <?php do_action('fluent_community/after_header_logo', $auth); ?>
    </div>
    <div class="top_menu_center fcom_desktop_only fcom_general_menu">
        <?php if ($menuItems): ?>
            <nav>
                <ul aria-label="Main menu" class="fcom_header_menu top_menu_items">
                    <?php \FluentCommunity\App\Services\Helper::renderMenuItems($menuItems, 'fcom_menu_link'); ?>
                </ul>
            </nav>
        <?php endif; ?>
        <?php do_action('fluent_community/after_header_menu', $context); ?>
    </div>
    <div class="top_menu_right">
        <?php  do_action('fluent_community/top_menu_right_items', $context); ?>
    </div>
</div>
