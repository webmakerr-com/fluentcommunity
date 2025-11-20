<?php

namespace FluentCommunity\App\Http\Controllers;

use FluentCommunity\App\Http\Controllers\Controller;
use FluentCommunity\App\Models\Activity;
use FluentCommunity\App\Models\Feed;
use FluentCommunity\App\Models\BaseSpace;
use FluentCommunity\App\Models\SpaceUserPivot;
use FluentCommunity\App\Services\ProfileHelper;
use FluentCommunity\Framework\Http\Request\Request;

class ActivityController extends Controller
{
    public function getActivities(Request $request)
    {
        $context = $request->get('context', []);

        $latestActivityIds = Activity::where(function ($q) {
            $userId = get_current_user_id();
            $q->where('is_public', 1);
            if (!$userId) {
                return $q;
            }

            $q->orWhere(function ($query) use ($userId) {
                $spaceIds = get_user_meta($userId, '_fcom_space_ids', true);
                if ($spaceIds) {
                    $query->whereIn('space_id', $spaceIds);
                    return $query;
                }
            });
        })
            ->whereIn('action_name', ['feed_published', 'comment_added'])
            ->selectRaw('MAX(id) as id')
            ->groupBy('feed_id', 'action_name')
            ->pluck('id');

        $activities = Activity::whereIn('id', $latestActivityIds)
            ->with(['xprofile' => function ($q) {
                $q->select(ProfileHelper::getXProfilePublicFields());
            }, 'feed', 'space'])
            ->whereHas('xprofile', function ($q) {
                $q->where('status', 'active');
            })
            ->orderBy('id', 'DESC');

        $spaceId = null;
        $userId = null;

        if ($context && !empty($context['space_id'])) {
            $spaceId = (int)$context['space_id'];
            $activities->where('space_id', $context['space_id']);
        } else if ($context && !empty($context['user_id'])) {
            $userId = (int)$context['user_id'];
            $activities->where('user_ID', $context['user_id']);
        }

        $activities = $activities->paginate();

        $formattedActivities = [];

        foreach ($activities as $activity) {
            $message = $activity->getFormattedMessage();
            if (!$message) {
                continue;
            }

            $route = $activity->feed->getJsRoute();

            if ($activity->action_name == 'comment_added') {
                $route['query'] = [
                    'comment_id' => $activity->related_id
                ];
            }

            $formattedActivities[] = [
                'id'         => $activity->id,
                'message'    => $message,
                'xprofile'   => $activity->xprofile,
                'updated_at' => $activity->updated_at->format('Y-m-d H:i:s'),
                'route'      => $route
            ];
        }

        if ($spaceId) {
            $afterContent = apply_filters('fluent_community/activity/after_contents_space', '', $spaceId, $context);
        } else if ($userId) {
            $afterContent = apply_filters('fluent_community/activity/after_contents_user', '', $userId, $context);
        } else {
            $afterContent = apply_filters('fluent_community/activity/after_contents', '', $context);
        }

        $returnData = [
            'activities'     => [
                'data'         => $formattedActivities,
                'total'        => $activities->total(),
                'per_page'     => $activities->perPage(),
                'current_page' => $activities->currentPage(),
            ],
            'after_contents' => $afterContent,
        ];

        if (!$spaceId) {

            if (!$userId) {
                $returnData['pinned_posts'] = $this->getPinnedPosts(null, true);
            }

            return $returnData;
        }

        if ($request->get('with_pins')) {
            $returnData['pinned_posts'] = $this->getPinnedPosts($spaceId, $request->get('is_trending'));
        }

        if ($request->get('with_pending_count')) {
            $pendingCount = 0;
            $user = $this->getUser();

            $space = BaseSpace::find($spaceId);

            if ($user && $space && $user->can('can_add_member', $space)) {
                $pendingCount = SpaceUserPivot::bySpace($space->id)
                    ->where('status', 'pending')
                    ->count();
            }

            $returnData['pending_count'] = $pendingCount;
        }

        return $returnData;
    }

    private function getPinnedPosts($spaceId = null, $isTrending = false)
    {
        $postsQuery = Feed::when($spaceId, function ($q) use ($spaceId) {
            $q->where('space_id', $spaceId);
        })
            ->byUserAccess(get_current_user_id())
            ->where('status', 'published')
            ->whereHas('xprofile', function ($q) {
                $q->where('status', 'active');
            })
            ->with(['xprofile' => function ($q) {
                $q->select(ProfileHelper::getXProfilePublicFields());
            }], 'space')
            ->limit(5);

        if ($isTrending && !$spaceId) {
            // Find trending posts which are created within last 7 days and order by reactions and comments count
            // make the comments_count * 2 to give more priority to comments
            $postsQuery->where('created_at', '>=', gmdate('Y-m-d H:i:s', strtotime('-7 days')))
                ->orderByRaw('(reactions_count + (comments_count * 2)) DESC');
        } else {
            $postsQuery->orderBy('id', 'DESC')
                ->where('priority', 1);
        }

        $posts = $postsQuery->get();

        $formattedActivities = [];

        foreach ($posts as $post) {
            $formattedActivities[] = [
                'id'         => $post->id,
                'message'    => $post->getHumanExcerpt(100),
                'permalink'  => $post->getPermalink(),
                'xprofile'   => $post->xprofile,
                'created_at' => $post->created_at->format('Y-m-d H:i:s'),
            ];
        }

        return $formattedActivities;
    }
}
