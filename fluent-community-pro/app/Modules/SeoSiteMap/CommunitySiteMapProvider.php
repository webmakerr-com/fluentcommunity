<?php

namespace FluentCommunityPro\App\Modules\SeoSiteMap;

use FluentCommunity\App\Services\Helper;

class CommunitySiteMapProvider extends \WP_Sitemaps_Provider
{

    // make visibility not protected
    public $name;

    /**
     * Constructor. Sets name, object_type properties.
     *
     * $name         Provider name. Uses in URL (should be unique).
     * $object_type  The object name that the provider works with.
     *               Passes into the hooks (should be unique).
     */
    public function __construct()
    {
        $this->name = 'fluentcommunity';
        $this->object_type = 'fluent-community';
    }

    public function get_sitemap_url($name, $page)
    {
        return Helper::baseUrl('site-maps/index.xml');
    }

    /**
     * Gets a URL list for a sitemap.
     *
     * @param int $page_num Page of results.
     * @param string $subtype Optional. Object subtype name. Default empty.
     *
     * @return array Array of URLs for a sitemap.
     */
    public function get_url_list($page_num, $object_subtype = '')
    {
        return [
            [
                'loc' => Helper::baseUrl('site-maps/index.xml'),
            ]
        ];
    }

    /**
     * Gets the maximum number of pages for a given object subtype.
     *
     * @param string $object_subtype Optional. Object subtype name. Default empty.
     *
     * @return int Number of pages.
     */
    public function get_max_num_pages($object_subtype = '')
    {
        return 1;
    }
}
