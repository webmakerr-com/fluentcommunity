<?php

namespace FluentCommunity\App\Hooks\Handlers;

use FluentCommunity\App\Models\Comment;
use FluentCommunity\App\Models\Feed;
use FluentCommunity\App\Models\User;
use FluentCommunity\App\Services\Helper;

class RateLimitHandler
{
    public function register()
    {
        add_action('fluent_community/check_rate_limit/create_post', [$this, 'maybeLimitPost'], 10, 1);
        add_action('fluent_community/check_rate_limit/create_comment', [$this, 'maybeLimitComment'], 10, 1);
    }

    public function maybeLimitPost(User $user)
    {
        if (Helper::isSiteAdmin($user->ID, $user)) {
            return;
        }

        // Check how many posts user has created in last 5 minutes
        $postsCount = Feed::query()->withoutGlobalScopes()->where('user_id', $user->ID)
            ->where('created_at', '>', gmdate('Y-m-d H:i:s', current_time('timestamp') - 300))
            ->count();

        $limitPer5Minutes = apply_filters('fluent_community/rate_limit/posts_per_5_minutes', 5);

        if ($postsCount > $limitPer5Minutes) {
            throw new \Exception(esc_html__('You have reached the limit of posting. Please try after some time', 'fluent-community'));
        }
    }

    public function maybeLimitComment(User $user)
    {
        if (Helper::isSiteAdmin($user->ID, $user)) {
            return;
        }

        // Check how many comments user has created in last 5 minutes
        $commentsCount = Comment::query()->withoutGlobalScopes()->where('user_id', $user->ID)
            ->where('created_at', '>', gmdate('Y-m-d H:i:s', current_time('timestamp') - 60))
            ->count();

        $limitPerMinute = apply_filters('fluent_community/rate_limit/comments_per_minute', 5);

        if ($commentsCount > $limitPerMinute) {
            throw new \Exception(esc_html__('You have reached the limit of commenting. Please try after some time', 'fluent-community'));
        }
    }
}
