<?php

namespace FluentCommunityPro\App\Modules\SeoSiteMap;

use FluentCommunity\App\Functions\Utility;
use FluentCommunity\App\Models\Feed;
use FluentCommunity\App\Services\Helper;
use FluentCommunity\Modules\Course\Model\Course;
use FluentCommunity\Modules\Course\Model\CourseLesson;

class SitemapQuery
{
    protected $perPage;
    protected $pageNum;

    public function __construct($perPage = 1000, $page_num = 1)
    {
        $this->perPage = $perPage;
        $this->pageNum = $page_num;
    }

    public function getFeeds($withIndex = true)
    {
        return Utility::getFromCache('sitemap:feeds:' . $this->pageNum, function () use ($withIndex) {
            $allFeeds = Feed::query()->where('status', 'published')
                ->with(['space'])
                ->select(['id', 'space_id', 'updated_at', 'created_at', 'slug'])
                ->byUserAccess(null)
                ->orderBy('created_at', 'ASC')
                ->offset(($this->pageNum - 1) * $this->perPage)
                ->limit($this->perPage)
                ->get();

            $urls = [];

            if ($withIndex && $this->pageNum == 1) {
                $urls[] = [
                    'loc' => Helper::baseUrl()
                ];
            }

            foreach ($allFeeds as $feed) {
                $urls[] = [
                    'loc'     => $feed->permalink,
                    'lastmod' => $feed->updated_at->format('Y-m-d\TH:i:sP')
                ];
            }

            return $urls;
        });
    }

    public function getSpaces($withIndex = true)
    {
        return Utility::getFromCache('sitemap:spaces:' . $this->pageNum, function () use ($withIndex) {
            $allSpaces = \FluentCommunity\App\Models\Space::query()
                ->where('status', 'published')
                ->whereIn('privacy', ['public', 'private'])
                ->select(['id', 'updated_at', 'type', 'created_at', 'slug'])
                ->orderBy('created_at', 'ASC')
                ->offset(($this->pageNum - 1) * $this->perPage)
                ->limit($this->perPage)
                ->get();

            $urls = [];

            if ($withIndex && $this->pageNum == 1) {
                $urls[] = [
                    'loc' => Helper::baseUrl('discover/spaces')
                ];
            }

            foreach ($allSpaces as $space) {
                $urls[] = [
                    'loc'     => $space->getPermalink(),
                    'lastmod' => $space->updated_at->format('Y-m-d\TH:i:sP')
                ];
            }

            return $urls;
        });
    }

    public function getCourses($withIndex = true)
    {
        return Utility::getFromCache('sitemap:courses:' . $this->pageNum, function () use ($withIndex) {
            $allCourses = Course::query()
                ->where('status', 'published')
                ->whereIn('privacy', ['public', 'private'])
                ->select(['id', 'updated_at', 'type', 'created_at', 'slug'])
                ->orderBy('created_at', 'ASC')
                ->offset(($this->pageNum - 1) * $this->perPage)
                ->limit($this->perPage)
                ->get();

            $urls = [];

            if ($withIndex && $this->pageNum == 1) {
                $urls[] = [
                    'loc' => Helper::baseUrl('courses')
                ];
            }

            foreach ($allCourses as $course) {
                $urls[] = [
                    'loc'     => $course->getPermalink(),
                    'lastmod' => $course->updated_at->format('Y-m-d\TH:i:sP')
                ];
            }

            return $urls;
        });
    }

    public function getLessons()
    {
        return Utility::getFromCache('sitemap:lessons:' . $this->pageNum, function () {
            $lessons = CourseLesson::query()
                ->whereHas('course', function ($query) {
                    return $query->where('status', 'published')
                        ->where('privacy', 'public');
                })
                ->with(['course'])
                ->where('status', 'published')
                ->select(['id', 'updated_at', 'type', 'space_id', 'created_at', 'slug'])
                ->orderBy('created_at', 'ASC')
                ->offset(($this->pageNum - 1) * $this->perPage)
                ->limit($this->perPage)
                ->get();

            $urls = [];

            foreach ($lessons as $lesson) {
                $urls[] = [
                    'loc'     => $lesson->getPermalink(),
                    'lastmod' => $lesson->updated_at->format('Y-m-d\TH:i:sP')
                ];
            }

            return $urls;
        });
    }

    public function getMaxPageNums($objectType)
    {
        switch ($objectType) {
            case 'feeds':
                $total = Feed::query()->where('status', 'published')->byUserAccess(null)->count();
                break;
            case 'spaces':
                $total = \FluentCommunity\App\Models\Space::query()
                    ->where('status', 'published')
                    ->whereIn('privacy', ['public', 'private'])
                    ->count();
                break;
            case 'courses':
                if (!Helper::isFeatureEnabled('course_module')) {
                    return 0;
                }
                $total = Course::query()
                    ->where('status', 'published')
                    ->whereIn('privacy', ['public', 'private'])
                    ->count();
                break;
            case 'lessons':
                if (!Helper::isFeatureEnabled('course_module')) {
                    return 0;
                }
                $total = CourseLesson::query()
                    ->whereHas('course', function ($query) {
                        $query->where('status', 'published')
                            ->where('privacy', 'public');
                    })
                    ->where('status', 'published')
                    ->count();
                break;
            default:
                return 0;
        }

        return $total > 0 ? ceil($total / $this->perPage) : 0;
    }
}
