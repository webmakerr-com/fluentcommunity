<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>

<div id="fcom_user_onboard_wrap" class="fcom_user_onboard">
    <?php do_action('fluent_community/before_auth_form_header', 'signup'); ?>
    <div class="fcom_onboard_header">
        <div class="fcom_onboard_header_title">
            <h2>
                <?php echo esc_html($title); ?>
            </h2>
            <p><?php echo wp_kses_post($description); ?></p>
        </div>
    </div>
    <div class="fcom_onboard_body">
        <div class="fcom_onboard_form">

            <?php do_action('fluent_community/before_registration_form'); ?>

            <form method="post" id="fcom_user_registration_form">
                <div class="fcom_form_main_fields">
                    <?php (new \FluentCommunity\App\Services\FormBuilder($formFields))->render(); ?>
                    <?php
                        foreach ($hiddenFields as $fluentCommunityName => $fluentCommunityValue) {
                            echo "<input type='hidden' name='".esc_attr($fluentCommunityName)."' value='".esc_attr($fluentCommunityValue)."'>";
                        }
                    ?>
                    <div class="fcom_form-group">
                        <div class="fcom_form_input">
                            <button type="submit" class="fcom_btn fcom_btn_primary has_svg_loader fcom_btn_submit">
                                <svg version="1.1" class="fls_loading_svg" x="0px" y="0px" width="40px" height="20px" viewBox="0 0 50 50" style="enable-background:new 0 0 50 50;" xml:space="preserve">
                                        <path fill="currentColor" d="M43.935,25.145c0-10.318-8.364-18.683-18.683-18.683c-10.318,0-18.683,8.365-18.683,18.683h4.068c0-8.071,6.543-14.615,14.615-14.615c8.072,0,14.615,6.543,14.615,14.615H43.935z">
                                            <animateTransform attributeType="xml" attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="0.6s" repeatCount="indefinite"/>
                                        </path>
                                    </svg>
                                <span>
                                    <?php echo esc_html($button_label); ?>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <?php if(!empty($loginUrl)): ?>
            <div class="fcom_spaced_divider">
                <?php esc_html_e('Already have an account?', 'fluent-community'); ?>
                <a href="<?php echo esc_url($loginUrl); ?>">
                    <?php esc_html_e('Login', 'fluent-community'); ?>
                </a>
            </div>
            <?php endif; ?>

            <?php do_action('fluent_community/after_registration_form'); ?>
        </div>
    </div>
</div>
