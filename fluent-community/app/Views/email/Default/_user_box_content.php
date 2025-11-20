<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<table width="100%" style="margin-bottom: 30px;" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td>
            <table cellspacing="0" cellpadding="0" border="0">
                <tr>
                    <td valign="top" style="border-radius: 50%; padding: 4px; vertical-align: top; height: 32px; width: 32px;">
                        <a href="<?php echo esc_url($permalink); ?>">
                            <img alt="" src="<?php echo esc_url($user_avatar); ?>" height="32" width="32" style="border-radius: 50%; height: 32px; width: 32px; display: block;">
                        </a>
                    </td>
                    <td style="font-family: Arial, sans-serif; font-size: 16px;color: #333; padding-left: 5px; vertical-align: middle;">
                        <span style="font-weight: bold;"><?php echo esc_html($user_name); ?></span>
                        <span><?php esc_html_e('commented on:', 'fluent-community'); ?></span>
                        <a target="_blank" href="<?php echo esc_url($permalink); ?>">
                            <?php echo wp_kses_post($post_content); ?>
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="font-family: Arial, sans-serif; font-size: 16px; line-height: 1.4; color: #333;">
            <table style="background-color: #f7f7f7; margin: 10px 0" bgcolor="#f7f7f7" cellspacing="0" cellpadding="0" border="0"
                   width="100%">
                <tr>
                    <td style="padding: 7px 20px;">
                        <?php \FluentCommunity\App\Services\CustomSanitizer::sanitizeRichText($content, true); ?>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
