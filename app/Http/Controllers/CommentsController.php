<?php

namespace FluentCommunity\App\Http\Controllers;

use FluentCommunity\App\Models\Media;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Services\CustomSanitizer;
use FluentCommunity\App\Services\FeedsHelper;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\App\Services\ProfileHelper;
use FluentCommunity\Framework\Http\Request\Request;
use FluentCommunity\App\Models\Comment;
use FluentCommunity\App\Models\Feed;
use FluentCommunity\App\Models\Reaction;
use FluentCommunity\Framework\Support\Arr;

class CommentsController extends Controller
{
    public function getComments(Request $request, $feed_id)
    {
        $feed = Feed::withoutGlobalScopes()->findOrFail($feed_id);
        $canViewComments = apply_filters('fluent_community/can_view_comments_' . $feed->type, true, $feed);

        if (!$canViewComments) {
            return [
                'comments' => []
            ];
        }

        $comments = Comment::where('post_id', $feed->id)
            ->byContentModerationAccessStatus($this->getUser())
            ->orderBy('created_at', 'asc')
            ->with([
                'xprofile' => function ($q) {
                    $q->select(ProfileHelper::getXProfilePublicFields());
                }
            ])
            ->whereHas('xprofile', function ($q) {
                $q->where('status', 'active');
            })
            ->get();

        $comments = apply_filters('fluent_community/comments_query_response', $comments, $request->all());

        $userId = $this->getUserId();

        if ($userId) {
            $likedIds = FeedsHelper::getLikedIdsByUserFeedId($feed->id, get_current_user_id());

            if ($likedIds) {
                $comments->each(function ($comment) use ($likedIds) {
                    if (in_array($comment->id, $likedIds)) {
                        $comment->liked = 1;
                    }
                });
            }
        }

        return [
            'comments' => $comments
        ];
    }

    public function store(Request $request, $feedId)
    {
        $user = $this->getUser(true);
        do_action('fluent_community/check_rate_limit/create_comment', $user);

        $text = $this->validateCommentText($request->all());
        $feed = Feed::withoutGlobalScopes()->findOrFail($feedId);

        if ($feed->status != 'published') {
            return $this->sendError([
                'message' => __('This post is not published yet', 'fluent-community')
            ]);
        }

        $this->verifyCreateCommentPermission($feed);

        $requestData = $request->all();

        // Check for duplicate
        $exist = Comment::where('user_id', get_current_user_id())
            ->where('message', $text)
            ->where('post_id', $feed->id)
            ->first();

        if ($exist) {
            return $this->sendError([
                'message' => __('No duplicate comment please!', 'fluent-community')
            ]);
        }

        $mentions = FeedsHelper::getMentions($text, $feed->space_id, true);
        $commentHtml = $this->generateCommentHtml($text, $mentions);
        $commentData = $this->prepareCommentData($feed->id, $text, $commentHtml);

        if ($parentId = $request->get('parent_id')) {
            $parentId = (int)$parentId;
            $parentComment = Comment::where('id', $parentId)
                ->where('post_id', $feed->id)
                ->first();

            if (!$parentComment) {
                return $this->sendError([
                    'message' => __('Invalid parent comment', 'fluent-community')
                ]);
            }

            $commentData['parent_id'] = $parentId;
        }

        [$commentData, $mediaItems] = $this->prepareCommentMedia($commentData, $requestData);

        $commentData['is_admin'] = $user->hasSpacePermission('community_moderator', $feed->space);

        if ($mentionUserIds = Arr::get($mentions, 'user_ids', [])) {
            $commentData['meta']['mentioned_user_ids'] = $mentionUserIds;
        }

        do_action('fluent_community/before_comment_create', $commentData, $feed);

        $commentData = apply_filters('fluent_community/comment/comment_data', $commentData, $feed);

        $comment = Comment::create($commentData);

        $feed->comments_count = $feed->comments_count + 1;
        $feed->save();

        if ($mediaItems) {
            foreach ($mediaItems as $mediaItem) {
                $mediaItem->fill([
                    'is_active'     => 1,
                    'feed_id'       => $feed->id,
                    'object_source' => 'comment',
                    'sub_object_id' => $comment->id
                ]);
                $mediaItem->save();
            }
        }

        $this->loadCommentRelations($comment);

        if ($comment->status != 'published') {
            do_action('fluent_community/comment/new_comment_' . $comment->status, $comment, $feed);
            /* translators: %$s is replaced by the status of the comment */
            $message = sprintf(__('Your comment has been marked as %s', 'fluent-community'), $comment->status);
            return [
                'comment' => $comment,
                'message' => $message
            ];
        }

        do_action('fluent_community/comment_added_' . $feed->type, $comment, $feed);
        do_action('fluent_community/comment_added', $comment, $feed, Arr::get($mentions, 'users', []));

        return [
            'comment' => $comment,
            'message' => __('Comment has been added', 'fluent-community'),
        ];
    }

    public function update(Request $request, $feedId, $commentId)
    {
        $text = $this->validateCommentText($request->all());
        $feed = Feed::withoutGlobalScopes()->findOrFail($feedId);
        $this->verifySpacePermission($feed);

        $requestData = $request->all();
        $comment = Comment::findOrFail($commentId);
        $user = $this->getUser(true);

        $requestData['is_admin'] = $user->hasPermissionOrInCurrentSpace('community_moderator', $feed->space);

        if ($comment->user_id != get_current_user_id() && !$user->can('edit_any_comment', $feed->space)) {
            return $this->sendError([
                'message' => __('You are not allowed to edit this comment', 'fluent-community')
            ]);
        }

        $mentions = FeedsHelper::getMentions($text, $feed->space_id);

        $commentHtml = $this->generateCommentHtml($text, $mentions);

        $commentData = $this->prepareCommentData($feed->id, $text, $commentHtml);

        [$commentData, $mediaItems] = $this->prepareCommentMedia($commentData, $requestData, $comment);

        $commentData = apply_filters('fluent_community/comment/update_comment_data', $commentData, $feed, $requestData, $comment);

        $comment->fill($commentData);

        $dirty = $comment->getDirty();

        if ($dirty) {
            $comment->save();
        }

        if ($mediaItems) {
            $mediaIds = [];
            foreach ($mediaItems as $media) {
                $media->fill([
                    'is_active'     => 1,
                    'feed_id'       => $feed->id,
                    'object_source' => 'comment',
                    'sub_object_id' => $comment->id
                ]);
                $media->save();
                $mediaIds[] = $media->id;
            }

            // remove other media
            $otherMedias = Media::where('object_source', 'comment')
                ->when($mediaIds, function ($q) use ($mediaIds) {
                    $q->whereNotIn('id', $mediaIds);
                })
                ->where('sub_object_id', $comment->id)
                ->get();

            if (!$otherMedias->isEmpty()) {
                do_action('fluent_community/comment/media_deleted', $otherMedias);
            }
        } else {
            // remove other media
            $otherMedias = Media::where('object_source', 'comment')
                ->where('sub_object_id', $comment->id)
                ->get();

            if (!$otherMedias->isEmpty()) {
                do_action('fluent_community/comment/media_deleted', $otherMedias);
            }
        }

        $this->loadCommentRelations($comment);

        if ($dirty) {
            do_action('fluent_community/comment_updated', $comment, $feed);
            do_action('fluent_community/comment_updated_' . $feed->type, $comment, $feed);
        }

        return [
            'comment' => $comment,
            'message' => __('Comment has been updated', 'fluent-community'),
        ];
    }

    private function prepareCommentMedia($commentData, $requestData, $exisitngComment = null)
    {
        $mediaImages = Arr::get($requestData, 'media_images', []);

        if ($mediaImages) {
            if ($exisitngComment) {
                $mediaItems = [];
                $mediaData = [];
                foreach ($mediaImages as $mediaImage) {
                    $id = Arr::get($mediaImage, 'media_id');
                    if ($id) {
                        $media = Media::where('sub_object_id', $exisitngComment->id)
                            ->where('object_source', 'comment')
                            ->find($id);
                    } else {
                        $media = Helper::getMediaFromUrl($mediaImage);
                    }

                    if ($media) {
                        $mediaItems[] = $media;
                        $mediaData[] = [
                            'media_id' => $media->id,
                            'url'      => $media->public_url,
                            'type'     => 'image',
                            'width'    => Arr::get($media->settings, 'width'),
                            'height'   => Arr::get($media->settings, 'height'),
                            'provider' => Arr::get($media->settings, 'provider', 'uploader')
                        ];
                    }
                }
                $commentData['meta']['media_items'] = $mediaData;
                return [$commentData, $mediaItems];
            } else {
                $uploadedImages = Helper::getMediaByProvider($mediaImages);
                if ($uploadedImages) {
                    $mediaItems = Helper::getMediaItemsFromUrl($uploadedImages);
                    if ($mediaItems) {
                        $mediaPreviews = [];
                        foreach ($mediaItems as $mediaItem) {
                            $mediaData = [
                                'media_id' => $mediaItem->id,
                                'url'      => $mediaItem->public_url,
                                'type'     => 'image',
                                'width'    => Arr::get($mediaItem->settings, 'width'),
                                'height'   => Arr::get($mediaItem->settings, 'height'),
                                'provider' => Arr::get($mediaItem->settings, 'provider', 'uploader')
                            ];

                            $mediaPreviews[] = array_filter($mediaData);
                        }
                        $commentData['meta']['media_items'] = $mediaPreviews;
                        return [$commentData, $mediaItems];
                    }
                }
            }
        }

        if (empty($requestData['meta']['media_preview']['image'])) {
            return [$commentData, null];
        }

        if ($exisitngComment) {
            $image = sanitize_url(Arr::get($requestData, 'meta.media_preview.image', ''));
            $existingMedia = Media::where('media_url', $image)
                ->where('object_source', 'comment')
                ->where('sub_object_id', $exisitngComment->id)
                ->first();

            if ($existingMedia) {
                $commentData['meta'] = $exisitngComment->meta;
                return [$commentData, $existingMedia];
            }
        }

        $commentData['meta']['media_preview'] = array_filter([
            'image'    => sanitize_url(Arr::get($requestData, 'meta.media_preview.image', '')),
            'type'     => Arr::get($requestData, 'meta.media_preview.type', 'image'),
            'provider' => Arr::get($requestData, 'meta.media_preview.provider', ''),
            'height'   => Arr::get($requestData, 'meta.media_preview.height', 0),
            'width'    => Arr::get($requestData, 'meta.media_preview.width', 0),
        ]);

        return [$commentData, null];
    }

    private function validateCommentText($data)
    {
        $text = trim(Arr::get($data, 'comment'));
        $text = CustomSanitizer::unslashMarkdown($text);

        $hasMedia = Arr::get($data, 'media_images', []) || Arr::get($data, 'meta.media_preview.image', false);

        if (!$text && !$hasMedia) {
            throw new \Exception(esc_html__('Please provide your reply text', 'fluent-community'), 422);
        }

        $maxCommentLength = apply_filters('fluent_community/max_comment_char_length', 10000);
        if ($text && strlen($text) > $maxCommentLength) {
            throw new \Exception(esc_html__('Comment text is too long', 'fluent-community'), 422);
        }

        return $text;
    }

    private function verifyCreateCommentPermission($feed)
    {
        if (Arr::get($feed->meta, 'comments_disabled') === 'yes') {
            throw new \Exception(esc_html__('Comments are disabled for this post', 'fluent-community'));
        }

        $this->verifySpacePermission($feed);
    }

    private function verifySpacePermission($feed)
    {

        if ($feed->space_id && $feed->space) {
            $user = $this->getUser(true);
            $user->verifySpacePermission('can_comment', $feed->space);

            if ($feed->space->type == 'course' && Arr::get($feed->space->settings, 'disable_comments') === 'yes') {
                throw new \Exception(esc_html__('Comments are disabled for this course', 'fluent-community'));
            }
        }
    }

    private function generateCommentHtml($text, $mentions)
    {
        $htmlText = $mentions ? $mentions['text'] : $text;
        return wp_kses_post(FeedsHelper::mdToHtml($htmlText));
    }

    private function prepareCommentData($feedId, $text, $commentHtml)
    {
        return [
            'post_id'          => $feedId,
            'message'          => $text,
            'message_rendered' => $commentHtml,
            'type'             => 'comment',
            'meta'             => [],
        ];
    }

    private function loadCommentRelations($comment)
    {
        $comment->load('media');
        $comment->load([
            'xprofile' => function ($q) {
                $q->select(ProfileHelper::getXProfilePublicFields());
            }
        ]);
    }

    public function addOrRemovePostReact(Request $request, $feed_id)
    {
        $feed = Feed::withoutGlobalScopes()->findOrFail($feed_id);
        $type = $request->get('react_type', 'like');
        $willRemove = $request->get('remove');

        if ($feed->status != 'published') {
            return $this->sendError([
                'message' => __('This post is not published yet', 'fluent-community')
            ]);
        }

        $userId = get_current_user_id();
        if ($userId === $feed->user_id && apply_filters('fluent_community/disable_self_post_react', false, $feed)) {
            return $this->sendError([
                'message' => __('You cannot react to your own post', 'fluent-community')
            ]);
        }

        $react = Reaction::where('user_id', $userId)
            ->where('object_id', $feed->id)
            ->where('type', $type)
            ->objectType('feed')
            ->first();

        if ($willRemove) {
            if ($react) {
                $react->delete();
                if ($type == 'like') {
                    $feed->reactions_count = $feed->reactions_count - 1;
                    $feed->timestamps = false; // Don't update the updated_at timestamp
                    $feed->save();
                }
            }

            return [
                'message'   => __('Reaction has been removed', 'fluent-community'),
                'new_count' => $feed->reactions_count
            ];
        }

        if ($react) {
            return [
                'message'   => __('You have already reacted to this post', 'fluent-community'),
                'new_count' => $feed->reactions_count
            ];
        }

        $react = Reaction::create([
            'user_id'     => get_current_user_id(),
            'object_id'   => $feed->id,
            'type'        => $type,
            'object_type' => 'feed'
        ]);

        if ($type == 'like') {
            $feed->reactions_count = $feed->reactions_count + 1;
            $feed->timestamps = false; // Don't update the updated_at timestamp
            $feed->save();

            $react->load('xprofile');
            do_action('fluent_community/feed/react_added', $react, $feed);
        }

        return [
            'message'   => __('Reaction has been added', 'fluent-community'),
            'new_count' => $feed->reactions_count
        ];
    }

    public function deleteComment(Request $request, $feedId, $commentId)
    {
        $feed = Feed::withoutGlobalScopes()->findOrFail($feedId);
        $comment = Comment::findOrFail($commentId);

        if ($comment->post_id != $feed->id) {
            return $this->sendError([
                'message' => __('Invalid comment', 'fluent-community')
            ]);
        }

        $user = User::find(get_current_user_id());
        if ($comment->user_id != get_current_user_id() && !$user->can('delete_any_comment', $feed->space)) {
            return $this->sendError([
                'message' => __('You are not allowed to delete this comment', 'fluent-community')
            ]);
        }

        do_action('fluent_community/before_comment_delete', $comment);

        if ($comment->media) {
            do_action('fluent_community/comment/media_deleted', $comment->media);
        }

        $comment->delete();

        $feed->comments_count = Comment::where('post_id', $feed->id)->count();
        $feed->timestamps = false; // Don't update the updated_at timestamp
        $feed->save();

        do_action('fluent_community/comment_deleted_' . $feed->type, $commentId, $feed);
        do_action('fluent_community/comment_deleted', $commentId, $feed);

        return [
            'message' => __('Selected comment has been deleted', 'fluent-community')
        ];
    }

    public function toggleReaction(Request $request, $feedId, $commentId)
    {
        $feed = Feed::withoutGlobalScopes()->findOrFail($feedId);
        $comment = Comment::findOrFail($commentId);

        if ($comment->post_id != $feed->id) {
            return $this->sendError([
                'message' => __('Invalid comment', 'fluent-community')
            ]);
        }

        $user = User::findOrFail(get_current_user_id());

        if ($feed->space_id) {
            $user->verifySpacePermission('registered', $feed->space);
        }

        $userId = get_current_user_id();
        if ($userId === $comment->user_id && apply_filters('fluent_community/disable_self_comment_react', false, $feed)) {
            return $this->sendError([
                'message' => __('You cannot react to your own comment', 'fluent-community')
            ]);
        }

        $reactionState = !!$request->get('state', false);

        if ($reactionState) {
            // add or update the reaction
            $reaction = Reaction::firstOrCreate([
                'user_id'     => get_current_user_id(),
                'object_id'   => $comment->id,
                'object_type' => 'comment',
                'parent_id'   => $feed->id
            ]);

            if ($reaction->wasRecentlyCreated) {
                $comment->reactions_count = $comment->reactions_count + 1;
                $comment->save();
            }
        } else {
            // remove the reaction
            $deleted = Reaction::where('user_id', get_current_user_id())
                ->where('object_id', $comment->id)
                ->where('object_type', 'comment')
                ->delete();

            if ($deleted) {
                $comment->reactions_count = $comment->reactions_count - 1;
                $comment->save();
            }
        }

        return [
            'message'         => __('Reaction has been toggled', 'fluent-community'),
            'reactions_count' => $comment->reactions_count,
            'liked'           => $reactionState
        ];
    }

    public function show(Request $request, $id)
    {

        $testComment = Comment::query()->findOrFail($id);

        $comment = Comment::byContentModerationAccessStatus($this->getUser(), $testComment->space)
            ->with([
                'xprofile' => function ($q) {
                    return $q->select(ProfileHelper::getXProfilePublicFields());
                }
            ])->findOrFail($id);

        // Just to verify the permission
        Feed::withoutGlobalScopes()
            ->byUserAccess($this->getUserId())
            ->findOrFail($comment->post_id);

        if ($request->get('context') == 'edit') {
            $meta = $comment->meta;
            unset($comment->meta);
            $images = Arr::get($meta, 'media_items', []);
            if ($images) {
                $comment->media_images = $images;
            } else {
                $preview = Arr::get($meta, 'media_preview', []);
                if ($preview) {
                    $previewUrl = Arr::get($preview, 'image');
                    $provider = Arr::get($preview, 'provider');
                    if ($previewUrl && $provider == 'uploader') {
                        $media = Media::where('media_url', $previewUrl)
                            ->where('object_source', 'comment')
                            ->where('sub_object_id', $comment->id)
                            ->first();
                        if ($media) {
                            $comment->media_images = [
                                [
                                    'media_id' => $media->id,
                                    'url'      => $media->public_url,
                                    'type'     => $media->media_type,
                                    'width'    => Arr::get($media->settings, 'width'),
                                    'height'   => Arr::get($media->settings, 'height'),
                                    'provider' => Arr::get($media->settings, 'provider', 'uploader')
                                ]
                            ];
                        }
                    } else {
                        $comment->meta = [
                            'media_preview' => $preview
                        ];
                    }
                }
            }
        }

        return [
            'comment' => $comment
        ];
    }
}
