<?php

namespace FluentCommunityPro\App\Hooks\Handlers;

use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunityPro\App\Services\ProHelper;
use FluentCrm\App\Services\Funnel\FunnelHelper;

class AccessManagementCrmHandler
{
    private $attachedUserTags = [];

    public function register()
    {
        if (!defined('FLUENTCRM') || !Helper::isFeatureEnabled('has_crm_sync')) {
            return;
        }

        add_action('fluent_community/space/joined', [$this, 'joinedInSpace'], 999, 2);
        add_action('fluent_community/space/user_left', [$this, 'leftFromSpace'], 999, 2);

        add_action('fluent_community/course/enrolled', [$this, 'joinedInSpace'], 999, 2);
        add_action('fluent_community/course/student_left', [$this, 'leftFromSpace'], 999, 2);

        // From FluentCRM side
        add_filter('fluentcrm_contact_added_to_tags', [$this, 'contactAddedToTags'], 999, 2);
        add_filter('fluentcrm_contact_removed_from_tags', [$this, 'contactRemovedFromTags'], 999, 2);

    }

    public function joinedInSpace($space, $userId)
    {
        $settings = $this->getSettings();
        $taggingMaps = Arr::get($settings, 'tagging_maps', []);
        $tagId = isset($taggingMaps[$space->id]) ? $taggingMaps[$space->id] : false;
        if (!$tagId) {
            return false;
        }

        if (!empty($this->attachedUserTags[$userId]) && in_array($tagId, $this->attachedUserTags[$userId])) {
            // Already Done
            return false;
        }

        $subscriber = FluentCrmApi('contacts')->getContactByUserRef($userId);

        if (!$subscriber) {
            if (!$this->isKeyEnabled('create_crm_contact')) {
                return false;
            }
            // let's create the contact
            $subscriberData = FunnelHelper::prepareUserData($userId);
            $subscriberData['status'] = 'subscribed';
            $subscriberData['source'] = 'fluent-community';
            $subscriber = FunnelHelper::createOrUpdateContact($subscriberData);
            if (!$subscriber) {
                return false;
            }
        }

        $subscriber->attachTags([$tagId]);

        if (empty($this->attachedUserTags[$userId])) {
            $this->attachedUserTags[$userId] = [];
        }

        $this->attachedUserTags[$userId][] = $tagId;

        return true;
    }

    public function leftFromSpace($space, $userId)
    {
        $settings = $this->getSettings();
        $taggingMaps = Arr::get($settings, 'linked_maps', []);

        $tagId = isset($taggingMaps[$space->id]) ? $taggingMaps[$space->id] : false;

        if (!$tagId) {
            return false;
        }

        $subscriber = FluentCrmApi('contacts')->getContactByUserRef($userId);
        if (!$subscriber) {
            return false;
        }
        
        $subscriber->detachTags([$tagId]);

        return true;
    }

    public function contactAddedToTags($tagIds, $subscriber)
    {
        $settings = $this->getSettings();
        $taggingMaps = Arr::get($settings, 'tagging_maps', []);

        if (empty($taggingMaps)) {
            return false;
        }

        $spaceIds = array_keys(array_filter(array_map(function ($tagId) use ($tagIds) {
            return in_array($tagId, $tagIds);
        }, $taggingMaps)));

        if (!$spaceIds) {
            return false;
        }

        $userId = $subscriber->getWpUserId();

        if (!$userId) {
            if (!$this->isKeyEnabled('create_user')) {
                return false;
            }

            // We have to create the user here
            $userId = ProHelper::createUserFromCrmContact($subscriber);

            if (!$userId || is_wp_error($userId)) {
                return false;
            }

            if ($this->isKeyEnabled('send_welcome_email')) {
                wp_new_user_notification($userId, null, 'user');
            }
        }

        $userId = (int)$userId;

        if (!$userId) {
            return false;
        }

        if (empty($this->attachedUserTags[$userId])) {
            $this->attachedUserTags[$userId] = [];
        }
        $this->attachedUserTags[$userId] = array_merge($this->attachedUserTags[$userId], $tagIds);

        foreach ($spaceIds as $spaceId) {
            Helper::addToSpace($spaceId, $userId, 'member', 'by_admin');
        }


        return true;
    }

    public function contactRemovedFromTags($tagIds, $subscriber)
    {
        $settings = $this->getSettings();
        $taggingMaps = Arr::get($settings, 'linked_maps', []);

        if (empty($taggingMaps)) {
            return false;
        }

        $spaceIds = array_keys(array_filter(array_map(function ($tagId) use ($tagIds) {
            return in_array($tagId, $tagIds);
        }, $taggingMaps)));

        if (!$spaceIds) {
            return false;
        }

        $userId = $subscriber->getWpUserId();
        if (!$userId) {
            return false;
        }

        foreach ($spaceIds as $spaceId) {
            Helper::removeFromSpace($spaceId, $userId, 'by_admin');
        }

        return true;
    }

    /*
     * properties: [
            'is_enabled'         => 'no',
            'tagging_maps'       => [],
            'linked_maps'        => [],
            'create_crm_contact' => 'yes',
            'create_user'        => 'no',
            'send_welcome_email' => 'yes'
        ];
     */
    private function getSettings()
    {
        static $settings;
        if ($settings) {
            return $settings;
        }

        $settings = get_option('_fcom_crm_tagging', []);

        return $settings;
    }

    private function isKeyEnabled($key)
    {
        $settings = $this->getSettings();
        return isset($settings[$key]) && $settings[$key] === 'yes';
    }
}
