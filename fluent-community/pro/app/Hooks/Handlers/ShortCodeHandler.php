<?php

namespace FluentCommunityPro\App\Hooks\Handlers;

use FluentCommunity\App\Hooks\Handlers\PortalHandler;
use FluentCommunity\App\Models\BaseSpace;
use FluentCommunity\App\Models\Comment;
use FluentCommunity\App\Models\Feed;
use FluentCommunity\App\Models\Reaction;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\Framework\Support\Arr;

class ShortCodeHandler
{
    public function register()
    {

      //  add_shortcode('fluent_community_stats', [$this, 'renderStats']);

        return;

        add_shortcode('fluent_community', [$this, 'renderApp']);

        add_filter('fluent_community/portal_route_type', function ($type) {
            return 'hash';
        });

        add_filter('fluent_community/portal_slug', function () {
            return 'community';
        });

    }

    public function renderApp($atts, $content = '')
    {
        $atts = shortcode_atts([
            'hide_sidebar' => 'no',
            'hide_header'  => 'no'
        ], $atts);

        $hasSidebar = Arr::get($atts, 'hide_sidebar') != 'yes';
        $hasHeader = Arr::get($atts, 'hide_header') != 'yes';

        $portalHander = new PortalHandler();
        $data = $portalHander->getAppData();

        $portalHander->loadClassicPortalAssets($data);

        $wrapClass = 'fhr_wrap';

        if (!$hasHeader) {
            $wrapClass .= ' fhr_no_header';
        }

        if (!$hasSidebar) {
            $wrapClass .= ' fhr_no_sidebar';
        }

        ob_start();

        ?>
        <div class="fluent_com">
            <div class="<?php echo esc_attr($wrapClass); ?>">
                <?php $hasHeader && do_action('fluent_community/portal_header', 'headless'); ?>
                <div class="fhr_content">
                    <div id="fluent_comminity_body" class="fhr_home">
                        <div class="feed_layout">
                            <?php if ($hasSidebar): ?>
                                <div class="spaces">
                                    <div id="fluent_community_sidebar_menu" class="space_contents">
                                        <?php do_action('fluent_community/portal_sidebar', 'headless'); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div id="fluent_com_portal"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php do_action('fluent_community/before_js_loaded'); ?>
        <?php do_action('fluent_community/portal_footer'); ?>
        <?php

        return ob_get_clean();
    }

    public function renderStats($atts, $content = '')
    {
        $stats = [
            'total_spaces' => [
                'label' => 'Total Spaces',
                'value' => BaseSpace::count()
            ],
            'total_community_users' => [
                'label' => 'Total Community Users',
                'value' => XProfile::count()
            ],
            'total_posts' => [
                'label' => 'Total Posts',
                'value' => Feed::count()
            ],
            'total_comments' => [
                'label' => 'Total Comments',
                'value' => Comment::count()
            ],
            'total_post_reactions' => [
                'label' => 'Total Likes',
                'value' => Reaction::count()
            ],
        ];

        ob_start();

        ?>
        <style>
            .fluent_community_stats {
                display: flex;
                flex-wrap: wrap;
                justify-content: space-between;
            }

            .fluent_community_stats * {
                box-sizing: border-box;
            }

            .fluent_community_stat {
                width: 49%;
                margin-bottom: 10px;
                padding: 10px;
                border-radius: 5px;
                background-color: var(--fcom-primary-bg, #eaeaea);
                border: 1px solid var(--fcom-primary-border, #e4e7eb);
                color: var(--fcom-primary-text, #19283a);
            }

            .fluent_community_stat h4 {
                margin: 0;
                font-size: 16px;
            }

            .fluent_community_stat p {
                margin: 0;
                font-size: 20px;
                font-weight: bold;
            }
        </style>
        <div class="fluent_community_stats">
            <?php foreach ($stats as $key => $stat): ?>
                <div class="fluent_community_stat">
                    <p><?php echo number_format($stat['value']); ?></p>
                    <h4><?php echo esc_html($stat['label']); ?></h4>
                </div>
            <?php endforeach; ?>
        <?php

        return ob_get_clean();
    }
}
