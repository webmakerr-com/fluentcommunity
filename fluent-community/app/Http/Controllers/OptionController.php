<?php

namespace FluentCommunity\App\Http\Controllers;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Hooks\Handlers\PortalHandler;
use FluentCommunity\App\Models\BaseSpace;
use FluentCommunity\App\Services\FeedsHelper;
use FluentCommunity\App\Services\LockscreenService;
use FluentCommunity\Framework\Http\Request\Request;

class OptionController extends Controller
{

    public function getAppVars()
    {
        $appVars = (new PortalHandler())->appVars();
        unset($appVars['rest']);

        return [
            'appVars'           => $appVars,
            'menu_links_groups' => Utility::getPortalSidebarData('sidebar')
        ];
    }

    public function getMenuItems()
    {
        return Utility::getPortalSidebarData('sidebar');
    }

    public function getSidebarMenuHtml(Request $request)
    {
        ob_start();
        do_action('fluent_community/portal_sidebar', 'ajax');

        $html = ob_get_clean();

        $userModel = $this->getUser();
        $userSpaces = [];
        if ($userModel) {
            if ($userModel->isCommunityModerator()) {
                $userSpaces = BaseSpace::orderBy('title', 'ASC')
                    ->get();
            } else {
                $userSpaces = BaseSpace::orderBy('title', 'ASC')
                    ->whereHas('members', function ($q) {
                        $q->where('user_id', get_current_user_id())
                            ->where('status', 'active');
                    })
                    ->get();
            }

            $userSpaces->each(function ($space) use ($userModel) {
                $space->permissions = $userModel->getSpacePermissions($space);
                $space->membership = $space->getMembership($userModel->ID);
                $space->description_rendered = wp_kses_post(FeedsHelper::mdToHtml($space->description));
                if ($space->privacy == 'private' && !$space->membership) {
                    $space->lockscreen_config = LockscreenService::getLockscreenConfig($space);
                }
                $space->topics = Utility::getTopicsBySpaceId($space->id);
            });
            $userSpaces = $userSpaces->keyBy('slug');
        } else {
            $userSpaces = (object)[];
        }

        return [
            'sidebar_html' => $html,
            'auth_spaces'  => $userSpaces
        ];
    }
}
