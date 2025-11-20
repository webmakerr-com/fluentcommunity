<?php

namespace FluentCommunityPro\App\Modules\Integrations\Paymattic;

use WPPayForm\App\Models\Submission;
use WPPayForm\App\Models\Meta;
use FluentCommunity\App\Models\BaseSpace;
use FluentCommunity\Framework\Support\Arr;

class RemoveFromSpaceAction
{
    public function init()
    {
        add_action('wppayform/after_payment_status_change', array($this, 'handle'), 10, 3);
        add_action('wppayform/subscription_payment_canceled', array($this, 'handleSubscriptionCancelled'), 10, 4);
    }

    public function handle($submissionId, $newStatus)
    {
        if ($newStatus !== 'refunded') {
            return;
        }

        $entry = (new Submission())->getSubmission($submissionId);

        $settings = Meta::getFormMeta($entry->form_id, 'fluent_community_feeds');
        if (!$settings) {
            return;
        }

        // Not enable on refund then skip
        if (!Arr::get($settings, 'remove_on_refund')) {
            return;
        }


        $userId = $entry->user_id;
        $spaceIds = Arr::get($settings, 'space_ids');

        if (!$spaceIds || !$userId) {
            return;
        }

        static::removeFromSpaces($entry, $spaceIds, $userId);

        return true;
    }

    public function handleSubscriptionCancelled($submission, $subscription, $formId, $vendor_data)
    {
   
        $userId = $submission->user_id;
        // get form meta related to fcom feeds
        if (!$userId) {
            return;
        }

        $entry = (new Submission())->getSubmission($submission->id);

        $settings = Meta::getFormMeta($entry->form_id, 'fluent_community_feeds');
        if (!$settings) {
            return;
        }

        // Not enable on subscription cancel then skip
        if (!Arr::get($settings, 'remove_on_subscription_cancel')) {
           return;
        }

        $metaKey =  'wpf_fcom_subscription_' . $userId; //	ex: fcom_subscription_74

        // get user meta from wp user meta
        $userMetaInfos = get_user_meta($userId, $metaKey, false);

        if (!$userMetaInfos) {
            return;
        }
        
        $payment_method = $submission->payment_method;

        foreach ($userMetaInfos as $userMetaInfo) {
            $entryId = Arr::get($userMetaInfo, 'entry_id');
            if ($entryId != $submission->id) {
                continue;
            }

            $spaceIds = Arr::get($userMetaInfo, 'space_ids');

            if ($payment_method == 'stripe' && Arr::get($vendor_data, 'canceled_at', false)) {
                $expireDate = $vendor_data['canceled_at'];
            } else if ($payment_method == 'paypal' && Arr::get($vendor_data, 'end_time', false)) { // need to recheck this with paypal
                $expireDate = Arr::get($vendor_data, 'end_time');
            } else if ($payment_method == 'square' && Arr::get($vendor_data, 'canceled_date', false)) {
                // need to update this according to square
                $expireDate = Arr::get($vendor_data, 'canceled_date');
            }
            else {
                $expireDate = current_time('mysql');
            }
    
            // make expiry date to be in mysql time format
            $timestamp = strtotime($expireDate);
            $expireDate = gmdate('Y-m-d H:i:s', $timestamp);
    
            $currentDate = current_time('mysql');
    
            // check if exipried date is  already expired
            if (strtotime($expireDate) <= strtotime($currentDate)) {
                // remove from spaces
                static::removeFromSpaces($submission, $spaceIds, $userId);
                // delete user meta
                delete_user_meta($userId, $metaKey, $userMetaInfo);
            } else {
                // update user meta
                update_user_meta($userId, $metaKey, [
                    'expiry_date' => $expireDate,
                    'space_ids' => $spaceIds,
                    'entry_id' => $entryId,
                    'status' => 'updated'
                ]);
            }
        }

        return;
    }

    public static function removeFromSpaces($entry, $spaceIds, $userId)
    {
        $spaceIds = (array) $spaceIds;

        $spaces = BaseSpace::query()->withoutGlobalScopes()->whereIn('id', $spaceIds)->get();

        if (!$spaces) {
            return;
        }

        $removedSpaces = [];
        foreach ($spaces as $space) {
            if (\FluentCommunity\App\Services\Helper::removeFromSpace($space, $userId)) {
                $removedSpaces[] = $space->title;
            }
        }


        if (!$userId || !$spaces) {
            $message = __('Remove from space/spaces Skipped because user/space could not be found -fluent-community', 'fluent-community-pro');
            do_action('wppayform_log_data', [
                'form_id' => $entry->form_id,
                'submission_id' => $entry->id,
                'type' => 'failed',
                'created_by' => 'Paymattic BOT',
                'content' => $message
            ]);
        }

    
        $message = 'Removed from Space/Spaces: ' . implode(',', $removedSpaces);
        do_action('wppayform_log_data', [
            'form_id' => $entry->form_id,
            'submission_id' => $entry->id,
            'type' => 'success',
            'created_by' => 'Paymattic BOT',
            'content' => $message
        ]);
    }
}
