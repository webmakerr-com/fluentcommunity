<?php

namespace FluentCommunity\Modules\Migrations\Helpers;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\BaseSpace;
use FluentCommunity\App\Models\Comment;
use FluentCommunity\App\Models\Feed;
use FluentCommunity\App\Models\Media;
use FluentCommunity\App\Models\Reaction;
use FluentCommunity\App\Models\Space;
use FluentCommunity\App\Models\SpaceGroup;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\Framework\Support\Arr;

class BPMigratorHelper
{
    public static function getBbDataStats()
    {
        return [
            'groups'                => fluentCommunityApp('db')->table('bp_groups')->count(),
            'total_posts'           => fluentCommunityApp('db')->table('bp_activity')->where('type', 'activity_update')->count(),
            'total_comments'        => fluentCommunityApp('db')->table('bp_activity')->where('type', 'activity_comment')->count(),
            'total_reactions'       => class_exists('\BB_Reaction') ? fluentCommunityApp('db')->table('bb_user_reactions')->whereIn('item_type', ['activity_comment', 'activity'])->count() : 0,
            'total_community_users' => User::count(),
        ];
    }

    public static function migratePost($post, $spaceId = null)
    {
        return (new PostMigrator($post))->migrate();
    }

    public static function syncComments($activityId, Feed $feed)
    {
        // Let's manage the comments
        $comments = fluentCommunityApp('db')->table('bp_activity')
            ->where('type', 'activity_comment')
            ->where('item_id', $activityId)
            ->orderBy('id', 'ASC')
            ->get();

        $comments = self::buildCommentsTree($comments);
        $commentMaps = [];

        foreach ($comments as $comment) {
            $newComment = self::insertBBComment($comment, $feed);
            $commentMaps[$comment['id']] = $newComment->id;
            if ($comment['children']) {
                foreach ($comment['children'] as $child) {
                    $childComment = self::insertBBComment($child, $feed, $newComment->id);
                    $commentMaps[$child['id']] = $childComment->id;
                }
            }
        }

        return $commentMaps;
    }

    public static function insertBBComment($comment, $feed, $parentId = null)
    {
        $comemntData = [
            'user_id'          => $comment['user_id'],
            'post_id'          => $feed->id,
            'message'          => self::toMarkdown($comment['content']),
            'message_rendered' => $comment['content'],
            'type'             => 'comment'
        ];

        $media = self::getActivityMediaPreview($comment['id']);

        if ($media) {
            $comemntData['meta'] = $media;
        }

        if ($parentId) {
            $comemntData['parent_id'] = $parentId;
        }

        $newComment = new Comment();
        $newComment->fill($comemntData);
        $newComment->created_at = $comment['date_recorded'];
        $newComment->updated_at = $comment['date_recorded'];
        $newComment->save();

        return $newComment;
    }

    public static function buildCommentsTree($comments)
    {
        $commentTree = [];
        $commentMap = [];

        // First pass: create a map of all comments
        foreach ($comments as $comment) {
            $commentMap[$comment->id] = [
                'id'            => $comment->id,
                'content'       => self::cleanUpContent($comment->content),
                'user_id'       => $comment->user_id,
                'date_recorded' => $comment->date_recorded,
                'mptt_left'     => $comment->mptt_left,
                'mptt_right'    => $comment->mptt_right,
                'children'      => []
            ];
        }

        // Second pass: build the tree structure
        foreach ($comments as $comment) {
            $parentId = $comment->secondary_item_id;

            if ($parentId == $comment->item_id) {
                // This is a top-level comment
                $commentTree[] = &$commentMap[$comment->id];
            } else {
                // This is a reply to another comment
                $commentMap[$parentId]['children'][] = &$commentMap[$comment->id];
            }
        }

        // Sort the tree based on mptt_left values
        usort($commentTree, function ($a, $b) {
            return $a['mptt_left'] - $b['mptt_left'];
        });

        // Recursive function to sort children
        $sortChildren = function (&$node) use (&$sortChildren) {
            usort($node['children'], function ($a, $b) {
                return $a['mptt_left'] - $b['mptt_left'];
            });

            foreach ($node['children'] as &$child) {
                $sortChildren($child);
            }
        };

        // Sort children for each top-level comment
        foreach ($commentTree as &$topLevelComment) {
            $sortChildren($topLevelComment);
        }

        foreach ($commentTree as &$comment) {
            foreach ($comment['children'] as &$child) {
                $childComments = $child['children'];
                if ($childComments) {
                    $comment['children'] = array_merge($comment['children'], $childComments);
                    unset($child['children']);
                }
            }

            // short the children
            usort($comment['children'], function ($a, $b) {
                return $a['id'] - $b['id'];
            });
        }

        return $commentTree;
    }

    public static function cleanUpContent($content)
    {
        $pattern = '/<a class=\'bp-suggestions-mention\'[^>]*>(@[\w-]+)<\/a>/is';
        $replacement = '$1';
        $content = preg_replace($pattern, $replacement, $content);

        // replace \" or \' with " or '
        $content = str_replace(['\"', "\'"], ['"', "'"], $content);

        // Shortcut: decode all HTML entities (including hex and decimal) to their UTF-8 characters, including emoji
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($content);
    }

    public static function toMarkdown($html)
    {
        // Replace header tags
        $html = preg_replace('/<h1>(.*?)<\/h1>/', '# $1', $html);
        $html = preg_replace('/<h2>(.*?)<\/h2>/', '## $1', $html);
        $html = preg_replace('/<h3>(.*?)<\/h3>/', '### $1', $html);
        $html = preg_replace('/<h4>(.*?)<\/h4>/', '#### $1', $html);
        $html = preg_replace('/<h5>(.*?)<\/h5>/', '##### $1', $html);
        $html = preg_replace('/<h6>(.*?)<\/h6>/', '###### $1', $html);

        // Replace bold and italic tags
        $html = preg_replace('/<strong>(.*?)<\/strong>/', '**$1**', $html);
        $html = preg_replace('/<b>(.*?)<\/b>/', '**$1**', $html);
        $html = preg_replace('/<em>(.*?)<\/em>/', '*$1*', $html);
        $html = preg_replace('/<i>(.*?)<\/i>/', '*$1*', $html);

        // Replace unordered lists
        $html = preg_replace('/<ul>(.*?)<\/ul>/', "\n$0\n", $html);
        $html = preg_replace('/<li>(.*?)<\/li>/', "- $1\n", $html);

        // Replace ordered lists
        $html = preg_replace('/<ol>(.*?)<\/ol>/', "\n$0\n", $html);
        $html = preg_replace('/<li>(.*?)<\/li>/', "1. $1\n", $html);

        // Replace links
        $html = preg_replace('/<a href="(.*?)".*?>(.*?)<\/a>/', '[$2]($1)', $html);

        // Replace images
        $html = preg_replace('/<img src="(.*?)".*?\/?>/', '![](=$1)', $html);

        // Replace blockquotes
        $html = preg_replace('/<blockquote>(.*?)<\/blockquote>/', "> $1\n", $html);

        // Replace horizontal rules
        $html = preg_replace('/<hr\/>/', "---\n", $html);

        // Replace pre and code tags
        $html = preg_replace('/<pre><code>(.*?)<\/code><\/pre>/', "```\n\$1\n```", $html);
        $html = preg_replace('/<code>(.*?)<\/code>/', "`$1`", $html);

        return trim($html);
    }

    public static function getActivityMediaPreview($activityId)
    {
        return self::getMediaItems($activityId);
    }

    private static function getMediaItems($activityId)
    {
        $mediaPreviews = [];
        $mediaMetas = fluentCommunityApp('db')->table('bp_activity_meta')
            ->select(['meta_value', 'meta_key'])
            ->where('activity_id', $activityId)
            ->whereIn('meta_key', ['bp_media_ids', '_gif_raw_data'])
            ->get()
            ->keyBy('meta_key')
            ->toArray();

        if (!$mediaMetas) {
            return null;
        }

        if (!empty($mediaMetas['bp_media_ids']) && !empty($mediaMetas['bp_media_ids']->meta_value)) {
            $mediaMeta = $mediaMetas['bp_media_ids'];
            $mediaIds = explode(',', $mediaMeta->meta_value);

            $mediaItems = fluentCommunityApp('db')->table('bp_media')
                ->select(['attachment_id'])
                ->whereIn('id', $mediaIds)
                ->get()
                ->pluck('attachment_id')
                ->toArray();

            $mediaPosts = fluentCommunityApp('db')->table('posts')
                ->whereIn('id', $mediaItems)
                ->where('post_type', 'attachment')
                ->get();

            foreach ($mediaPosts as $mediaPost) {
                if (strpos($mediaPost->post_mime_type, 'image/') === false) {
                    continue;
                }
                $meta = (array)get_post_meta($mediaPost->ID, '_wp_attachment_metadata', true);
                $mediaPreviews[] = [
                    'media_id' => NULL,
                    'url'      => $mediaPost->guid,
                    'type'     => 'image',
                    'width'    => Arr::get($meta, 'width'),
                    'height'   => Arr::get($meta, 'height'),
                    'provider' => 'external'
                ];
            }
        } else if (!empty($mediaMetas['_gif_raw_data'])) {
            $giphyMeta = Utility::safeUnserialize($mediaMetas['_gif_raw_data']->meta_value);
            $giphyMedia = Arr::get($giphyMeta, 'images.downsized_medium', []);
            if (!$giphyMedia || empty($giphyMedia['url'])) {
                return null;
            }

            return [
                'media_preview' => array_filter([
                    'image'    => sanitize_url($giphyMedia['url']),
                    'type'     => 'image',
                    'provider' => 'giphy',
                    'height'   => (int)Arr::get($giphyMedia, 'height', 0),
                    'width'    => (int)Arr::get($giphyMedia, 'width', 0),
                ])
            ];
        }

        if (!$mediaPreviews) {
            return null;
        }

        if (count($mediaPreviews) == 1) {
            $media = $mediaPreviews[0];
            $media['image'] = $media['url'];
            unset($media['url']);
            return [
                'media_preview' => $media
            ];
        }

        return [
            'media_items' => $mediaPreviews
        ];
    }

    public static function isBuddyBoss()
    {
        return defined('BP_PLATFORM_VERSION');
    }

    private static function syncPostReactions($postsId, $feedId)
    {
        if (!self::isBuddyBoss()) {
            return;
        }

        // feed likes
        $reactions = fluentCommunityApp('db')->table('bb_user_reactions')
            ->select(['user_id', 'date_created', 'item_id'])
            ->where('item_id', $postsId)
            ->where('item_type', 'activity')
            ->get();

        $likesArray = [];
        foreach ($reactions as $reaction) {
            $likesArray[] = [
                'user_id'     => $reaction->user_id,
                'object_id'   => $feedId,
                'object_type' => 'feed',
                'type'        => 'like',
                'created_at'  => $reaction->date_created,
                'updated_at'  => $reaction->date_created
            ];
        }

        if ($likesArray) {
            Reaction::insert($likesArray);
        }
    }

    private static function syncCommentsReactions($commentIdMaps, $feedId)
    {
        if (!self::isBuddyBoss()) {
            return;
        }

        $bbCommentIds = array_keys($commentIdMaps);

        if (!$bbCommentIds) {
            return false;
        }

        $reactions = fluentCommunityApp('db')->table('bb_user_reactions')
            ->select(['user_id', 'date_created', 'item_id'])
            ->whereIn('item_id', $bbCommentIds)
            ->where('item_type', 'activity_comment')
            ->get();

        $likesArray = [];
        $likesCount = [];
        foreach ($reactions as $reaction) {
            $commentId = (int)Arr::get($commentIdMaps, $reaction->item_id);
            if ($commentId) {
                if (empty($likesCount[$commentId])) {
                    $likesCount[$commentId] = 0;
                }
                $likesCount[$commentId] = $likesCount[$commentId] + 1;
                $likesArray[] = [
                    'user_id'     => $reaction->user_id,
                    'object_id'   => $commentId,
                    'object_type' => 'comment',
                    'parent_id'   => $feedId,
                    'type'        => 'like',
                    'created_at'  => $reaction->date_created,
                    'updated_at'  => $reaction->date_created
                ];
            }
        }

        if ($likesArray) {
            Reaction::insert($likesArray);
            foreach ($likesCount as $commentId => $count) {
                Comment::where('id', $commentId)->update(['reactions_count' => $count]);
            }
        }
    }

    public static function syncUser(User $user)
    {
        $syncedXprofile = $user->syncXProfile();
        if (!$syncedXprofile) {
            return false;
        }

        $xprofile = $user->xprofile;
        // Let's sync the cover photo and avatar
        if (!$xprofile->hasCustomAvatar()) {
            $avatar = get_avatar_url($user->ID, 'full', true);

            $coverPhoto = bp_attachments_get_attachment(
                'url',
                array(
                    'object_dir' => 'members',
                    'item_id'    => $user->ID,
                )
            );

            $hasChange = false;
            if ($coverPhoto) {
                $path = self::getFilePathFromUrl($coverPhoto);
                if ($path) {
                    $media = self::createMediaFromPath($path, [
                        'object_source' => 'user_cover_photo',
                        'user_id'       => $user->ID
                    ]);
                    if ($media) {
                        $meta = $xprofile->meta;
                        $meta['cover_photo'] = $coverPhoto;
                        $xprofile->meta = $meta;
                        $hasChange = true;
                    }
                }
            }

            if ($avatar && !strpos($avatar, 'gravatar.com')) {
                $path = self::getFilePathFromUrl($avatar);
                if ($path) {
                    $media = self::createMediaFromPath($path, [
                        'object_source' => 'user_photo',
                        'user_id'       => $user->ID
                    ]);
                    if ($media) {
                        $avatar = $media->media_url;
                        $xprofile->avatar = $avatar;
                        $hasChange = true;
                    }
                }
            }

            if ($hasChange) {
                $xprofile->save();
            }
        }

        if (!self::isBuddyBoss()) {
            $favIds = get_user_meta($user->ID, 'bp_favorite_activities', true);
            if ($favIds) {
                $favFeedIds = fluentCommunityApp('db')->table('bp_activity_meta')
                    ->whereIn('activity_id', $favIds)
                    ->select(['meta_value'])
                    ->where('meta_key', '_fcom_feed_id')
                    ->get()
                    ->pluck('meta_value')
                    ->toArray();

                if ($favFeedIds) {
                    $favPosts = Feed::whereIn('id', $favFeedIds)->get();
                    foreach ($favPosts as $favPost) {
                        $exist = Reaction::where('user_id', $user->ID)
                            ->where('object_id', $favPost->id)
                            ->where('type', 'like')
                            ->objectType('feed')
                            ->exists();

                        if (!$exist) {
                            Reaction::create([
                                'user_id'     => get_current_user_id(),
                                'object_id'   => $favPost->id,
                                'type'        => 'like',
                                'object_type' => 'feed'
                            ]);

                            $favPost->reactions_count = (int)$favPost->reactions_count + 1;
                            $favPost->save();
                        }
                    }
                }
            }
        }

        return true;
    }

    public static function migrateGroupData($group, $force = true)
    {
        $exitMeta = fluentCommunityApp('db')->table('bp_groups_groupmeta')
            ->where('group_id', $group->id)
            ->where('meta_key', '_fcom_space_id')
            ->first();

        $existingSpace = null;
        if ($exitMeta) {
            $existingSpace = Space::find($exitMeta->meta_value);
            if ($existingSpace && !$force) {
                return $existingSpace;
            }
        }

        // Create a new space group
        $spaceGroup = null;
        if (!empty($group->space_menu_id)) {
            $spaceGroup = SpaceGroup::find($group->space_menu_id);
        }

        $serial = BaseSpace::when($spaceGroup, function ($q) use ($spaceGroup) {
                $q->where('parent_id', $spaceGroup->id);
            })->max('serial') + 1;

        $privacy = $group->status;

        if ($privacy == 'hidden') {
            $privacy = 'secret';
        } else if (!in_array($privacy, ['public', 'private'])) {
            $privacy = 'private';
        }

        $postBy = fluentCommunityApp('db')->table('bp_groups_groupmeta')
            ->where('group_id', $group->id)
            ->where('meta_key', 'activity_feed_status')
            ->first();

        $restrictedPostOnly = 'no';
        if ($postBy && $postBy->meta_value != 'members') {
            $restrictedPostOnly = 'yes';
        }

        $settings = [
            'restricted_post_only' => $restrictedPostOnly
        ];

        $documentStatus = fluentCommunityApp('db')->table('bp_groups_groupmeta')
            ->where('group_id', $group->id)
            ->where('meta_key', 'document_status')
            ->first();

        if ($documentStatus) {
            $settings = wp_parse_args($settings, [
                'document_library' => 'yes',
                'document_access'  => 'members_only',
                'document_upload'  => $documentStatus === 'mods' ? 'admin_only' : 'members_only'
            ]);
        }

        $groupData = [
            'title'       => sanitize_text_field($group->name),
            'slug'        => Utility::slugify($group->slug),
            'privacy'     => $privacy,
            'description' => wp_kses_post(wp_unslash($group->description)),
            'settings'    => $settings,
            'parent_id'   => $spaceGroup ? $spaceGroup->id : null,
            'serial'      => $serial ?: 1
        ];

        if ($existingSpace) {
            $existingSpace->fill($groupData);
            $existingSpace->save();
        } else {
            $exist = BaseSpace::where('slug', $groupData['slug'])
                ->exists();

            if ($exist) {
                $groupData['slug'] = $groupData['slug'] . '-' . time();
            }

            $existingSpace = Space::create($groupData);

            fluentCommunityApp('db')->table('bp_groups_groupmeta')
                ->insert([
                    'group_id'   => $group->id,
                    'meta_key'   => '_fcom_space_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
                    'meta_value' => $existingSpace->id // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
                ]);
        }

        $groupLogo = bp_core_fetch_avatar(
            array(
                'item_id'    => $group->id,
                'avatar_dir' => 'group-avatars',
                'object'     => 'group',
                'type'       => 'full',
                'html'       => false,
            )
        );
        if ($groupLogo) {
            $filePath = self::getFilePathFromUrl($groupLogo);
            if ($filePath) {
                $media = self::createMediaFromPath($filePath, [
                    'object_source' => 'space_logo',
                    'sub_object_id' => $existingSpace->id
                ]);
                if ($media) {
                    $existingSpace->logo = $media->media_url;
                }
            }
        }

        $group_cover_image = bp_attachments_get_attachment(
            'url',
            array(
                'object_dir' => 'groups',
                'item_id'    => $group->id,
            )
        );
        if ($group_cover_image) {
            if ($groupLogo) {
                $filePath = self::getFilePathFromUrl($group_cover_image);
                if ($filePath) {
                    $media = self::createMediaFromPath($filePath, [
                        'object_source' => 'space_cover_photo',
                        'sub_object_id' => $existingSpace->id
                    ]);
                    if ($media) {
                        $existingSpace->cover_photo = $media->media_url;
                    }
                }
            }
        }

        $existingSpace->save();

        return $existingSpace;
    }

    public static function deleteCurrentData()
    {
        // delete the folder in uploads folder fluent-community with php please
        $uploadDir = wp_upload_dir();
        $fcomDir = $uploadDir['basedir'] . '/fluent-community';
        if (is_dir($fcomDir)) {
            // delete the directory and all its content
            self::deleteDirectory($fcomDir);
        }

        // reset fluent community data
        \FluentCommunity\App\Models\Feed::truncate();
        \FluentCommunity\App\Models\Comment::truncate();
        \FluentCommunity\App\Models\Reaction::truncate();
        \FluentCommunity\App\Models\Media::truncate();
        \FluentCommunity\App\Models\Activity::truncate();
        \FluentCommunity\App\Models\XProfile::truncate();
        \FluentCommunity\App\Models\Space::where('type', 'community')->delete();

        // reset buddypress meta data
        fluentCommunityApp('db')->table('bp_groups_groupmeta')->where('meta_key', '_fcom_space_id')->delete();
        fluentCommunityApp('db')->table('bp_activity_meta')->where('meta_key', '_fcom_feed_id')->delete();

        delete_option('_bp_fcom_group_maps');
        delete_option('_bp_fcom_last_post_id');
        delete_option('_bp_fcom_last_user_id');
        delete_option('_bp_fcom_last_migrated_member_id');
    }

    public static function getFcomSpaceIdByGroupId($bbGroupId)
    {
        if (!$bbGroupId) {
            return null;
        }

        static $groupMaps;

        if (!$groupMaps) {
            $groupMaps = get_option('_bp_fcom_group_maps', []);
        }

        return isset($groupMaps[$bbGroupId]) ? $groupMaps[$bbGroupId] : null;

    }

    private static function deleteDirectory($dir)
    {
        // Include WordPress filesystem API
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        // Initialize the WP_Filesystem
        global $wp_filesystem;
        if (!WP_Filesystem()) {
            return false; // Filesystem initialization failed
        }

        // Sanitize the directory path
        $dir = trailingslashit($dir);

        // Check if directory exists
        if (!$wp_filesystem->is_dir($dir)) {
            return false; // Directory doesn't exist
        }

        // Delete directory and its contents recursively
        $result = $wp_filesystem->rmdir($dir, true);

        return $result;
    }

    private static function getFilePathFromUrl($url)
    {
        // Parse the URL to get its components
        $parsed_url = wp_parse_url($url);
        // Get the path from the URL
        $url_path = isset($parsed_url['path']) ? $parsed_url['path'] : '';

        // Remove the site URL part to get the relative path

        // handle http / https both
        $site_url = site_url(''); // e.g., https://example.com
        if (strpos($url, $site_url) === false) {
            $site_url = str_replace('https://', 'http://', $site_url);
        }
        if (strpos($url, $site_url) === false) {
            $site_url = str_replace('http://', 'https://', $site_url);
        }

        $relative_path = str_replace($site_url, '', $url);

        // Combine with ABSPATH to get the full file path
        $file_path = ABSPATH . ltrim($relative_path, '/');

        // Check if the file exists
        if (file_exists($file_path)) {
            return $file_path;
        }

        return false; // Return false if the file doesn't exist
    }

    public static function createMediaFromPath($path, $mediaArgs = [])
    {
        $defaults = [
            'media_key'     => md5(wp_generate_uuid4()),
            'is_active'     => 1,
            'driver'        => 'local',
            'media_path'    => '',
            'media_url'     => '',
            'settings'      => [],
            'object_source' => ''
        ];

        $mediaArgs = wp_parse_args($mediaArgs, $defaults);


        if (empty($mediaArgs['object_source'])) {
            return null;
        }

        $fileName = basename($path);

        $mediaArgs['settings']['original_name'] = $fileName;

        // copy the file to uploads/fluent-community
        $uploadDir = wp_upload_dir();
        $fcomDir = $uploadDir['basedir'] . '/fluent-community';
        if (!is_dir($fcomDir)) {
            wp_mkdir_p($fcomDir);
        }

        $newFilePath = $fcomDir . '/fluentcom-' . md5(wp_generate_uuid4()) . '-fluentcom-' . $fileName;


        if (!copy($path, $newFilePath)) {
            return null;
        }

        $fileUrl = str_replace($fcomDir, wp_upload_dir()['baseurl'] . '/fluent-community', $newFilePath);

        $mediaArgs['media_path'] = $newFilePath;
        $mediaArgs['media_url'] = $fileUrl;

        $mediaArgs['media_type '] = mime_content_type($newFilePath);

        $imageSizes = wp_getimagesize($newFilePath);


        if ($imageSizes && count($imageSizes) >= 2) {
            $mediaArgs['settings']['width'] = $imageSizes[0];
            $mediaArgs['settings']['height'] = $imageSizes[1];
        }

        return Media::create($mediaArgs);
    }

    public static function recalculateUserPoints($userId)
    {
        // SUM of all the points of the Comment Model
        $commentPoints = Comment::where('user_id', $userId)
            ->sum('reactions_count');

        $postsPoints = Feed::where('user_id', $userId)
            ->sum('reactions_count');

        return $commentPoints + $postsPoints;
    }

    public static function maybeEnableFollowersModule()
    {
        if (!defined('FLUENT_COMMUNITY_PRO')) {
            return;
        }

        $isEnabled = Helper::isFeatureEnabled('followers_module');
        if ($isEnabled) {
            return;
        }

        if (fluentCommunityApp('db')->table('bp_friends')->exists()) {
            \FluentCommunityPro\App\Modules\Followers\FollowerHelper::updateSettings(['is_enabled' => 'yes']);
        }
    }
}
