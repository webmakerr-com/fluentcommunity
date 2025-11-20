<?php

namespace FluentCommunityPro\App\Http\Controllers;

use FluentCommunity\App\Models\Feed;
use FluentCommunity\App\Models\Comment;
use FluentCommunity\App\Functions\Utility;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunityPro\App\Models\Moderation;
use FluentCommunity\Framework\Http\Request\Request;

class ModerationController extends Controller
{
    public function get(Request $request)
    {
        $postId = $request->getSafe('post_id', 'intval');

        $parentId = $request->getSafe('parent_id', 'intval');

        $status = $request->getSafe('status', 'sanitize_text_field');

        $contentType = $request->getSafe('content_type', 'sanitize_text_field');

        $reports = Moderation::orderBy('created_at', 'desc')
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($postId, function ($query) use ($postId) {
                $query->where('post_id', $postId);
            })
            ->when($parentId, function ($query) use ($parentId) {
                $query->where('parent_id', $parentId);
            })
            ->when($contentType, function ($query) use ($contentType) {
                $query->where('content_type', $contentType);
            })
            ->with(['post.space', 'post.xprofile', 'comment', 'comment.xprofile', 'reporter'])
            ->paginate();

        $reports->getCollection()->transform(function ($report) {
            if ($report->post) {
                $report->post->title = $report->post->getHumanExcerpt(160);
            }
            if ($report->content_type == 'comment' && $report->comment) {
                $report->comment->title = $report->comment ? $report->comment->getHumanExcerpt(160) : '';
            }
            return $report;
        });

        return [
            'reports' => $reports
        ];
    }

    public function create(Request $request)
    {
        $data['reason'] = $request->getSafe('reason');
        $data['explanation'] = $request->getSafe('explanation');
        $data['content_type'] = $request->getSafe('content_type');
        $data['post_id'] = $request->getSafe('post_id', 'intval');
        $data['parent_id'] = $request->getSafe('parent_id', 'intval');
        $data['user_id'] = get_current_user_id();

        $this->validate($data, [
            'content_type' => 'required|in:post,comment',
            'reason'       => 'required|string|max:255',
            'explanation'  => 'nullable|string|max:1000',
            'user_id'      => 'required',
            'parent_id'    => 'nullable',
            'post_id'      => 'required'
        ]);

        $feed = Feed::byUserAccess($data['user_id'])->withoutGlobalScopes()->findOrFail($data['post_id']);
        $comment = null;

        if ($data['content_type'] == 'comment') {
            $comment = Comment::findOrFail($data['parent_id']); //  just for validation
        } else {
            $feedUser = $feed->user;
            if ($feedUser && $feed->space) {
                if ($feedUser->hasSpacePermission('community_moderator', $feed->space)) {
                    return $this->sendError([
                        'message' => __('You cannot report this content posted by a moderator.', 'fluent-community-pro')
                    ]);
                }
            }
        }

        // Check if the user already reported this content
        $existingReport = Moderation::where('post_id', $data['post_id'])
            ->where('content_type', $data['content_type'])
            ->when($comment, function ($query) use ($comment) {
                $query->where('parent_id', $comment->id);
            })
            ->where('user_id', $data['user_id'])
            ->first();

        if ($existingReport) {
            return $this->sendError([
                'message' => __('You have already reported this content.', 'fluent-community-pro')
            ]);
        }

        $existingReports = Moderation::where('post_id', $data['post_id'])
            ->where('content_type', $data['content_type'])
            ->when($comment, function ($query) use ($comment) {
                $query->where('parent_id', $comment->id);
            });

        $data['reports_count'] = $existingReports->count() + 1;

        if ($data['reports_count'] > 1) {
            $existingReports->update(['reactions_count' => $data['reports_count']]);
        }

        $report = Moderation::create($data);

        $content = $comment ? $comment : $feed;
        $meta = $content->meta ?: [];
        $meta['reports_count'] = $data['reports_count'];
        $content->meta = $meta;
        $content->save();

        do_action('fluent_community/content_moderation/created', $report, $content, $data['content_type']);

        return [
            'message' => __('Your report has been successfully submitted. A moderator will review as soon as possible.', 'fluent-community-pro'),
            'report'  => $report,
            'content' => $content
        ];
    }

    public function update(Request $request, $reportId)
    {
        $report = Moderation::findOrFail($reportId);
        $status = $request->getSafe('status');

        Moderation::where('post_id', $report->post_id)
            ->where('content_type', $report->content_type)
            ->where('parent_id', $report->parent_id)
            ->update(['status' => $status]);

        $reportMeta = $report->meta ?: [];
        $reportMeta['updated_by'] = get_current_user_id();
        $report->meta = $reportMeta;
        $report->save();

        $contentStatus = $status == 'unpublished' ? 'unpublished' : 'published';

        $oldContentStatus = $contentStatus;

        $isCommentContent = $report->content_type == 'comment';

        $contentPublished = false;

        $content = $isCommentContent
            ? Comment::find($report->parent_id)
            : Feed::find($report->post_id);

        if ($content) {
            $oldContentStatus = $content->status;
            $content->status = $contentStatus;

            $meta = $content->meta ?: [];
            $meta['reports_count'] = 0;

            $isPreventPublished = Arr::get($meta, 'prevent_published', 'no') === 'yes';
            if ($isPreventPublished && $contentStatus == 'published' && $oldContentStatus == 'pending') {
                $contentPublished = true;
                unset($meta['prevent_published']);
                unset($meta['auto_flagged']);
            }

            $content->meta = $meta;
            $content->save();
        }

        $feed = null;
        if ($isCommentContent) {
            $feed = Feed::find($report->post_id);
        }

        if ($feed && $contentStatus != $oldContentStatus) {
            if ($contentStatus == 'published') {
                $feed->comments_count = $feed->comments_count + 1;
            }

            if ($contentStatus == 'unpublished') {
                $feed->comments_count = $feed->comments_count - 1;
            }

            $feed->save();
        }

        if ($contentPublished) {
            if ($isCommentContent) {
                do_action('fluent_community/comment_added_' . $feed->type, $content, $feed);
                do_action('fluent_community/comment_added', $content, $feed);
            } else {
                do_action('fluent_community/feed/created', $content);
                if ($content->space_id) {
                    do_action('fluent_community/space_feed/created', $content);
                }
            }
        }

        do_action('fluent_community/report/' . $status, $report);

        return [
            'report'  => $report,
            'content' => $content,
            'message' => __('Report updated successfully', 'fluent-community-pro')
        ];
    }

    public function delete(Request $request, $reportId)
    {
        $report = Moderation::findOrFail($reportId);
        do_action('fluent_community/report/before_delete', $report);
        $report->delete();

        do_action('fluent_community/report/after_delete', $report);

        return [
            'message' => __('Report deleted successfully', 'fluent-community-pro')
        ];
    }

    public function saveConfig(Request $request)
    {
        $config = $request->get('config', []);
        $config['is_enabled'] = Arr::get($config, 'is_enabled', 'no') === 'yes' ? 'yes' : 'no';
        $config['profanity_filter'] = sanitize_text_field(Arr::get($config, 'profanity_filter', ''));
        $config['flag_after_threshold'] = (int)Arr::get($config, 'flag_after_threshold', 0);

        Utility::updateOption('moderation_config', $config);

        $globalFeatures = Utility::getOption('fluent_community_features', []);
        $globalFeatures['content_moderation'] = $config['is_enabled'];
        Utility::updateOption('fluent_community_features', $globalFeatures);

        return [
            'config'  => $config,
            'message' => __('Moderation config saved successfully', 'fluent-community-pro')
        ];
    }

    public function isEnabled()
    {
        $config = Utility::getOption('moderation_config', []);
        return Arr::get($config, 'is_enabled', '') == 'yes';
    }
}
