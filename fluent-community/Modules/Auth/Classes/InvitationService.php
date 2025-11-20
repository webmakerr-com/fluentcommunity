<?php

namespace FluentCommunity\Modules\Auth\Classes;


use FluentCommunity\App\App;
use FluentCommunity\App\Models\SpaceUserPivot;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\App\Services\Libs\Mailer;
use FluentCommunity\Framework\Support\Arr;

class InvitationService
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

        $errors = apply_filters('registration_errors', $errors, $sanitized_user_login, $user_email); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

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

    public static function invite($inviatationData)
    {
        // Validate the data
        if (empty($inviatationData['email']) || !is_email($inviatationData['email'])) {
            return new \WP_Error('email_required', __('Email is required', 'fluent-community'));
        }

        if (empty($inviatationData['user_id'])) {
            return new \WP_Error('user_id_required', __('User ID is required', 'fluent-community'));
        }

        $spaceId = (int)Arr::get($inviatationData, 'space_id');

        $inviteeUser = get_user_by('email', $inviatationData['email']);

        if ($inviteeUser) {
            if (empty($spaceId)) {
                return new \WP_Error('user_exist', __('User already exists.', 'fluent-community'));
            }

            if ($spaceId) {
                // Let's check if the user is already a member of the space
                $spaceUser = SpaceUserPivot::where('user_id', $inviteeUser->ID)
                    ->where('space_id', $spaceId)
                    ->first();

                if ($spaceUser) {
                    return new \WP_Error('user_exist', __('User already exists in the space.', 'fluent-community'));
                }
            }
        }

        // Check if the user already invited the same email
        $exist = Invitation::where('message', $inviatationData['email'])
            ->when($spaceId, function ($query) use ($spaceId) {
                return $query->where('post_id', $spaceId);
            })
            ->where('user_id', $inviatationData['user_id'])
            ->first();

        if ($exist) {
            return new \WP_Error('invitation_exist', __('You have already invited this user', 'fluent-community'));
        }

        $formattedData = array_filter([
            'message'          => $inviatationData['email'],
            'user_id'          => $inviatationData['user_id'],
            'status'           => 'pending',
            'post_id'          => $spaceId,
            'message_rendered' => md5($inviatationData['email'] . '_' . time() . '_' . wp_generate_uuid4(10) . '_' . $inviatationData['user_id']),
            'meta'             => [
                'invitee_name' => sanitize_text_field(Arr::get($inviatationData, 'invitee_name'))
            ]
        ]);

        $inviation = Invitation::create($formattedData);

        do_action('fluent_community/invitation_created', $inviation);

        return $inviation;
    }

    public static function createLinkInvite($data)
    {
        $formattedData = array_filter([
            'message'          => '',
            'user_id'          => $data['user_id'],
            'status'           => 'active',
            'post_id'          => $data['space_id'],
            'message_rendered' => md5($data['space_id'] . '_' . time() . '_' . wp_generate_uuid4(10) . '_' . $data['user_id']),
            'meta'             => Arr::only($data, ['title', 'limit', 'expire_date'])
        ]);

        $inviation = Invitation::create($formattedData);

        do_action('fluent_community/invitation_link_created', $inviation);

        return $inviation;
    }

    public static function sendInvitationEmail(Invitation $invitation)
    {
        $xProfile = $invitation->xprofile;
        if (!$xProfile) {
            return new \WP_Error('xprofile_not_found', __('XProfile not found', 'fluent-community'));
        }

        $portalSettings = Helper::generalSettings();

        /* translators: %1$s is replaced by the name of the user, %2$s is replaced by the title of the site */
        $subject = \sprintf(__('%1$s has invited you to join the %2$s', 'fluent-community'),
            $xProfile->display_name,
            Arr::get($portalSettings, 'site_title')
        );

        if ($invitation->post_id) {
            $space = $invitation->space;
            if ($space) {
                $subject = \sprintf(
                    /* translators: %1$s is replaced by the name of the user, %2$s is replaced by the title of the space, %3$s is replaced by the title of the site */
                    __('%1$s has invited you to join the %2$s on %3$s', 'fluent-community'),
                    $xProfile->display_name,
                    $space->title,
                    Arr::get($portalSettings, 'site_title')
                );
            }
        }

        $invitation->reactions_count += 1;
        $invitation->save();

        $emailComposer = new \FluentCommunity\App\Services\Libs\EmailComposer();

        $emailComposer->addBlock('html_content', self::getInviationEmailHtml($invitation));
        $emailComposer->setDefaultLogo();
        $emailComposer->setDefaultFooter();

        $emailBody = $emailComposer->getHtml();

        $mailer = new Mailer($invitation->message, $subject, $emailBody);

        return $mailer->send();
    }

    private static function getInviationEmailHtml(Invitation $invitation)
    {
        $portalSettings = Helper::generalSettings();
        $siteTitle = Arr::get($portalSettings, 'site_title');

        if ($invitation->space) {
            /* translators: %1$s is replaced by the title of the space, %2$s is replaced by the title of the site */
            $siteTitle = \sprintf(__('%1$s on %2$s', 'fluent-community'), $invitation->space->title, $siteTitle);
        }

        return (string)App::make('view')->make('email.Default._invitation', [
            'by_name'      => $invitation->xprofile->display_name,
            'by_email'     => $invitation->user->user_email,
            'site_title'   => $siteTitle,
            'access_url'   => $invitation->getAccessUrl(),
            'invitee_name' => Arr::get($invitation->meta, 'invitee_name', 'there')
        ]);
    }
}
