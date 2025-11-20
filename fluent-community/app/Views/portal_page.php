<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly
/**
 * @var string $title
 * @var string $description
 * @var string $url
 * @var string $featured_image
 * @var string $landing_route
 * @var bool $isHeadless
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <title><?php echo esc_attr($title); ?></title>
    <meta charset='utf-8'>
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=2.0,user-scalable=yes,viewport-fit=cover"/>
    <meta name="mobile-web-app-capable" content="yes">
    <?php if (!$isHeadless) : wp_head(); else: ?>
        <meta name="description" content="<?php echo esc_attr($description); ?>">
        <link rel="icon" type="image/x-icon" href="<?php echo esc_url(get_site_icon_url()); ?>"/>
        <link rel="apple-touch-icon" href="<?php echo esc_url(get_site_icon_url()); ?>"/>
        <meta property="og:type" content="website">
        <meta property="og:url" content="<?php echo esc_url($url); ?>">
        <meta property="og:site_name" content="<?php bloginfo('name'); ?>">
        <meta property="og:description" content="<?php echo esc_attr($description); ?>">
        <?php if ($featured_image): ?>
            <meta property="og:image" content="<?php echo esc_url($featured_image); ?>">
            <meta name="twitter:image" content="<?php echo esc_url($featured_image); ?>">
            <meta name="twitter:card" content="summary_large_image">
        <?php endif; ?>
        <?php do_action('fluent_community/portal_head_meta', $landing_route); ?>

        <?php if(!empty($canonical_url)): ?>
        <link rel="canonical" href="<?php echo esc_url($canonical_url); ?>">
        <?php endif; ?>

        <?php if(!empty($json_ld)): ?><script type="application/ld+json"><?php echo json_encode($json_ld); ?></script>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (\FluentCommunity\App\Services\Helper::isRtl()): ?>
        <style>
            body {
                direction: rtl;
            }
        </style>
    <?php endif; ?>

    <?php do_action('fluent_community/portal_head'); ?>

    <style id="fcom_css_vars">
        <?php echo esc_html(\FluentCommunity\App\Functions\Utility::getColorCssVariables()); ?>
        .dark body .el-dialog {
            --el-dialog-bg-color: #2B2E33;
        }
    </style>
</head>
<body>
<div class="fcom_wrap">
    <?php do_action('fluent_community/before_portal_dom'); ?>
    <div class="fluent_com">
        <?php do_action('fluent_community/portal_html'); ?>
    </div>
</div>
<?php do_action('fluent_community/before_js_loaded'); ?>
<?php do_action('fluent_community/portal_footer'); ?>
<?php !$isHeadless && wp_footer(); ?>
</body>
</html>
