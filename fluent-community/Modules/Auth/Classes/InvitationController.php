<?php

namespace FluentCommunity\Modules\Auth\Classes;

use FluentCommunity\App\Http\Controllers\Controller;
use FluentCommunity\App\Models\BaseSpace;
use FluentCommunity\App\Models\Space;
use FluentCommunity\App\Models\SpaceUserPivot;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\App\Services\ProfileHelper;
use FluentCommunity\Framework\Http\Request\Request;
use FluentCommunity\Framework\Support\Arr;

class InvitationController extends Controller
{
    public function getInvitations(Request $request)
    {
        $user = $this->getUser(true);
        $isMod = $user->isCommunityModerator();
        $spaceId = intval($request->get('space_id'));

        $space = Space::findOrFail($spaceId);
        $space->verifyUserPermisson($user, 'community_moderator');

        $status = $request->get('status', 'pending');
        if ($status == 'all') {
            $status = '';
        }

        $inviatations = Invitation::where('user_id', $user->ID)
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->with(['xprofile' => function ($query) {
                $query->select(ProfileHelper::getXProfilePublicFields());
            }])
            ->when(!$isMod, function ($query) use ($user) {
                $query->where('user_id', $user->ID);
            })
            ->when($spaceId, function ($query) use ($spaceId) {
                $query->where('post_id', $spaceId);
            })
            ->orderBy('id', 'desc')
            ->paginate();

        foreach ($inviatations as $inviatation) {
            if ($inviatation->isValid()) {
                $inviatation->access_url = $inviatation->getAccessUrl();
            }
        }

        return [
            'invitations' => $inviatations,
            'is_mod'      => $isMod
        ];
    }

    public function delete(Request $request, $invitationId)
    {
        $invitationId = intval($invitationId);
        $inivitation = Invitation::findOrFail($invitationId);
        $user = $this->getUser(true);
        $space = Space::findOrFail($inivitation->post_id);
        $space->verifyUserPermisson($user, 'community_moderator');

        $inivitation->delete();

        return [
            'message' => __('Invitation deleted successfully', 'fluent-community')
        ];
    }

    public function store(Request $request)
    {
        $data = $request->all();
        $this->validate($data, [
            'email'    => 'required|email',
            'space_id' => 'required'
        ]);

        $spaceId = (int)Arr::get($data, 'space_id');
        $user = $this->getUser(true);

        $space = Space::findOrFail($spaceId);
        $space->verifyUserPermisson($user, 'community_moderator');

        $inviteeEmail = sanitize_email(Arr::get($data, 'email'));

        $indivatationData = array_filter([
            'email'        => $inviteeEmail,
            'user_id'      => $user->ID,
            'invitee_name' => sanitize_text_field(Arr::get($data, 'invitee_name')),
            'space_id'     => $space->id
        ]);

        $invitation = InvitationService::invite($indivatationData);
        if (is_wp_error($invitation)) {
            return $this->sendError([
                'message' => $invitation->get_error_message()
            ]);
        }

        // Now send the email for the invitation
        InvitationService::sendInvitationEmail($invitation);

        return [
            'message'    => __('Invitation has been sent successfully', 'fluent-community'),
            'invitation' => $invitation
        ];
    }

    public function createNewLink(Request $request)
    {
        $data = $request->all();
        $this->validate($data, [
            'title'    => 'required',
            'space_id' => 'required',
        ]);

        $spaceId = (int)Arr::get($data, 'space_id');
        $space = Space::findOrFail($spaceId);
        $user = $this->getUser(true);
        $space->verifyUserPermisson($user, 'community_moderator');

        $indivatationData = array_filter([
            'email'    => '',
            'user_id'  => $user->ID,
            'space_id' => $space->id,
            'title'    => sanitize_text_field(Arr::get($data, 'title')),
            'limit'    => sanitize_text_field(Arr::get($data, 'limit', 0)),
            'expire_date'   => sanitize_text_field(Arr::get($data, 'expire_date', '')),
        ]);

        $invitation = apply_filters('fluent_community/create_invitation_link', null, $indivatationData);

        if(!$invitation) {
            return $this->sendError([
                'message' => __('Something went wrong', 'fluent-community')
            ]);
        }

        if (is_wp_error($invitation)) {
            return $this->sendError([
                'message' => $invitation->get_error_message()
            ]);
        }

        $invitation->access_url =  $invitation->getAccessUrl();

        return [
            'message'    => __('Invitation link has been created', 'fluent-community'),
            'invitation' => $invitation
        ];
    }

    public function resend(Request $request, $invitationId)
    {
        $invitation = Invitation::findOrFail($invitationId);

        if ($invitation->reactions_count > 5) {
            return $this->sendError([
                'message' => __('You can not resend this invitation', 'fluent-community')
            ]);
        }

        InvitationService::sendInvitationEmail($invitation);

        return [
            'message' => __('Invitation has been resent successfully', 'fluent-community')
        ];
    }

    private function validateExistingWpUser($email, $spaceId = null)
    {
        $user = get_user_by('email', $email);

        if ($user) {
            if ($spaceId && !$this->userExistInSpace($spaceId, $user->ID)) {
                return true;
            }

            wp_send_json([
                'message' => __('You are already a member of this site', 'fluent-community')
            ], 400);
        }
    }

    private function isInviteMembers()
    {
        $settings = Helper::getOnboardingSettings();

        return Arr::get($settings, 'is_onboarding_enabled', '') == 'yes';
    }

    private function isTodayInvitationLimitExceeded($userId)
    {
        $todayInvitationCount = Invitation::getTodayInvitationCount($userId);

        return $todayInvitationCount > 10;
    }

    private function userExistInSpace($spaceId, $userId)
    {
        return SpaceUserPivot::where('space_id', $spaceId)
            ->where('user_id', $userId)
            ->exists();
    }
}
