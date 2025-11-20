<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<?php
/**
 * @var $invitee_name string
 * @var $by_name string
 * @var $by_email string
 * @var $site_title string
 * @var $access_url string
 */
?>
<div style="background-color: #ffffff; padding: 20px; border-radius: 8px;">
    <?php /* translators: %s is replaced by the name of the user who is invited */ ?>
    <p><?php echo esc_html(sprintf(__('Hey %s,', 'fluent-community'), $invitee_name)); ?></p>
    <p><?php
        /* translators: %1$s is replaced by the name of the user who invited the user, %2$s is replaced by the title of the site */
        echo wp_kses_post(sprintf(__('%1$s has invited you to join in %2$s.', 'fluent-community'),
            '<strong>'.$by_name.'</strong>',
            '<strong>'.$site_title.'</strong>'
        ));
        ?>
    </p>

    <p><?php esc_html_e('Click the link below to accept the invitation:', 'fluent-community') ?></p>

    <a href="<?php echo esc_url($access_url); ?>" style="background-color: #000000; color: #ffffff; padding: 10px 20px; text-align: center; border-radius: 5px; display: inline-block; text-decoration: none;">
        <?php esc_html_e('Accept invitation', 'fluent-community'); ?>
    </a>
</div>
