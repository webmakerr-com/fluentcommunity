<?php

namespace FluentCommunityPro\App\Modules;

use FluentCommunity\App\Functions\Utility;

class ModulesInit
{
    public function register($app)
    {
        add_action('fluent_community/install_messaging_plugin', function () {
            // This is a custom plugin. Need to be handled differently
            \FluentCommunityPro\App\Services\ProHelper::backgroundInstallerDirect([
                'name'      => 'Fluent Messages',
                'repo-slug' => 'fluent-messaging',
                'file'      => 'fluent-messaging.php'
            ], 'fluent-messages', 'https://s3.amazonaws.com/wpcolorlab/fluent-messaging.zip');
        });

        $this->initModules($app);
    }

    private function initModules($app)
    {
        $features = Utility::getFeaturesConfig();

        if (isset($features['cloud_storage']) && $features['cloud_storage'] === 'yes') {
            (new \FluentCommunityPro\App\Modules\CloudStorage\CloudStorageModule())->register($app);
        }

        (new \FluentCommunityPro\App\Modules\LeaderBoard\LeaderBoardModule())->register($app, $features);
        (new \FluentCommunityPro\App\Modules\UserBadge\UserBadgeModule())->register($app, $features);
        (new \FluentCommunityPro\App\Modules\Integrations\Integrations())->register($app);

        (new \FluentCommunityPro\App\Modules\DocumentLibrary\DocumentModule())->register($app);


        if (isset($features['giphy_module']) && $features['giphy_module'] === 'yes') {
            (new \FluentCommunityPro\App\Modules\Giphy\GiphyModule())->register($app);
        }

        if (isset($features['emoji_module']) && $features['emoji_module'] === 'yes') {
            (new \FluentCommunityPro\App\Modules\Emoji\EmojiModule())->register($app);
        }

        if (isset($features['invitation']) && $features['invitation'] === 'yes') {
            (new \FluentCommunity\Modules\Auth\InvitationModule())->register($app);
        }

        (new \FluentCommunityPro\App\Modules\Webhooks\WebhookModule())->register();

        (new \FluentCommunityPro\App\Modules\SeoSiteMap\SeoSiteMapHandler())->register();

        (new \FluentCommunityPro\App\Modules\Quiz\QuizModule())->register($app);
    }
}
