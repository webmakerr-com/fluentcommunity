<?php

namespace FluentCommunityPro\App\Services\Analytics;

use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\App\Models\Comment;
use FluentCommunity\App\Models\Space;
use FluentCommunity\App\Models\Feed;

class Spaces
{

    /**
     * @var $provider AnalyticsService
     */
    protected $provider;

    protected $startDate;

    protected $endDate;

    public function __construct($provider, $startDate, $endDate)
    {
        $this->provider = $provider;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function getSpaceWidget($spaceId = null)
    {

        $dateRanges = $this->getDateRanges($this->startDate, $this->endDate);

        $spaceQuery = Space::query()->where('status', 'published');

        $feedQuery = Feed::query()->whereHas('space')->when($spaceId, function ($query) use ($spaceId) {
            return $query->where('status', 'published')->where('space_id', $spaceId);
        });

        $commentQuery = Comment::query()->when($spaceId, function ($query) use ($spaceId) {
            return $query->whereHas('post', function ($query) use ($spaceId) {
                return $query->where('space_id', $spaceId);
            });
        });

        $xprofileQuery = XProfile::query()->when($spaceId, function ($query) use ($spaceId) {
            return $query->whereHas('spaces', function ($query) use ($spaceId) {
                return $query->where('space_id', $spaceId);
            });
        });

        $totalSpaces = $this->provider->getWidgetCountsWithComparison($dateRanges, $spaceQuery, __('Total Spaces', 'fluent-community-pro'));
        $totalPosts = $this->provider->getWidgetCountsWithComparison($dateRanges, $feedQuery, __('Total Posts', 'fluent-community-pro'));
        $totalComments = $this->provider->getWidgetCountsWithComparison($dateRanges, $commentQuery, __('Total Comments', 'fluent-community-pro'));
        $totalMembers = $this->provider->getWidgetCountsWithComparison($dateRanges, $xprofileQuery, __('Total Members', 'fluent-community-pro'));

        $widgets = [
            'total_spaces'   => $totalSpaces,
            'total_posts'    => $totalPosts,
            'total_comments' => $totalComments,
            'total_members'  => $totalMembers,
        ];

        return $widgets;
    }

    public function getSpaceActivity($spaceId = null)
    {
        if ($spaceId) {
            $query = function ($query) use ($spaceId) {
                return $query->where('id', $spaceId);
            };
        } else {
            $query = function ($query) {
                return $query;
            };
        }

        $chartStatistics = $this->provider->getChartStatistics($this->startDate, $this->endDate, 'spaces', 'created_at', $query);
        $chartStatistics['title'] = __('Activity', 'fluent-community-pro');
        return $chartStatistics;
    }

    public function getPostActivity($spaceId = null)
    {
        if ($spaceId) {
            $query = function ($query) use ($spaceId) {
                return $query->where('space_id', $spaceId);
            };
        } else {
            $query = function ($query) {
                return $query;
            };
        }

        $chartStatistics = $this->provider->getChartStatistics($this->startDate, $this->endDate, 'posts', 'created_at', $query);
        $chartStatistics['title'] = __('Activity', 'fluent-community-pro');
        return $chartStatistics;
    }

    public function getTopSpaces($spaceId = null)
    {

        if ($spaceId) {
            return $this->getSpacePopularPosts($spaceId);
        }

        $dateRanges = $this->getDateRanges($this->startDate, $this->endDate);

        $popularSpaces = Space::query()
            ->whereHas('posts')
            ->whereHas('members')
            ->where('status', 'published')
            ->withCount(['members' => function ($query) use ($dateRanges) {
                return $query->whereBetween('created_at', [$dateRanges['start_date'], $dateRanges['end_date']]);
            },
                         'posts'   => function ($query) use ($dateRanges) {
                             return $query->whereBetween('created_at', [$dateRanges['start_date'], $dateRanges['end_date']]);
                         }])
            ->with(['posts' => function ($query) {
                $query->withCount('comments')->where('status', 'published');
            }, 'group'])
            ->take(10)
            ->get();

        $popularSpaces = $popularSpaces->map(function ($space) {
            $space->comments_count = $space->posts->sum('comments_count');
            $space->group = null;
            return Arr::only($space->toArray(), ['id', 'title', 'members_count', 'posts_count', 'comments_count', 'group']);
        });

        // sort all spaces by comments post and members count
        $popularSpaces = $popularSpaces->sortByDesc(function ($space) {
            return $space['comments_count'] + $space['posts_count'] + $space['members_count'];
        })->values();

        $columns = [
            [
                'prop'  => 'title',
                'label' => __('Space Name', 'fluent-community-pro'),
            ],
            [
                'prop'  => 'posts_count',
                'label' => __('Posts', 'fluent-community-pro'),
            ],
            [
                'prop'  => 'comments_count',
                'label' => __('Comments', 'fluent-community-pro'),
            ],
            [
                'prop'  => 'members_count',
                'label' => __('Members', 'fluent-community-pro'),
            ]
        ];

        $spaces = [
            'columns' => $columns,
            'data'    => $popularSpaces
        ];

        return $spaces;
    }

    public function getSpacePopularPosts($spaceId)
    {

        $dateRanges = $this->getDateRanges($this->startDate, $this->endDate);

        $popularPosts = Feed::query()
            ->whereHas('space', function ($query) use ($spaceId) {
                return $query->where('id', $spaceId);
            })
            ->whereBetween('created_at', [$dateRanges['start_date'], $dateRanges['end_date']])
            ->withCount('reactions')
            ->where('status', 'published')
            ->withCount('comments')
            ->orderBy('comments_count', 'desc')
            ->orderBy('reactions_count', 'desc')
            ->take(10)
            ->get();

        $formattedData = [];

        foreach ($popularPosts as $popularPost) {
            $formattedData[] = [
                'id'              => $popularPost->id,
                'title'           => $popularPost->getHumanExcerpt(),
                'comments_count'  => $popularPost->comments_count,
                'reactions_count' => $popularPost->reactions_count,
            ];
        }

        $columns = [
            [
                'prop'  => 'title',
                'label' => __('Post Title', 'fluent-community-pro'),
            ],
            [
                'prop'  => 'comments_count',
                'label' => __('Comments', 'fluent-community-pro'),
            ],
            [
                'prop'  => 'reactions_count',
                'label' => __('Reactions', 'fluent-community-pro'),
            ]
        ];

        $posts = [
            'columns' => $columns,
            'data'    => $formattedData
        ];

        return $posts;
    }

    public function getDateRanges($startDate, $endDate)
    {
        return [
            'start_date' => gmdate('Y-m-d', strtotime($startDate)) . ' 00:00:00',
            'end_date'   => gmdate('Y-m-d', strtotime($endDate)) . ' 23:59:59'
        ];
    }
}
