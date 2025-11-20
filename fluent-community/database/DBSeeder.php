<?php

namespace FluentCommunity\Database;

use FluentCommunity\App\App;
use FluentCommunity\Dev\Seeds\CommentTableSeeder;
use FluentCommunity\Dev\Seeds\FeedTableSeeder;
use FluentCommunity\Dev\Seeds\PostReactionsTableSeeder;
use FluentCommunity\Dev\Seeds\SpaceTableSeeder;
use FluentCommunity\Dev\Seeds\SpaceUserTableSeeder;
use FluentCommunity\Dev\Seeds\TermFeedTableSeeder;
use FluentCommunity\Dev\Seeds\TermsTableSeeder;
use FluentCommunity\Dev\Seeds\UserActivityTableSeeder;
use FluentCommunity\Dev\Seeds\UserTableSeeder;
use FluentCommunity\Dev\Seeds\XProfileTableSeeder;

class DBSeeder
{
    public static function run()
    {
        //self::clean();
        $startTime = microtime(true);
        UserTableSeeder::run(1000);
        XProfileTableSeeder::run(1000);
        FeedTableSeeder::run(1000);
        CommentTableSeeder::run(1000);
        SpaceTableSeeder::run(1000);
        SpaceUserTableSeeder::run(1000);
        TermsTableSeeder::run(1000);
        TermFeedTableSeeder::run(1000);
        PostReactionsTableSeeder::run(100000);
        UserActivityTableSeeder::run(100000);

        $endTime = microtime(true);
        $totalTime = round($endTime - $startTime, 2);

        /* translators: %s is replaced by the total time */
        echo sprintf(esc_html__('Seeding completed in %s seconds', 'fluent-community'), esc_html($totalTime)) . "\n";
    }

    public static function clean()
    {
        $db = App::make('db');
         //$db->table('users')->truncate();
         //$db->table('usermeta')->truncate();
         $db->table('fcom_xprofile')->truncate();
         $db->table('fcom_posts')->truncate();
         $db->table('fcom_post_comments')->truncate();
         $db->table('fcom_spaces')->truncate();
         $db->table('fcom_space_user')->truncate();
         $db->table('fcom_terms')->truncate();
         $db->table('fcom_term_feed')->truncate();
         $db->table('fcom_post_reactions')->truncate();
         $db->table('fcom_user_activities')->truncate();
    }
}
