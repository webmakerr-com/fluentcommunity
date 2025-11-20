<?php

namespace FluentCommunityPro\App\Modules\Emoji;

class EmojiModule
{
    public function register($app)
    {
        add_filter('fluent_community/portal_vars', function ($vars) {
            $vars['features']['emoji_app'] = true;
            return $vars;
        });
        
        add_filter('fluent_community/feed_data/new', [$this, 'maybeEncodeEmoji'], 10, 1);

        add_action('fluent_community/before_js_loaded', function () {
            ?>
            <style>
                .emoji-type-image.emoji-set-twitter {
                    background-image: url(<?php echo esc_url(plugin_dir_url(__FILE__) . 'emoji-set.png'); ?>);
                }
            </style>
            <?php
        });

    }
    
    public function maybeEncodeEmoji($feedData)
    {
        if (isset($feedData['message'])) {
            $feedData['message'] = wp_encode_emoji($feedData['message']);
        }

        return $feedData;
    }
}
