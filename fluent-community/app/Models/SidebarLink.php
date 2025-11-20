<?php

namespace FluentCommunity\App\Models;


/**
 *  SideBar Link Model - DB Model for Individual Links
 *
 *  Database Model
 *
 * @package FluentCommunity\App\Models
 *
 * @version 1.7.5
 */
class SidebarLink extends BaseSpace
{
    protected static $type = 'sidebar_link';

    public function defaultSettings()
    {
        return [
            'emoji'                => '',
            'shape_svg'            => '',
            'permalink'            => '',
            'membership_ids'     => []
        ];
    }

}
