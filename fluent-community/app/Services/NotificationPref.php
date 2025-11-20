<?php

namespace FluentCommunity\App\Services;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\NotificationSubscription;
use FluentCommunity\App\Models\Space;
use FluentCommunity\Framework\Support\Arr;

class NotificationPref
{
    public static function getGlobalPrefs()
    {
        $pref = Utility::getEmailNotificationSettings();
        $valids = ['com_my_post_mail', 'reply_my_com_mail', 'mention_mail', 'digest_email_status', 'messaging_email_status'];

        $pref = Arr::only($pref, $valids);

        $pref = array_map(function ($value) {
            return $value === 'yes' ? 1 : 0;
        }, $pref);

        return $pref;
    }

    public static function getUserPrefs($userId)
    {
        return Utility::getFromCache('user_notification_pref_' . $userId, function () use ($userId) {
            $prefs = NotificationSubscription::where('user_id', $userId)
                ->select(['notification_type', 'is_read', 'object_id'])
                ->get();

            if ($prefs->isEmpty()) {
                return [];
            }

            $fromattedPrefs = [];
            foreach ($prefs as $pref) {
                $key = $pref->notification_type;
                if ($pref->object_id) {
                    $key = $pref->notification_type . '_' . $pref->object_id;
                }
                $fromattedPrefs[$key] = $pref->is_read;
            }
            return $fromattedPrefs;
        }, 86400 * 30);
    }

    public static function updateUserPrefs($userId, $prefs = [])
    {
        $validKeys = [
            'com_my_post_mail',
            'reply_my_com_mail',
            'mention_mail',
            'digest_mail'
        ];

        $validPrefs = [];
        foreach ($prefs as $key => $value) {
            if (in_array($key, $validKeys)) {
                $validPrefs[$key] = $value ? 1 : 0;
            } else if (strpos($key, 'np_by_') === 0) {
                // This is the notification by object. We are processing per key when updating
                $validPrefs[$key] = $value ? 1 : 0;
            } else if ($key == 'message_email_frequency') {
                $validPrefs[$key] = $value;
            }
        }

        $ids = [];
        foreach ($validPrefs as $key => $value) {
            $exist = NotificationSubscription::where('user_id', $userId)
                ->where('notification_type', $key)
                ->first();

            if ($exist) {
                $exist->is_read = $value;
                $exist->save();
                $ids[] = $exist->id;
            } else {
                $newData = [
                    'user_id'           => $userId,
                    'object_type'       => 'notification_pref',
                    'notification_type' => $key,
                    'is_read'           => $value
                ];

                $isByObject = strpos($key, 'np_by_') === 0;

                // Find the last number in the key _{number}
                if ($isByObject) {
                    $matches = [];
                    preg_match('/\d+$/', $key, $matches);
                    if (isset($matches[0])) {
                        $objectId = (int)$matches[0];
                        if ($objectId && Space::where('id', $objectId)->exists()) {
                            // Now remove the last _{number} from the key. Make sure you are removing the last one
                            $newData['notification_type'] = substr($key, 0, strrpos($key, '_'));
                            $newData['object_id'] = $objectId;
                            $exist = NotificationSubscription::where('user_id', $userId)
                                ->where('notification_type', $newData['notification_type'])
                                ->where('object_id', $objectId)
                                ->first();
                            if($exist) {
                                $exist->is_read = $value;
                                $exist->save();
                                $ids[] = $exist->id;
                                continue;
                            }
                        }
                    }
                }
                $created = NotificationSubscription::create($newData);
                $ids[] = $created->id;
            }
        }

        // Delete the rest
        NotificationSubscription::where('user_id', $userId)
            ->whereNotIn('id', $ids)
            ->delete();

        // delete the cache now
        Utility::forgetCache('user_notification_pref_' . $userId);

        return self::getUserPrefs($userId);
    }

    public static function updateUserSinglePref($userId, $prefKey, $prefValue, $objectId = null)
    {
        $prefs = self::getUserPrefs($userId);
        $prefs[$prefKey] = $prefValue ? 1 : 0;

        if ($objectId) {
            $prefs[$prefKey . '_' . $objectId] = $prefValue;
        }

        return self::updateUserPrefs($userId, $prefs);
    }

    public static function isPrefEnabled($userId, $prefKey, $globalStatus = false)
    {
        $prefs = self::getUserPrefs($userId);

        if (!isset($prefs[$prefKey])) {
            return $globalStatus;
        }

        return (bool)$prefs[$prefKey];
    }

    public static function willGetCommentEmail($userId, $globalStatus = null)
    {
        if ($globalStatus === null) {
            $globalStatus = Arr::get(self::getGlobalPrefs(), 'com_my_post_mail', false);
        }

        return self::isPrefEnabled($userId, 'com_my_post_mail', $globalStatus);
    }

    public static function willGetCommentReplyEmail($userId, $globalStatus = null)
    {
        if ($globalStatus === null) {
            $globalStatus = Arr::get(self::getGlobalPrefs(), 'reply_my_com_mail', false);
        }

        return self::isPrefEnabled($userId, 'reply_my_com_mail', $globalStatus);
    }

    public static function willGetMentionEmail($userId, $globalStatus = null)
    {
        if ($globalStatus === null) {
            $globalStatus = Arr::get(self::getGlobalPrefs(), 'mention_mail', false);
        }

        return self::isPrefEnabled($userId, 'mention_mail', $globalStatus);
    }

    public static function willGetDigestEmail($userId, $globalStatus = null)
    {
        if ($globalStatus === null) {
            $globalStatus = Arr::get(self::getGlobalPrefs(), 'digest_mail', false);
        }

        return self::isPrefEnabled($userId, 'digest_mail', $globalStatus);
    }

}
