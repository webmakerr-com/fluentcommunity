<?php

namespace FluentCommunityPro\App\Modules\SeoSiteMap;

use FluentCommunity\App\Models\BaseSpace;
use FluentCommunity\App\Models\Comment;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\App\Services\ProfileHelper;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\Modules\Course\Model\CourseLesson;
use FluentCommunity\Modules\Course\Model\CourseTopic;

class SeoSiteMapHandler
{
    public function register()
    {
        add_action('fluent_community/portal_head_meta', [$this, 'addSeoIndexTag'], 10, 1);

        add_action('fluent_community/rendering_path_ssr_site-maps', [$this, 'renderSiteMaps'], 10, 1);

        add_action('init', function () {
            if (apply_filters('fluent_communuty/add_sitemap_provider', true)) {
                wp_register_sitemap_provider('fluentcommunity', new CommunitySiteMapProvider());
            }
        });


        add_filter('fluent_community/feed_view_json_ld', [$this, 'pushJsonLdData'], 10, 3);
        add_filter('fluent_community/course_view_json_ld', [$this, 'pushJsonLdDataForCourse'], 10, 3);
    }

    public function renderSiteMaps($paths = [])
    {
        if (!$this->isPublicCommunity() || count($paths) < 2) {
            $rendered = new \WP_Sitemaps_Renderer();
            echo $rendered->render_index([[
                'loc' => Helper::baseUrl('/')
            ]]);
            die();
        }

        $requestedMap = $paths[1];
        if ($requestedMap == 'style.xsl' || $requestedMap == 'style-index.xsl') {
            $this->renderStyleSheet($requestedMap);
            die();
        }

        // remove the xml extension
        $requestedMap = str_replace('.xml', '', $requestedMap);

        // explode with -
        $requestedMap = explode('-', $requestedMap);

        $pageNum = Arr::get($requestedMap, 1, 1);
        $objectType = Arr::get($requestedMap, 0);
        $validObjectTypes = [
            'index',
            'feeds',
            'spaces',
            'courses',
            'lessons'
        ];

        if (!in_array($objectType, $validObjectTypes)) {
            return;
        }

        $lists = [];
        $sitemapQuery = new SitemapQuery(1000, $pageNum);

        if ($objectType == 'index') {
            $lists = $this->getIndexLinkList();
        } else if ($objectType == 'feeds') {
            $lists = $sitemapQuery->getFeeds();
        } else if ($objectType == 'spaces') {
            $lists = $sitemapQuery->getSpaces();
        } else if ($objectType == 'courses') {
            $lists = $sitemapQuery->getCourses();
        } else if ($objectType == 'lessons') {
            $lists = $sitemapQuery->getLessons();
        }

        $isRootMap = Helper::getPortalSlug(false) === '';

        add_filter('wp_sitemaps_stylesheet_url', function ($url) use ($isRootMap) {
            if ($isRootMap) {
                return $url;
            }
            return Helper::baseUrl('site-maps/style.xsl');
        });

        add_filter('wp_sitemaps_stylesheet_index_url', function ($url) use ($isRootMap) {
            if ($isRootMap) {
                return $url;
            }
            return Helper::baseUrl('site-maps/style-index.xsl');
        });

        $rendered = new \WP_Sitemaps_Renderer();

        if ($objectType == 'index') {
            echo $rendered->render_index($lists);
        } else {
            $rendered->render_sitemap($lists);
        }

        die();
    }

    public function getIndexLinkList()
    {
        $sitemapQuery = new SitemapQuery(1000);
        $feedsPageCount = $sitemapQuery->getMaxPageNums('feeds');
        $spacePageCount = $sitemapQuery->getMaxPageNums('spaces');
        $lessonsPageCount = $sitemapQuery->getMaxPageNums('lessons');
        $coursesPageCount = $sitemapQuery->getMaxPageNums('courses');

        $allLinks = [];

        if ($feedsPageCount > 0) {
            for ($i = 1; $i <= $feedsPageCount; $i++) {
                $allLinks[] = [
                    'loc' => Helper::baseUrl('site-maps/feeds-' . $i . '.xml')
                ];
            }
        }

        if ($spacePageCount > 0) {
            for ($i = 1; $i <= $spacePageCount; $i++) {
                $allLinks[] = [
                    'loc' => Helper::baseUrl('site-maps/spaces-' . $i . '.xml')
                ];
            }
        }

        if ($coursesPageCount > 0) {
            for ($i = 1; $i <= $coursesPageCount; $i++) {
                $allLinks[] = [
                    'loc' => Helper::baseUrl('site-maps/courses-' . $i . '.xml')
                ];
            }
        }

        if ($lessonsPageCount > 0) {
            for ($i = 1; $i <= $lessonsPageCount; $i++) {
                $allLinks[] = [
                    'loc' => Helper::baseUrl('site-maps/lessons-' . $i . '.xml')
                ];
            }
        }

        return $allLinks;
    }

    public function addSeoIndexTag($routeName)
    {
        if (!$this->isPublicCommunity()) {
            echo '<meta name="robots" content="noindex, nofollow">';
            return;
        }

        // let's add no index for profiles
        if (in_array($routeName, ['user_profile', 'admin'])) {
            echo '<meta name="robots" content="noindex, nofollow">';
            return;
        }
    }

    private function isPublicCommunity()
    {
        $settings = Helper::generalSettings();
        $accessLevel = Arr::get($settings, 'access.acess_level');
        return $accessLevel === 'public';
    }

    private function renderStyleSheet($requestMap = '')
    {
        header('Content-Type: application/xml; charset=UTF-8');

        if ($requestMap == 'style-index.xsl') {
            echo $this->getIndexSitemapStylesheet();
        } else {
            echo $this->getSitemapStylesheet();
        }

        exit;
    }

    private function getSitemapStylesheet()
    {
        $css = (new \WP_Sitemaps_Stylesheet())->get_stylesheet_css();
        $title = esc_xml('FluentCommunity XML Sitemap');
        $description = esc_xml('This XML Sitemap is generated by FluentCommunity to make your community feeds, spaces, courses, lessons more visible for search engines.');
        $learn_more = \sprintf(
            '<a href="%s">%s</a>',
            esc_url('https://www.fluentcommunity.co/docs/community-seo-sitemaps'),
            esc_xml('Learn more about FluentCommunity Sitemaps.')
        );

        $allIndex = \sprintf(
            '<a href="%s">%s</a>',
            esc_url(Helper::baseUrl('site-maps/index.xml')),
            esc_xml('View all the Community Sitemaps.')
        );

        $text = \sprintf(
            esc_xml('Number of URLs in this XML Sitemap: %s.'),
            '<xsl:value-of select="count( sitemap:urlset/sitemap:url )" />'
        );

        $lang = get_language_attributes('html');
        $url = esc_xml('URL');
        $lastmod = esc_xml('Last Modified');
        $changefreq = esc_xml('Change Frequency');
        $priority = esc_xml('Priority');

        $xsl_content = <<<XSL
<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet
		version="1.0"
		xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
		xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
		exclude-result-prefixes="sitemap"
		>

	<xsl:output method="html" encoding="UTF-8" indent="yes" />

	<!--
	  Set variables for whether lastmod, changefreq or priority occur for any url in the sitemap.
	  We do this up front because it can be expensive in a large sitemap.
	  -->
	<xsl:variable name="has-lastmod"    select="count( /sitemap:urlset/sitemap:url/sitemap:lastmod )"    />
	<xsl:variable name="has-changefreq" select="count( /sitemap:urlset/sitemap:url/sitemap:changefreq )" />
	<xsl:variable name="has-priority"   select="count( /sitemap:urlset/sitemap:url/sitemap:priority )"   />

	<xsl:template match="/">
		<html {$lang}>
			<head>
				<title>{$title}</title>
				<style>
					{$css}
				</style>
			</head>
			<body>
				<div id="sitemap">
					<div id="sitemap__header">
						<h1>{$title}</h1>
						<p>{$description}</p>
						<p>{$learn_more}</p>
						<p>{$allIndex}</p>
					</div>
					<div id="sitemap__content">
						<p class="text">{$text}</p>
						<table id="sitemap__table">
							<thead>
								<tr>
									<th class="loc">{$url}</th>
									<xsl:if test="\$has-lastmod">
										<th class="lastmod">{$lastmod}</th>
									</xsl:if>
									<xsl:if test="\$has-changefreq">
										<th class="changefreq">{$changefreq}</th>
									</xsl:if>
									<xsl:if test="\$has-priority">
										<th class="priority">{$priority}</th>
									</xsl:if>
								</tr>
							</thead>
							<tbody>
								<xsl:for-each select="sitemap:urlset/sitemap:url">
									<tr>
										<td class="loc"><a href="{sitemap:loc}"><xsl:value-of select="sitemap:loc" /></a></td>
										<xsl:if test="\$has-lastmod">
											<td class="lastmod"><xsl:value-of select="sitemap:lastmod" /></td>
										</xsl:if>
										<xsl:if test="\$has-changefreq">
											<td class="changefreq"><xsl:value-of select="sitemap:changefreq" /></td>
										</xsl:if>
										<xsl:if test="\$has-priority">
											<td class="priority"><xsl:value-of select="sitemap:priority" /></td>
										</xsl:if>
									</tr>
								</xsl:for-each>
							</tbody>
						</table>
					</div>
				</div>
			</body>
		</html>
	</xsl:template>
</xsl:stylesheet>

XSL;


        return $xsl_content;
    }

    private function getIndexSitemapStylesheet()
    {
        $css = (new \WP_Sitemaps_Stylesheet())->get_stylesheet_css();
        $title = esc_xml('FluentCommunity XML Sitemap');
        $description = esc_xml('This XML Sitemap is generated by FluentCommunity to make your community feeds, spaces, courses, lessons more visible for search engines.');
        $learn_more = \sprintf(
            '<a href="%s">%s</a>',
            esc_url('https://www.fluentcommunity.co/docs/community-seo-sitemaps'),
            esc_xml('Learn more about FluentCommunity Sitemaps.')
        );

        $text = \sprintf(
            esc_xml('Number of URLs in this XML Sitemap: %s.'),
            '<xsl:value-of select="count( sitemap:sitemapindex/sitemap:sitemap )" />'
        );

        $lang = get_language_attributes('html');
        $url = 'URL';
        $lastmod = 'Last Modified';

        $xsl_content = <<<XSL
<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet
		version="1.0"
		xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
		xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
		exclude-result-prefixes="sitemap"
		>

	<xsl:output method="html" encoding="UTF-8" indent="yes" />

	<!--
	  Set variables for whether lastmod occurs for any sitemap in the index.
	  We do this up front because it can be expensive in a large sitemap.
	  -->
	<xsl:variable name="has-lastmod" select="count( /sitemap:sitemapindex/sitemap:sitemap/sitemap:lastmod )" />

	<xsl:template match="/">
		<html {$lang}>
			<head>
				<title>{$title}</title>
				<style>
					{$css}
				</style>
			</head>
			<body>
				<div id="sitemap">
					<div id="sitemap__header">
						<h1>{$title}</h1>
						<p>{$description}</p>
						<p>{$learn_more}</p>
					</div>
					<div id="sitemap__content">
						<p class="text">{$text}</p>
						<table id="sitemap__table">
							<thead>
								<tr>
									<th class="loc">{$url}</th>
									<xsl:if test="\$has-lastmod">
										<th class="lastmod">{$lastmod}</th>
									</xsl:if>
								</tr>
							</thead>
							<tbody>
								<xsl:for-each select="sitemap:sitemapindex/sitemap:sitemap">
									<tr>
										<td class="loc"><a href="{sitemap:loc}"><xsl:value-of select="sitemap:loc" /></a></td>
										<xsl:if test="\$has-lastmod">
											<td class="lastmod"><xsl:value-of select="sitemap:lastmod" /></td>
										</xsl:if>
									</tr>
								</xsl:for-each>
							</tbody>
						</table>
					</div>
				</div>
			</body>
		</html>
	</xsl:template>
</xsl:stylesheet>
XSL;


        return $xsl_content;
    }

    public function pushJsonLdData($ldData, $feed, $data)
    {
        $feedUrl = Arr::get($data, 'canonical_url');
        return [
            '@context'             => 'https://schema.org',
            '@type'                => 'DiscussionForumPosting',
            'headline'             => $feed->title,
            '@id'                  => $feedUrl,
            'url'                  => $feedUrl,
            'text'                 => $feed->message,
            'datePublished'        => $feed->created_at->format(DATE_W3C),
            'dateModified'         => $feed->updated_at->format(DATE_W3C),
            'author'               => [
                '@type' => 'Person',
                'name'  => $feed->xprofile ? $feed->xprofile->username : 'unknown',
                'url'   => Helper::baseUrl('u/' . ($feed->xprofile ? $feed->xprofile->username : 'unknown')),
            ],
            'interactionStatistic' => [
                '@type'                => 'InteractionCounter',
                'interactionType'      => 'https://schema.org/Comment',
                'userInteractionCount' => $feed->reactions_count
            ],
            'comment'              => $this->formatLdComments($feed, $feedUrl),
        ];
    }

    public function pushJsonLdDataForCourse($ldData, BaseSpace $course, $data)
    {
        $lessons = [];
        $sections = CourseTopic::where('space_id', $course->id)
            ->orderBy('priority', 'ASC')
            ->with(['lessons' => function ($query) {
                $query->where('status', 'published');
            }])
            ->get();

        foreach ($sections as $section) {
            foreach ($section->lessons as $lesson) {
                $lessons[] = [
                    '@type' => 'Syllabus',
                    'name'  => $lesson->title,
                    'description' => Helper::getHumanExcerpt($lesson->message, 100)
                ];
            }
        }

        return [
            '@context'      => 'https://schema.org',
            '@id'           => $course->getPermalink(),
            '@type'         => 'Course',
            'name'          => $course->title,
            'description'   => Helper::getHumanExcerpt($course->description, 1000),
            'url'           => $course->getPermalink(),
            'publisher'     => [
                '@type' => 'Organization',
                'name'  => get_bloginfo('name'),
                'url'   => home_url()
            ],
            'datePublished' => $course->created_at->format(DATE_W3C),
            'inLanguage'    => get_locale(),
            'syllabusSections' => $lessons,
        ];
    }

    private function formatLdComments($feed, $feedPermalink)
    {
        $comments = Comment::where('post_id', $feed->id)
            ->orderBy('created_at', 'asc')
            ->with([
                'xprofile' => function ($q) {
                    $q->select(ProfileHelper::getXProfilePublicFields());
                }
            ])
            ->get();

        $children = [];

        $formattedComments = [];

        foreach ($comments as $comment) {
            if (!$comment->parent_id) {
                $formattedComments[$comment->id] = [
                    '@type'                => 'Comment',
                    'text'                 => $comment->message,
                    'datePublished'        => $comment->created_at->format(DATE_W3C),
                    'author'               => [
                        '@type' => 'Person',
                        'name'  => $comment->xprofile ? $comment->xprofile->username : 'unknown',
                        'url'   => Helper::baseUrl('u/' . ($feed->xprofile ? $feed->xprofile->username : 'unknown')),
                    ],
                    'interactionStatistic' => [
                        '@type'                => 'InteractionCounter',
                        'interactionType'      => 'http://schema.org/LikeAction',
                        'userInteractionCount' => $comment->reactions_count
                    ],
                    'url'                  => $feedPermalink . '?comment_id=' . $comment->id
                ];
            } else {
                $children[] = $comment;
            }
        }

        foreach ($children as $comment) {
            if (isset($formattedComments[$comment->parent_id])) {
                if (empty($formattedComments[$comment->parent_id]['comment'])) {
                    $formattedComments[$comment->parent_id]['comment'] = [];
                }
                $formattedComments[$comment->parent_id]['comment'][] = [
                    '@type'                => 'Comment',
                    'text'                 => $comment->message,
                    'datePublished'        => $comment->created_at->format(DATE_W3C),
                    'author'               => [
                        '@type' => 'Person',
                        'name'  => $comment->xprofile ? $comment->xprofile->username : 'unknown',
                        'url'   => Helper::baseUrl('u/' . ($feed->xprofile ? $feed->xprofile->username : 'unknown'))
                    ],
                    'interactionStatistic' => [
                        '@type'                => 'InteractionCounter',
                        'interactionType'      => 'http://schema.org/LikeAction',
                        'userInteractionCount' => $comment->reactions_count
                    ],
                    'url'                  => $feedPermalink . '?comment_id=' . $comment->id
                ];
            }
        }

        return array_values($formattedComments);
    }
}
