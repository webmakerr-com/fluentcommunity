<?php

namespace FluentCommunity\App\Http\Controllers;

use FluentCommunity\App\Models\Comment;
use FluentCommunity\App\Models\Feed;
use FluentCommunity\App\Models\Reaction;
use FluentCommunity\App\Services\FeedsHelper;
use FluentCommunity\App\Services\ProfileHelper;
use FluentCommunity\Framework\Http\Request\Request;
use FluentCommunity\Framework\Support\Arr;

class ReactionController extends Controller
{
    public function getByFeedId(Request $request)
    {
        $feedId = $request->getSafe('feed_id', 'intval');

        if (!$feedId) {
            return [
                'reactions' => []
            ];
        }

        $feed = Feed::withoutGlobalScopes()->byUserAccess(get_current_user_id())->findOrFail($feedId);

        $reactions = $feed->reactions()
            ->whereHas('xprofile')
            ->with([
                'xprofile' => function ($q) {
                    $q->select(ProfileHelper::getXProfilePublicFields());
                }
            ])
            ->where('type', 'like')
            ->distinct('user_id')
            ->limit(100)
            ->get(); // Todo: Add lazy loading in the future

        return apply_filters('fluent_community/reactions_api_response', [
            'reactions' => $reactions
        ], $reactions, $request->all());
    }

    public function getByCommentId(Request $request)
    {
        $commentId = $request->getSafe('comment_id', 'intval');

        if (!$commentId) {
            return [
                'reactions' => []
            ];
        }

        $comment = Comment::findOrFail($commentId);

        // Just validate the permission
        Feed::withoutGlobalScopes()->byUserAccess(get_current_user_id())->findOrFail($comment->post_id);

        $reactions = $comment
            ->reactions()
            ->whereHas('xprofile')
            ->with([
                'xprofile' => function ($q) {
                    $q->select(ProfileHelper::getXProfilePublicFields());
                }
            ])
            ->where('type', 'like')
            ->distinct('user_id')
            ->limit(100)
            ->get(); // Todo: Add lazy loading in the future

        return apply_filters('fluent_community/reactions_api_response', [
            'reactions' => $reactions
        ], $reactions, $request->all());
    }

    public function addOrRemovePostReact(Request $request, $feed_id)
    {
        $feed = Feed::withoutGlobalScopes()->findOrFail($feed_id);
        $type = $request->get('react_type', 'like');
        $willRemove = $request->get('remove');

        $react = Reaction::where('user_id', get_current_user_id())
            ->where('object_id', $feed->id)
            ->where('type', $type)
            ->objectType('feed')
            ->first();

        if ($willRemove) {
            if ($react) {
                $react->delete();
                if ($type == 'like') {
                    $feed->reactions_count = $feed->reactions_count - 1;
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
            $feed->save();

            $react->load('xprofile');
            do_action('fluent_community/feed/react_added', $react, $feed);
        }

        return [
            'message'   => __('Reaction has been added', 'fluent-community'),
            'new_count' => $feed->reactions_count
        ];
    }

    public function castSurveyVote(Request $request, $feed_id)
    {
        $feed = Feed::where('id', $feed_id)
            ->byUserAccess($this->getUserId())
            ->first();

        if (!$feed || $feed->content_type != 'survey') {
            return $this->sendError([
                'message' => __('Sorry! you do not have access to this post or invalid request', 'fluent-community')
            ]);
        }

        $surveyConfig = $feed->meta['survey_config'];
        $endDate = Arr::get($surveyConfig, 'end_date');
        if ($endDate && strtotime($endDate) < current_time('timestamp')) {
            return $this->sendError([
                'message' => __('Sorry! This survey has ended', 'fluent-community')
            ]);
        }

        $voteIndexes = $request->get('vote_indexes', []);
        $feed = FeedsHelper::castSurveyVote($voteIndexes, $feed, $this->getUserId());
        $surveyConfig = $feed->meta['survey_config'];

        $votedOptions = $feed->getSurveyCastsByUserId($this->getUserId());

        foreach ($surveyConfig['options'] as $index => $option) {
            if (in_array($option['slug'], $votedOptions)) {
                $surveyConfig['options'][$index]['voted'] = true;
            }
        }

        return [
            'survey_config' => $surveyConfig
        ];
    }

    public function getSurveyVoters($feedId, $optionSlug)
    {
        $currentUserId = get_current_user_id();

        $feed = Feed::withoutGlobalScopes()->byUserAccess($currentUserId)->findOrFail($feedId);

        $voters = $feed->surveyVotes()
            ->where('object_type', $optionSlug)
            ->whereHas('xprofile')
            ->with([
                'xprofile' => function ($q) {
                    $q->select(ProfileHelper::getXProfilePublicFields());
                }
            ])
            ->limit(100)
            ->get(); // Todo: Add lazy loading in the future

        return [
            'voters' => $voters
        ];
    }
}
