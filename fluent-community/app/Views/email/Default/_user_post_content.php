<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * @var string $permalink
 * @var string $user_avatar
 * @var string $user_name
 * @var string $community_name
 * @var string $content
 */

?>
<table width="100%" cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td style="font-family: Arial, sans-serif; font-size: 16px; line-height: 1.4; color: #333;">
            <table style="margin-bottom: 20px;" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                    <td>
                        <table style="margin-bottom: 10px;" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td valign="top" style="border-radius: 50%; padding: 4px; vertical-align: top; height: 32px; width: 32px;">
                                    <a href="<?php echo esc_url($permalink); ?>">
                                        <img alt="" src="<?php echo esc_url($user_avatar); ?>" height="32" width="32" style="border-radius: 50%; height: 32px; width: 32px; display: block;">
                                    </a>
                                </td>
                                <td style="font-family: Arial, sans-serif; font-size: 16px;color: #333; padding: 0 5px; vertical-align: middle;">
                                    <a style="text-decoration: none; color: #333;" href="<?php echo esc_url($permalink); ?>">
                                        <span style="font-weight: bold;"><?php echo esc_html($user_name); ?></span>
                                    </a>
                                    <?php if($space_name): ?>
                                        <?php /* translators: %s is replaced by the title of the space */ ?>
                                        <span style="font-family: Arial, sans-serif; font-size: 12px; font-weight: normal; margin: 0;"><?php echo esc_html(sprintf(__('in %s', 'fluent-community'), $space_name)); ?></span>
                                    <?php endif; ?>

                                    <?php if(!empty($timestamp)): ?>
                                        <?php /* translators: %s is replaced by the time ago */ ?>
                                        <p style="font-family: Arial, sans-serif; font-size: 12px; font-weight: normal; margin: 0; margin-bottom: 0px;"><?php echo esc_html(sprintf(__('%s ago', 'fluent-community'), $timestamp)); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 0; line-height: 1.4;font-family: Helvetica, sans-serif;">
                        <?php if($title): ?>
                        <a href="<?php echo esc_url($permalink); ?>" style="color: #1f3349; display: block; overflow: hidden; margin: 0; line-height: 1.2; padding: 0; text-decoration: none;">
                            <h2 style="color: #1f3349; display: block; font-size: 20px; overflow: hidden; margin: 0 0 10px 0; padding: 0; text-decoration: none;"><?php echo esc_html($title); ?></h2>
                        </a>
                        <?php endif; ?>
                        <?php \FluentCommunity\App\Services\CustomSanitizer::sanitizeRichText($content, true); ?>
                        <?php if(!empty($show_read_more)): ?>
                        <a href="<?php echo esc_url($permalink); ?>" style="color: #1f3349;text-decoration: none;font-weight: bold;font-size: 14px;">
                            <?php echo esc_html__('...read more', 'fluent-community'); ?>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
