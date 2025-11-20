<?php

namespace FluentCommunity\Modules\Migrations\Helpers;

use FluentCommunity\App\Models\Comment;
use FluentCommunity\App\Models\Feed;
use FluentCommunity\App\Models\Media;
use FluentCommunity\App\Models\Reaction;
use FluentCommunity\App\Services\FeedsHelper;
use FluentCommunity\Framework\Support\Arr;

class PostMigrator
{

    private $metas = [];

    private $attachments = [];

    private $mediaItems = [];

    private $childPostIds = [];

    private $createdFeedId = null;

    private $spaceId = null;

    private $post = null;

    public function __construct($post, $metaItems = null)
    {
        if (is_numeric($post)) {
            $post = fluentCommunityApp('db')->table('bp_activity')->where('id', $post)->first();
        }

        $this->post = $post;
        $this->spaceId = BPMigratorHelper::getFcomSpaceIdByGroupId($post->item_id);

        if (!$metaItems) {
            $postMetas = fluentCommunityApp('db')->table('bp_activity_meta')
                ->where('activity_id', $post->id)
                ->get();
            foreach ($postMetas as $meta) {
                $this->metas[$meta->meta_key] = $meta->meta_value;
            }
        } else {
            $this->metas = $metaItems;
        }
    }

    public function migrate()
    {
        $documentIds = Arr::get($this->metas, 'bp_document_ids', '');
        $mediaIds = Arr::get($this->metas, 'bp_media_ids', '');
        $videoIds = Arr::get($this->metas, 'bp_video_ids', '');

        if ($videoIds) {
            if ($mediaIds) {
                $mediaIds .= ',';
            }
            $mediaIds .= $videoIds;
        }

        if ($documentIds) {
            // It's a document post
            $documentIdsArray = array_filter(array_filter(explode(',', $documentIds)));
            if ($documentIdsArray) {
                $documents = fluentCommunityApp('db')->table('bp_document')
                    ->whereIn('id', $documentIdsArray)
                    ->get();
                foreach ($documents as $document) {
                    $file = get_attached_file($document->attachment_id, true);
                    if (!$file || !file_exists($file)) {
                        continue;
                    }
                    $this->childPostIds[] = $document->activity_id;

                    $this->attachments[] = [
                        'path'  => $file,
                        'title' => $document->title
                    ];
                }
            }
        }

        if ($mediaIds) {
            $mediaIdsArray = array_filter(array_filter(explode(',', $mediaIds)));

            if ($mediaIdsArray) {
                $medias = fluentCommunityApp('db')->table('bp_media')
                    ->whereIn('id', $mediaIdsArray)
                    ->get();
                foreach ($medias as $media) {
                    $file = get_attached_file($media->attachment_id, true);

                    if (!$file || !file_exists($file)) {
                        continue;
                    }
                    $mediaType = mime_content_type($file);
                    $mediaType = explode('/', $mediaType)[0];


                    // check if the image is a valid image
                    if (!in_array($mediaType, ['image', 'video'])) {
                        continue;
                    }

                    $image_data = [];

                    if ($mediaType == 'image') {
                        $image_data = wp_get_attachment_image_src($media->attachment_id, 'full');
                        if (!$image_data) {
                            $image_data = [];
                        }
                    }

                    $this->childPostIds[] = $media->activity_id;

                    $this->mediaItems[] = [
                        'path'       => $file,
                        'title'      => $media->title,
                        'media_type' => $mediaType,
                        'width'      => Arr::get($image_data, 1, ''),
                        'height'     => Arr::get($image_data, 2, '')
                    ];
                }
            }
        }

        $content = BPMigratorHelper::cleanUpContent($this->post->content);


        $linkPreviewData = [];
        $linkPreview = maybe_unserialize(Arr::get($this->metas, '_link_preview_data', ''));

        if ($linkPreview && !empty($linkPreview['url'])) {
            $linkPreviewData = array_filter([
                'title'       => $linkPreview['title'] ?? '',
                'description' => $linkPreview['description'] ?? '',
                'url'         => $linkPreview['url'],
                'type'        => 'meta_data',
                'image'       => $linkPreview['image_url'] ?? ''
            ]);

            if (!$content) {
                $content = '[' . $linkPreviewData['url'] . '](' . $linkPreviewData['url'] . ')';
            }
        }

        if (!$content && !$this->attachments && !$this->mediaItems) {
            // error_log('Missing Post: ' . $this->post->id);
            return false;
        }

        // let's create the posts
        $feedData = [
            'user_id'          => $this->post->user_id,
            'title'            => $this->post->post_title ?? '',
            'message'          => BPMigratorHelper::toMarkdown($content),
            'message_rendered' => FeedsHelper::mdToHtml($content),
            'type'             => 'text',
            'content_type'     => 'text',
            'privacy'          => 'public',
            'status'           => 'published',
            'space_id'         => $this->spaceId
        ];

        [$feedData, $media] = FeedsHelper::processFeedMetaData($feedData, []);

        $mediaMeta = BPMigratorHelper::getActivityMediaPreview($this->post->id);

        if ($mediaMeta) {
            $feedData['meta'] = $mediaMeta;
        }

        if ($this->attachments) {
            $feedData['content_type'] = 'document';
        }

        $feedData['meta']['bb_activity_id'] = $this->post->id;

        $feed = new Feed();
        $feed->fill($feedData);
        $feed->created_at = $this->post->date_recorded;
        $feed->updated_at = $this->post->date_recorded;
        $feed->save();
        $this->createdFeedId = $feed->id;
        if (!$this->attachments && !$this->mediaItems && $linkPreviewData) {
            $feedmeta = $feed->meta ? $feed->meta : [];
            if (empty($feedmeta['media_preview'])) {
                $feedData['meta']['media_preview'] = $linkPreviewData;
                if (empty($linkPreview['image']) && !empty($linkPreview['attachment_id'])) {
                    $attachmentImagePath = get_attached_file($linkPreview['attachment_id']);
                    if ($attachmentImagePath && file_exists($attachmentImagePath)) {
                        $linkPreviewMedia = BPMigratorHelper::createMediaFromPath($attachmentImagePath, [
                            'object_source' => 'feed',
                            'user_id'       => $this->post->user_id,
                            'sub_object_id' => $feed->id,
                        ]);

                        if ($linkPreviewMedia) {
                            $linkPreviewData['image'] = $linkPreviewMedia->media_url;
                        }
                    }
                }

                $feedmeta['media_preview'] = $linkPreviewData;
                $feed->meta = $feedmeta;
                $feed->save();
            }
        }

        $feed = $this->maybeProcessDocuments($feed);
        $feed = $this->maybeProcessMediaItems($feed);

        $this->syncPostReactions();

        // let's migrate the comments
        $commentIdMaps = $this->syncComments($this->post->id, $feed);

        $this->syncCommentsReactions($commentIdMaps);

        $feed = $feed->recountStats();

        return $feed;
    }

    private function isBuddyBoss()
    {
        return defined('BP_PLATFORM_VERSION');
    }

    private function syncComments()
    {

        $itemIds = array_filter(array_merge([$this->post->id], $this->childPostIds));

        // Let's manage the comments
        $comments = fluentCommunityApp('db')->table('bp_activity')
            ->where('type', 'activity_comment')
            ->whereIn('item_id', $itemIds)
            ->orderBy('id', 'ASC')
            ->get();

        $comments = $this->buildCommentsTree($comments);
        $commentMaps = [];

        foreach ($comments as $comment) {
            $newComment = $this->insertBBComment($comment);
            $commentMaps[$comment['id']] = $newComment->id;
            if ($comment['children']) {
                foreach ($comment['children'] as $child) {
                    $childComment = $this->insertBBComment($child, $newComment->id);
                    $commentMaps[$child['id']] = $childComment->id;
                }
            }
        }

        return $commentMaps;
    }

    private function syncPostReactions()
    {
        if (!$this->isBuddyBoss()) {
            return;
        }

        $allItemIds = array_filter(array_merge([$this->post->id], $this->childPostIds));

        // feed likes
        $reactions = fluentCommunityApp('db')->table('bb_user_reactions')
            ->select(['user_id', 'date_created', 'item_id'])
            ->whereIn('item_id', $allItemIds)
            ->where('item_type', 'activity')
            ->get();

        $likesArray = [];
        foreach ($reactions as $reaction) {
            $likesArray[] = [
                'user_id'     => $reaction->user_id,
                'object_id'   => $this->createdFeedId,
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

    private function syncCommentsReactions($commentIdMaps)
    {
        if (!$this->isBuddyBoss()) {
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
                    'parent_id'   => $this->createdFeedId,
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

    private function buildCommentsTree($comments)
    {
        $commentTree = [];
        $commentMap = [];

        // First pass: create a map of all comments
        foreach ($comments as $comment) {
            $commentMap[$comment->id] = [
                'id'            => $comment->id,
                'content'       => BPMigratorHelper::cleanUpContent($comment->content),
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
                $childComments = Arr::get($child, 'children', []);
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

    private function insertBBComment($comment, $parentId = null)
    {
        $content = BPMigratorHelper::toMarkdown($comment['content']);

        $comemntData = [
            'user_id'          => $comment['user_id'],
            'post_id'          => $this->createdFeedId,
            'message'          => $content,
            'message_rendered' => FeedsHelper::mdToHtml($content),
            'type'             => 'comment'
        ];

        $media = BPMigratorHelper::getActivityMediaPreview($comment['id']);

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

    private function maybeProcessDocuments($feed)
    {
        if (!$this->attachments) {
            return $feed;
        }

        $fromattedAttachments = [];

        foreach ($this->attachments as $attachment) {
            $path = $attachment['path'];
            $fileName = basename($path);

            $orginalName = $fileName;

            // get the path folder from path
            $newFileName = 'fluentcom-' . md5(wp_generate_uuid4()) . '-fluentcom-' . $fileName;

            // copy the existing file to uploads/fluentcom
            $uploadDir = wp_upload_dir();
            $newFolder = $uploadDir['basedir'] . '/fluent-community/space_documents/';
            if (!file_exists($newFolder)) {
                wp_mkdir_p($newFolder);
            }

            copy($path, $newFolder . $newFileName);
            $newPath = $newFolder . $newFileName;
            $newUrl = $uploadDir['baseurl'] . '/fluent-community/space_documents/' . $newFileName;

            // Copy that to
            $mediaType = mime_content_type($newPath);

            $mediaData = [
                'object_source' => 'space_document',
                'media_key'     => md5($attachment['path'] . '_' . time() . '_' . wp_rand(1000, 9999)),
                'user_id'       => $feed->user_id,
                'feed_id'       => $feed->id,
                'is_active'     => 1,
                'media_type'    => $mediaType,
                'driver'        => 'local',
                'media_path'    => $newPath,
                'media_url'     => $newUrl,
                'settings'      => [
                    'original_name' => $orginalName
                ]
            ];

            $newMedia = new Media();

            $newMedia->fill($mediaData);
            $newMedia->created_at = $feed->created_at;
            $newMedia->updated_at = $feed->created_at;
            $newMedia->save();
            $fromattedAttachments[] = [
                'id'        => $newMedia->id,
                'url'       => $newMedia->getPrivateDownloadUrl(),
                'media_key' => $newMedia->media_key,
                'title'     => $orginalName,
                'type'      => $mediaType
            ];
        }

        $feedMeta = $feed->meta ? $feed->meta : [];
        $feedMeta['document_lists'] = $fromattedAttachments;

        $feed->meta = $feedMeta;
        $feed->save();

        return $feed;
    }

    private function maybeProcessMediaItems($feed)
    {
        if (!$this->mediaItems) {
            return $feed;
        }

        $fromattedAttachments = [];

        foreach ($this->mediaItems as $mediaItem) {
            $path = $mediaItem['path'];

            $fileName = basename($path);

            $orginalName = $fileName;

            // get the path folder from path
            $newFileName = 'fluentcom-' . md5(wp_generate_uuid4()) . '-fluentcom-' . $fileName;

            // copy the existing file to uploads/fluentcom
            $uploadDir = wp_upload_dir();
            $newFolder = $uploadDir['basedir'] . '/fluent-community/';
            if (!file_exists($newFolder)) {
                wp_mkdir_p($newFolder);
            }

            copy($path, $newFolder . $newFileName);
            $newPath = $newFolder . $newFileName;
            $newUrl = $uploadDir['baseurl'] . '/fluent-community/' . $newFileName;


            if ($mediaItem['media_type'] === 'image') {
                // we should optimize the image!
                $width = $mediaItem['width'] ?? 0;
                if ($width > 1600) {
                    $resizedPath = $this->resizeImage($newPath);
                    if ($resizedPath !== $newPath) {
                        $newPath = $resizedPath;
                        $newUrl = str_replace($uploadDir['basedir'], $uploadDir['baseurl'], $newPath);
                        $imageData = getimagesize($newPath);
                        if ($imageData && is_array($imageData) && isset($imageData[0]) && isset($imageData[1])) {
                            $mediaItem['width'] = (int)$imageData[0];
                            $mediaItem['height'] = (int)$imageData[1];
                            if (defined('WP_CLI')) {
                                \WP_CLI::line('Resized image: ' . $width . ' to ' . $mediaItem['width'] . 'x' . $mediaItem['height']);
                            }
                        }
                    }
                }
            }

            // Copy that to

            // get width and height of the image
            $mediaType = mime_content_type($newPath);

            $settings = array_filter([
                'original_name' => $orginalName,
                'width'         => $mediaItem['width'] ?? null,
                'height'        => $mediaItem['height'] ?? null
            ]);

            $mediaData = [
                'object_source' => 'feed',
                'media_key'     => md5($mediaItem['path'] . '_' . time() . '_' . wp_rand(1000, 9999)),
                'user_id'       => $feed->user_id,
                'feed_id'       => $feed->id,
                'is_active'     => 1,
                'media_type'    => $mediaType,
                'driver'        => 'local',
                'media_path'    => $newPath,
                'media_url'     => $newUrl,
                'settings'      => $settings
            ];

            $newMedia = new Media();

            $newMedia->fill($mediaData);
            $newMedia->created_at = $feed->created_at;
            $newMedia->updated_at = $feed->created_at;
            $newMedia->save();
            $fromattedAttachments[] = array_filter([
                'media_id' => $newMedia->id,
                'url'      => $newUrl,
                'title'    => $mediaItem['title'] ?? $orginalName,
                'type'     => $mediaItem['media_type'],
                'width'    => $mediaItem['width'] ?? null,
                'height'   => $mediaItem['height'] ?? null,
                'provider' => 'uploader'
            ]);
        }

        if (!$fromattedAttachments) {
            return $feed;
        }

        $feedMeta = $feed->meta ? $feed->meta : [];
        $feedMeta['media_items'] = $fromattedAttachments;

        $feed->meta = $feedMeta;
        $feed->save();

        return $feed;
    }

    private function resizeImage($path)
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if (!in_array(strtolower($extension), ['jpg', 'jpeg', 'png'])) {
            return $path;
        }

        $maxWidth = 1600;
        $editor = wp_get_image_editor($path);

        if (is_wp_error($editor) || $editor->get_size()['width'] < $maxWidth) {
            return $path;
        }

        $imageExtensions = ['jpg', 'jpeg', 'png'];


        $newFilePath = $path;

        $imageExtensions = array_map(function ($ext) {
            return '.' . $ext;
        }, $imageExtensions);

        $newFilePath = str_replace($imageExtensions, '.webp', $newFilePath);

        $editor->resize($maxWidth, null, false);
        $editor->set_quality(90);
        $result = $editor->save($newFilePath, 'image/webp');
        if (is_wp_error($result)) {
            return $path;
        }

        wp_delete_file($path);

        return $newFilePath;
    }

}
