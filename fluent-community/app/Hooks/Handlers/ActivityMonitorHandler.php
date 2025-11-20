<?php

namespace FluentCommunity\App\Hooks\Handlers;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\Activity;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Support\Arr;

class ActivityMonitorHandler
{
    public function register()
    {
        add_action('fluent_community/comment_added', [$this, 'handleNewCommentEvent'], 10, 2);
        add_action('fluent_community/feed/created', [$this, 'handleFeedCreated'], 10, 1);

        add_action('fluent_community/track_activity', [$this, 'trackActivity'], 10);

        add_action('profile_update', function ($userId) {
            if (Utility::getPrivacySetting('enable_user_sync') === 'no') {
                return;
            }

            $user = get_user_by('ID', $userId);
            $xprofile = XProfile::where('user_id', $userId)->first();
            if (!$xprofile) {
                return;
            }
            $firstName = $user->first_name;
            $lastName = $user->last_name;
            $displayName = trim($firstName . ' ' . $lastName);
            if ($displayName && $xprofile->display_name != $displayName) {
                $xprofile->display_name = $displayName;
                $xprofile->save();
            }
        });

        add_action('wp_ajax_fluent_community_renew_nonce', function () {

            $ajax_nonce = Arr::get($_REQUEST, 'ajax_nonce'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

            if (!wp_verify_nonce($ajax_nonce, 'fluent_community_ajax_nonce')) {
                wp_send_json(['message' => 'Invalid nonce'], 400);
            }

            $currentProfile = Helper::getCurrentProfile();
            if (!$currentProfile) {
                wp_send_json(['message' => 'Invalid user'], 400);
            }

            wp_send_json([
                'rest_nonce' => wp_create_nonce('wp_rest'),
                'ajax_nonce' => wp_create_nonce('fluent_community_ajax_nonce'),
            ], 200);
        });
    }


    public function handleNewCommentEvent($comment, $feed)
    {
        $isPublic = 1;

        if ($feed->space_id) {
            $isPublic = $feed->space->privacy == 'public';
        }

        $data = [
            'user_id'     => $comment->user_id,
            'feed_id'     => $feed->id,
            'space_id'    => $feed->space_id,
            'related_id'  => $comment->id,
            'action_name' => 'comment_added',
            'is_public'   => $isPublic,
        ];

        Activity::create($data);

        do_action('fluent_community/track_activity');
    }

    public function handleFeedCreated($feed)
    {
        $isPublic = 1;

        if ($feed->space_id) {
            $isPublic = $feed->space->privacy == 'public';
        }

        $data = [
            'user_id'     => $feed->user_id,
            'feed_id'     => $feed->id,
            'space_id'    => $feed->space_id,
            'action_name' => 'feed_published',
            'is_public'   => $isPublic,
        ];

        Activity::create($data);

        do_action('fluent_community/track_activity');
    }

    public function trackActivity()
    {
        $currentProfile = Helper::getCurrentProfile();

        if (!$currentProfile) {
            return false;
        }

        $currentProfile->last_activity = current_time('mysql');
        $currentProfile->save();
    }
}
