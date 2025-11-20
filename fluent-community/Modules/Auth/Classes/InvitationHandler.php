<?php

namespace FluentCommunity\Modules\Auth\Classes;

use FluentCommunity\App\App;
use FluentCommunity\App\Models\BaseSpace;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Services\Helper;

class InvitationHandler
{
    public function register()
    {
        add_filter('fluent_community/auth/invitation', function ($invitation, $token) {
            return Invitation::where('message_rendered', $token)
                ->whereIn('status', ['pending', 'active'])
                ->first();
        }, 10, 2);

        add_action('fluent_community/auth/show_invitation_for_user', [$this, 'showCommunityOnBoard'], 10, 2);
        add_action('wp_ajax_fcom_user_accept_invitation', [$this, 'acceptInvitationAjax']);
        add_filter('fluent_community/auth/after_login_with_invitation', [$this, 'handleInvitationLogin'], 10, 3);
        add_filter('fluent_community/auth/after_signup_redirect_url', function ($redirecctUrl, $user, $postedData) {
            if (empty($postedData['invitation_token'])) {
                return $redirecctUrl;
            }

            $invitation = Invitation::where('message_rendered', $postedData['invitation_token'])
                ->whereIn('status', ['pending', 'active'])
                ->first();

            if (!$invitation || !$user || !$invitation->isValid()) {
                return $redirecctUrl;
            }

            if ($invitation->message && $invitation->message != $user->user_email) {
                return $redirecctUrl;
            }

            if ($invitation->post_id) {
                $space = BaseSpace::withoutGlobalScopes()->find($invitation->post_id);
                if ($space) {
                    $role = 'member';
                    if ($space->type == 'course') {
                        $role = 'student';
                    }
                    $isNew = Helper::addToSpace($space, $user->ID, $role);
                    if ($isNew && !$invitation->message) {
                        $invitation->reactions_count = $invitation->reactions_count + 1;
                        $invitation->save();
                    }
                }
                $redirecctUrl = $space->getPermalink();
            }

            if ($invitation->message) {
                $invitation->status = 'accepted';
                $invitation->save();
            }

            return $redirecctUrl;
        }, 10, 3);
    }

    public function addShortcode($atts)
    {
        return 'Hello world!';
    }

    public function showCommunityOnBoard($invitation, $frameData)
    {
        $user = Helper::getCurrentUser();
        $user->syncXProfile(false);
        $frameData['title'] = __('Accept Invitation', 'fluent-community');

        $space = BaseSpace::withoutGlobalScopes()->find($invitation->post_id);

        $spaceName = $space ? $space->title : __('the community', 'fluent-community');

        /* translators: %1$s is replaced by the name of the user, %2$s is replaced by the name of the inviter, %3$s is replaced by the name of the space */
        $frameData['description'] = \sprintf(__('Welcome back %1$s. %2$s has invited you to join in %3$s. Please click the button below to continue.', 'fluent-community'),
            $user->display_name,
            $invitation->xprofile ? $invitation->xprofile->display_name : __('Someone', 'fluent-community'),
            '<b>' . $spaceName . '</b>'
        );

        $frameData['invitation_token'] = $invitation->message_rendered;

        App::make('view')->render('auth.logged_in_accept', $frameData);
    }

    public function acceptInvitationAjax()
    {
        $token = isset($_POST['invitation_token']) ? sanitize_text_field(wp_unslash($_POST['invitation_token'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $user = Helper::getCurrentUser();

        $redirectUrl = $this->handleInvitationLogin(null, $user, $token);

        if (is_wp_error($redirectUrl)) {
            wp_send_json([
                'message' => $redirectUrl->get_error_message()
            ], 422);
        }

        wp_send_json([
            'redirect_url' => $redirectUrl,
            'message'      => __('Invitation accepted successfully. Please wait...', 'fluent-community')
        ]);
    }

    public function handleInvitationLogin($url, $user, $token)
    {
        $userModel = User::find($user->ID);

        $invitation = Invitation::where('message_rendered', $token)
            ->whereIn('status', ['pending', 'active'])
            ->first();

        if (!$userModel || !$invitation || !$invitation->isValid() || ($invitation->message && $invitation->message != $userModel->user_email)) {
            return new \WP_Error('invalid_invitation', __('Invalid invitation token. Please try again', 'fluent-community'));
        }

        $userModel->syncXProfile(true);

        $space = null;
        if ($invitation->post_id) {
            $space = BaseSpace::withoutGlobalScopes()->find($invitation->post_id);
            if ($space) {
                $role = 'member';
                if ($space->type == 'course') {
                    $role = 'student';
                }
                $isNew = Helper::addToSpace($space, $userModel->ID, $role);

                if ($isNew && !$invitation->message) {
                    $invitation->reactions_count = $invitation->reactions_count + 1;
                    $invitation->save();
                }
            }
        }

        if ($invitation->message) {
            $invitation->status = 'accepted';
            $invitation->save();
        }

        $redirectUrl = Helper::baseUrl();
        if ($space) {
            $redirectUrl = $space->getPermalink();
        }

        return $redirectUrl;
    }

}
