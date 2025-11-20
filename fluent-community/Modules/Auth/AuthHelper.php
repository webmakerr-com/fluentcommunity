<?php

namespace FluentCommunity\Modules\Auth;

use FluentCommunity\App\App;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\App\Services\Libs\Mailer;
use FluentCommunity\Framework\Support\Arr;

class AuthHelper
{
    public static function registerNewUser($user_login, $user_email, $user_pass = '', $extraData = [])
    {
        $errors = new \WP_Error();

        $sanitized_user_login = sanitize_user($user_login);

        $user_email = apply_filters('user_registration_email', $user_email); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        // Check the username.
        if ('' === $sanitized_user_login) {
            $errors->add('empty_username', __('<strong>Error</strong>: Please enter a username.', 'fluent-community'));
        } elseif (!validate_username($user_login)) {
            $errors->add('invalid_username', __('<strong>Error</strong>: This username is invalid because it uses illegal characters. Please enter a valid username.', 'fluent-community'));
            $sanitized_user_login = '';
        } elseif (username_exists($sanitized_user_login)) {
            $errors->add('username_exists', __('<strong>Error</strong>: This username is already registered. Please choose another one.', 'fluent-community'));
        } else {
            /** This filter is documented in wp-includes/user.php */
            $illegal_user_logins = (array)apply_filters('illegal_user_logins', array()); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            if (in_array(strtolower($sanitized_user_login), array_map('strtolower', $illegal_user_logins), true)) {
                $errors->add('invalid_username', __('<strong>Error</strong>: Sorry, that username is not allowed.', 'fluent-community'));
            }
        }

        // Check the email address.
        if ('' === $user_email) {
            $errors->add('empty_email', __('<strong>Error</strong>: Please type your email address.', 'fluent-community'));
        } elseif (!is_email($user_email)) {
            $errors->add('invalid_email', __('<strong>Error</strong>: The email address is not correct.', 'fluent-community'));
            $user_email = '';
        } elseif (email_exists($user_email)) {
            $errors->add(
                'email_exists',
                __('<strong>Error:</strong> This email address is already registered. Please login or try resetting your password.', 'fluent-community')
            );
        }

        do_action('register_post', $sanitized_user_login, $user_email, $errors); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        if ($errors->has_errors()) {
            return $errors;
        }

        if (!$user_pass) {
            $user_pass = wp_generate_password(8, false);
        }

        $data = [
            'user_login' => wp_slash($sanitized_user_login),
            'user_email' => wp_slash($user_email),
            'user_pass'  => $user_pass
        ];

        if (!empty($extraData['first_name'])) {
            $data['first_name'] = sanitize_text_field($extraData['first_name']);
        }

        if (!empty($extraData['last_name'])) {
            $data['last_name'] = sanitize_text_field($extraData['last_name']);
        }

        if (!empty($extraData['full_name']) && empty($extraData['first_name']) && empty($extraData['last_name'])) {
            $extraData['full_name'] = sanitize_text_field($extraData['full_name']);
            // extract the names
            $fullNameArray = explode(' ', $extraData['full_name']);
            $data['first_name'] = array_shift($fullNameArray);
            if ($fullNameArray) {
                $data['last_name'] = implode(' ', $fullNameArray);
            } else {
                $data['last_name'] = '';
            }
        }

        if (!empty($extraData['description'])) {
            $data['description'] = sanitize_textarea_field($extraData['description']);
        }

        if (!empty($extraData['user_url']) && filter_var($extraData['user_url'], FILTER_VALIDATE_URL)) {
            $data['user_url'] = sanitize_url($extraData['user_url']);
        }

        if (!empty($extraData['role'])) {
            $data['role'] = $extraData['role'];
        }

        $user_id = wp_insert_user($data);

        if (!$user_id || is_wp_error($user_id)) {
            $errors->add('registerfail', __('<strong>Error</strong>: Could not register you. Please contact the site admin!', 'fluent-community')
            );
            return $errors;
        }

        if (!empty($_COOKIE['wp_lang'])) {
            $wp_lang = sanitize_text_field(wp_unslash($_COOKIE['wp_lang']));
            if (in_array($wp_lang, get_available_languages(), true)) {
                update_user_meta($user_id, 'locale', $wp_lang); // Set user locale if defined on registration.
            }
        }

        do_action('register_new_user', $user_id); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        return $user_id;
    }

    public static function makeLogin($user)
    {
        wp_clear_auth_cookie();
        wp_set_current_user($user->ID, $user->user_login);
        wp_set_auth_cookie($user->ID, true, is_ssl());

        $user = get_user_by('ID', $user->ID);

        if ($user) {
            do_action('wp_login', $user->user_login, $user); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        }

        return $user;
    }

    public static function isFluentAuthAvailable()
    {
        if (defined('FLUENT_AUTH_VERSION') && FLUENT_AUTH_VERSION) {
            return (new \FluentAuth\App\Hooks\Handlers\CustomAuthHandler())->isEnabled();
        }

        return false;
    }

    public static function getTermsText()
    {
        $policyUrl = apply_filters('fluent_community/terms_policy_url', get_privacy_policy_url());

        $termsText = __('I agree to the terms and conditions', 'fluent-community');
        if ($policyUrl) {
            /* translators: %1$s is replaced by the text "terms and conditions", %2$s is replaced by the text "to the terms and conditions" */
            $termsText = sprintf(__('I agree to the %1$s terms and conditions %2$s', 'fluent-community'), '<a rel="noopener" href="' . esc_url($policyUrl) . '" target="_blank">', '</a>');
        }

        return $termsText;
    }

    public static function getFormFields($invitation = null)
    {
        $fields = apply_filters('fluent_community/auth/signup_fields', [
            'full_name'     => [
                'label'             => __('Full name', 'fluent-community'),
                'placeholder'       => __('Your first & last name', 'fluent-community'),
                'type'              => 'text',
                'required'          => true,
                'value'             => $invitation ? Arr::get($invitation->meta, 'invitee_name') : '',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'email'         => [
                'type'              => 'email',
                'placeholder'       => __('Your email address', 'fluent-community'),
                'label'             => __('Email Address', 'fluent-community'),
                'required'          => true,
                'value'             => $invitation ? $invitation->message : '',
                'readonly'          => $invitation && $invitation->message,
                'sanitize_callback' => 'sanitize_email'
            ],
            'username'      => [
                'type'              => 'text',
                'placeholder'       => __('No space or special characters', 'fluent-community'),
                'label'             => __('Username', 'fluent-community'),
                'required'          => true,
                'sanitize_callback' => 'sanitize_user'
            ],
            'password'      => [
                'type'              => 'password',
                'placeholder'       => __('Password', 'fluent-community'),
                'label'             => __('Account Password', 'fluent-community'),
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'conf_password' => [
                'type'              => 'password',
                'placeholder'       => __('Password Confirmation', 'fluent-community'),
                'label'             => __('Re-type Account Password', 'fluent-community'),
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'terms'         => [
                'type'         => 'inline_checkbox',
                'inline_label' => self::getTermsText(),
                'required'     => true
            ]
        ], $invitation);

        if (!self::isPasswordConfRequired()) {
            unset($fields['conf_password']);
        }

        return $fields;
    }

    public static function getLostPasswordUrl($redirectUrl = '')
    {
        if (self::isFluentAuthAvailable()) {
            $url = add_query_arg([
                'form' => 'reset_password'
            ], Helper::getAuthUrl());
        } else {
            $url = wp_lostpassword_url($redirectUrl);;
        }

        return apply_filters('fluent_community/auth/lost_password_url', $url);
    }

    public static function getLoginFormFields()
    {
        return apply_filters('fluent_community/auth/login_fields', [
            'username' => [
                'type'              => 'text',
                'placeholder'       => __('Your account email address', 'fluent-community'),
                'label'             => __('Email Address', 'fluent-community'),
                'required'          => true,
                'sanitize_callback' => 'sanitize_user'
            ],
            'password' => [
                'type'              => 'password',
                'placeholder'       => __('Your account password', 'fluent-community'),
                'label'             => __('Password', 'fluent-community'),
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ]);
    }

    public static function isPasswordConfRequired()
    {
        return apply_filters('fluent_community/autg/password_confirmation', true);
    }

    public static function isRegistrationEnabled()
    {

        $enabled = !!get_option('users_can_register');

        if (!$enabled) {
            $generalSettinsg = Helper::generalSettings();
            $enabled = $generalSettinsg['explicit_registration'] !== 'no';
        }

        return apply_filters('fluent_community/auth/registration_enabled', $enabled);
    }

    public static function isTwoFactorEnabled()
    {
        return apply_filters('fluent_auth/verify_signup_email', true);
    }

    public static function get2FaRegistrationCodeForm($formData)
    {
        $generalSettings = Helper::generalSettings();
        try {
            $verifcationCode = str_pad(random_int(100123, 900987), 6, 0, STR_PAD_LEFT);
        } catch (\Exception $e) {
            $verifcationCode = str_pad(wp_rand(100123, 900987), 6, 0, STR_PAD_LEFT);
        }

        // Hash the code
        $codeHash = wp_hash_password($verifcationCode);

        // Create a token with the email and code hash
        $data = [
            'email'     => $formData['email'],
            'code_hash' => $codeHash,
            'expires'   => time() + 600 // 10 minutes expiry
        ];
        $token = base64_encode(json_encode($data));

        // Sign the token
        $signature = hash_hmac('sha256', $token, SECURE_AUTH_KEY);
        $signedToken = $token . '.' . $signature;

        /* translators: %s is replaced by the title of the site */
        $mailSubject = apply_filters("fluent_community/auth/signup_verification_mail_subject", sprintf(__('Your registration verification code for %s', 'fluent-community'), Arr::get($generalSettings, 'site_title')));

        $pStart = '<p style="font-family: Arial, sans-serif; font-size: 16px; font-weight: normal; margin: 0; margin-bottom: 16px;">';

        /* translators: %s is replaced by the name of the user */
        $message = $pStart . sprintf(__('Hello %s,', 'fluent-community'), Arr::get($formData, 'first_name')) . '</p>' .
            $pStart . __('Thank you for registering with us! To complete the setup of your account, please enter the verification code below on the registration page.', 'fluent-community') . '</p>' .
            /* translators: %s is replaced by the verification code */
            $pStart . '<b>' . sprintf(__('Verification Code: %s', 'fluent-community'), $verifcationCode) . '</b></p>' .
            '<br />' .
            $pStart . __('This code is valid for 10 minutes and is meant to ensure the security of your account. If you did not initiate this request, please ignore this email.', 'fluent-community') . '</p>';

        $message = apply_filters('fluent_community/auth/signup_verification_email_body', $message, $verifcationCode, $formData);

        $generalSettings = Helper::generalSettings();
        $message = (string)App::make('view')->make('email.template', [
            'logo'        => [
                'url' => $generalSettings['logo'],
                'alt' => $generalSettings['site_title']
            ],
            'bodyContent' => $message,
            'pre_header'  => __('Activate your account', 'fluent-community'),
            'footerLines' => [
                __('If you did not initiate this request, please ignore this email.', 'fluent-community'),
                /* translators: %1$s is replaced by the title of the site, %2$s is replaced by the home URL */
                sprintf(__('This email has been sent from %1$s. Site: %2$s', 'fluent-community'), Arr::get($generalSettings, 'site_title'), home_url())
            ]
        ]);

        $mailer = new Mailer($formData['email'], $mailSubject, $message);

        if ($formData['first_name']) {
            $toName = trim(Arr::get($formData, 'first_name') . ' ' . Arr::get($formData, 'last_name'));
            $mailer = $mailer->to($formData['email'], $toName);
        }

        $mailer->send();

        ob_start();
        ?>
        <div class="fls_signup_verification">
            <input type="hidden" name="__two_fa_signed_token" value="<?php echo esc_attr($signedToken); ?>"/>
            <?php /* translators: %s is replaced by the email address */ ?>
            <p><?php echo esc_html(\sprintf(__('A verification code has been sent to %s. Please provide the code below: ', 'fluent-community'), $formData['email'])) ?></p>
            <div class="fcom_form-group fcom_field_verification">
                <div class="fcom_form_label">
                    <label for="fcom_field_verification"><?php esc_html_e('Verification Code', 'fluent-community'); ?></label>
                </div>
                <div class="fs_input_wrap">
                    <input type="text" id="fcom_field_verification"
                           placeholder="<?php esc_html_e('2FA Code', 'fluent-community'); ?>" name="_email_verification_code"
                           required/>
                </div>
            </div>
            <div class="fcom_form-group">
                <div class="fcom_form_input">
                    <button type="submit" class="fcom_btn has_svg_loader fcom_btn_primary">
                        <svg version="1.1" class="fls_loading_svg" x="0px" y="0px" width="40px" height="20px" viewBox="0 0 50 50" style="enable-background:new 0 0 50 50;" xml:space="preserve">
                            <path fill="currentColor" d="M43.935,25.145c0-10.318-8.364-18.683-18.683-18.683c-10.318,0-18.683,8.365-18.683,18.683h4.068c0-8.071,6.543-14.615,14.615-14.615c8.072,0,14.615,6.543,14.615,14.615H43.935z">
                                <animateTransform attributeType="xml"
                                                  attributeName="transform"
                                                  type="rotate"
                                                  from="0 25 25"
                                                  to="360 25 25"
                                                  dur="0.6s"
                                                  repeatCount="indefinite"/>
                            </path>
                        </svg>
                        <span> <?php esc_html_e('Complete Signup', 'fluent-community'); ?></span>
                    </button>
                </div>
            </div>
        </div>

        <?php
        return ob_get_clean();
    }

    public static function validateVerificationCode($code, $verificationToken, $formData)
    {
        list($data, $signature) = explode('.', $verificationToken, 2);
        $expectedSignature = hash_hmac('sha256', $data, SECURE_AUTH_KEY);

        if (!hash_equals($expectedSignature, $signature)) {
            return new \WP_Error('invalid_token', __('Invalid verification token. Please try again', 'fluent-community'));
        }

        $data = json_decode(base64_decode($data), true);
        if ($data['expires'] < time()) {
            return new \WP_Error('expired_token', __('Verification token has expired. Please try again.', 'fluent-community'));
        }

        if ($data['email'] !== $formData['email']) {
            return new \WP_Error('invalid_email', __('Invalid email address. Please try again', 'fluent-community'));
        }

        if (!wp_check_password($code, $data['code_hash'])) {
            return new \WP_Error('invalid_code', __('Invalid verification code. Please try again', 'fluent-community'));
        }

        return true;
    }

    public static function isAuthRateLimit()
    {
        if (apply_filters('fluent_community/auth/disable_rate_limit', false)) {
            return true;
        }

        $transientKey = 'fluent_com_rate_limit_' . md5(Helper::getIp());
        $rateLimit = get_transient($transientKey);

        if (!$rateLimit) {
            $rateLimit = 0;
        }

        if ($rateLimit >= 10) {
            return new \WP_Error('rate_limit', __('Too many requests. Please try again later', 'fluent-community'));
        }

        $rateLimit = $rateLimit + 1;
        set_transient($transientKey, $rateLimit, 300); // per 5 minutes
        return true;
    }


    public static function nativeLoginForm($args = array(), $hiddenFields = [])
    {
        $defaults = array(
            'echo'           => true,
            'redirect'       => (is_ssl() ? 'https://' : 'http://')
                . (isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '')
                . (isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : ''),
            'form_id'        => 'loginform',
            'label_username' => __('Email Address', 'fluent-community'),
            'label_password' => __('Password', 'fluent-community'),
            'label_remember' => __('Remember Me', 'fluent-community'),
            'label_log_in'   => __('Log In', 'fluent-community'),
            'id_username'    => 'user_login',
            'id_password'    => 'user_pass',
            'id_remember'    => 'rememberme',
            'id_submit'      => 'wp-submit',
            'remember'       => true,
            'value_username' => '',
            'username_placeholder' => __('Your account email address', 'fluent-community'),
            'password_placeholder' => __('Your account password', 'fluent-community'),
            'value_remember' => false,
        );

        $args = wp_parse_args($args, apply_filters('login_form_defaults', $defaults)); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        $login_form_top = apply_filters('login_form_top', '', $args); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        $login_form_middle = apply_filters('login_form_middle', '', $args); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        $login_form_bottom = apply_filters('login_form_bottom', '', $args); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        $actionUrl = esc_url(site_url('wp-login.php', 'login_post'));

        if (isset($args['action_url'])) {
            $actionUrl = esc_url($args['action_url']);
        }

        foreach ($hiddenFields as $key => $value) {
            $login_form_top .= \sprintf(
                '<input type="hidden" name="%1$s" value="%2$s" />',
                esc_attr($key),
                esc_attr($value)
            );
        }

        $form = \sprintf(
                '<form name="%1$s" id="%1$s" action="%2$s" method="post">',
                esc_attr($args['form_id']),
                $actionUrl
            ) .
            $login_form_top .
            \sprintf(
                '<p class="login-username fcom_form-group">
				<label for="%1$s">%2$s</label>
				<input type="text" name="log" id="%1$s" autocomplete="username" class="input" value="%3$s" placeholder="%4$s" size="20" />
			</p>',
                esc_attr($args['id_username']),
                esc_html($args['label_username']),
                esc_attr($args['value_username']),
                esc_attr($args['username_placeholder']),
            ) .
            \sprintf(
                '<p class="login-password fcom_form-group">
				<label for="%1$s">%2$s</label>
				<input type="password" name="pwd" id="%1$s" autocomplete="current-password" placeholder="%3$s" class="input" value="" size="20" />
			</p>',
                esc_attr($args['id_password']),
                esc_html($args['label_password']),
                esc_attr($args['password_placeholder'])
            ) .
            $login_form_middle .
            ($args['remember'] ?
                \sprintf(
                    '<p class="login-remember fcom_form-group"><label><input name="rememberme" type="checkbox" id="%1$s" value="forever"%2$s /> %3$s</label></p>',
                    esc_attr($args['id_remember']),
                    ($args['value_remember'] ? ' checked="checked"' : ''),
                    esc_html($args['label_remember'])
                ) : ''
            ) .
            \sprintf(
                '<p class="login-submit">
				<input type="submit" name="wp-submit" id="%1$s" class="button button-primary" value="%2$s" />
				<input type="hidden" name="redirect_to" value="%3$s" />
			</p>',
                esc_attr($args['id_submit']),
                esc_attr($args['label_log_in']),
                esc_url($args['redirect'])
            ) .
            $login_form_bottom .
            '</form>';

        if ($args['echo']) {
            echo $form; // @phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            return $form;
        }
    }
}
