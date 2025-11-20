<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * @var string $title
 * @var string $description
 * @var string $url
 * @var string $btn_txt
 */
?>
<!DOCTYPE html>
<html style="background: #f0f0f1;" <?php language_attributes(); ?>>
<head>
    <title><?php echo esc_attr($title); ?></title>
    <meta charset='utf-8'>
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=0,viewport-fit=cover"/>
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="description" content="<?php echo esc_attr($description); ?>">
    <link rel="icon" type="image/x-icon" href="<?php echo esc_url(get_site_icon_url()); ?>"/>
</head>
<body style='font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"'>
<div style="display: block; width: 100%;" class="fluent_com">
    <div style="max-width: 600px;margin: 150px auto;border: 1px solid #e3e8ee;border-radius: 10px;" class="fcom_error_wrapper">
        <div style="padding: 15px 20px;background: #f0f2f5;border-top-left-radius: 10px;border-top-right-radius: 10px;border-bottom: 1px solid #e3e8ee;" class="fcom_error_title">
            <h3 style="margin: 0"><?php echo wp_kses_post($title); ?></h3>
        </div>
        <div style="padding: 20px 20px 40px;background: white;border-bottom-left-radius: 10px;border-bottom-right-radius: 10px;" class="fcom_error_body">
            <p><?php echo wp_kses_post($description); ?></p>

            <?php if($btn_txt): ?>
                <div style="text-align: center;margin-top: 40px;" class="btn_wrapper">
                    <a style=" background: black;color: white;padding: 10px 20px;border-radius: 5px;text-decoration: none;display: inline-block;" href="<?php echo esc_url($url); ?>" class="fcom_btn fcom_btn_primary">
                        <?php echo wp_kses_post($btn_txt); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
