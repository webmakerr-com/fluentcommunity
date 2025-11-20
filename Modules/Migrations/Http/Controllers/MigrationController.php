<?php

namespace FluentCommunity\Modules\Migrations\Http\Controllers;

use FluentCommunity\App\Http\Controllers\Controller;
use FluentCommunity\Framework\Http\Request\Request;

class MigrationController extends Controller
{
    public function getAvailableMigrations(Request $request)
    {
        $migrations = [];

        if (defined('BP_PLATFORM_VERSION')) {
            $migrations[] = [
                'key'   => 'buddyboss',
                'title' => 'BuddyBoss',
                'logo'  => FLUENT_COMMUNITY_PLUGIN_URL . 'assets/images/brands/bboss_logo.png'
            ];
        }  else if (defined('BP_PLUGIN_DIR')) {
            $migrations[] = [
                'key'   => 'buddypress',
                'title' => 'BuddyPress',
                'logo'  => FLUENT_COMMUNITY_PLUGIN_URL . 'assets/images/brands/buddypress.svg'
            ];
        }

        return [
            'migrations' => $migrations
        ];
    }
}
