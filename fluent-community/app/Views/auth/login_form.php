<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly ?>
<div id="fcom_user_onboard_wrap" class="fcom_user_onboard">
    <?php do_action('fluent_community/before_auth_form_header', 'login'); ?>
    <div class="fcom_onboard_header">
        <div class="fcom_onboard_header_title">
            <h2>
                <?php echo esc_html($settings['title']); ?>
            </h2>
            <p>
                <?php echo wp_kses_post($settings['description']); ?>
            </p>
        </div>
    </div>
    <div class="fcom_onboard_body">
        <div class="fcom_onboard_form">

            <?php
            \FluentCommunity\Modules\Auth\AuthHelper::nativeLoginForm([
                'echo'    => true,
                'form_id' => 'fcom_user_login_form',
                'value_remember' => true,
                'redirect' => !empty($redirect_to) ? $redirect_to : '',
                'value_username' => $defaults ? \FluentCommunity\Framework\Support\Arr::get($defaults, 'email') : '',
                'label_log_in' => $settings['button_label'],
            ], $hiddenFields);
            ?>

            <div class="fcom_spaced_divider">
                <?php if (!empty($signupUrl)): ?>
                    <div class="fcom_alt_auth_text">
                        <?php esc_html_e('Don\'t have an account?', 'fluent-community'); ?>
                        <a href="<?php echo esc_url($signupUrl); ?>">
                            <?php esc_html_e('Signup', 'fluent-community'); ?>
                        </a>
                    </div>
                <?php endif; ?>
                <p class="fcom_reset_pass_text">
                    <a href="<?php echo esc_url(wp_lostpassword_url($redirect)); ?>">
                        <?php esc_html_e('Lost your password?', 'fluent-community'); ?>
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>
