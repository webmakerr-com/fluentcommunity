<?php

namespace FluentCommunityPro\App\Http\Controllers;

use FluentCommunityPro\App\Services\Analytics\AnalyticsService;
use FluentCommunity\Framework\Http\Request\Request;
use FluentCommunity\App\Models\Space;

class SpacesReportsController extends Controller
{

    public function widget(Request $request)
    {
        list($startDate, $endDate) = $this->getDateRanges($request);
        $spaceId = $request->getSafe('space_id', 'intval', null);

        return [
            'data' => AnalyticsService::create()->spaces($startDate, $endDate)->getSpaceWidget($spaceId)
        ];
    }

    public function activity(Request $request)
    {
        list($startDate, $endDate) = $this->getDateRanges($request);

        return [
            'data' => AnalyticsService::create()->spaces($startDate, $endDate)->getPostActivity($request->getSafe('space_id', 'intval', null))
        ];
    }

    public function getTopSpaces(Request $request)
    {
        list($startDate, $endDate) = $this->getDateRanges($request);

        return [
            'data' => AnalyticsService::create()->spaces($startDate, $endDate)->getTopSpaces($request->getSafe('space_id', 'intval', null))
        ];
    }

    public function searchSpace(Request $request)
    {
        $search = $request->getSafe('search', 'sanitize_text_field', '');

        $spaces = Space::query()
            ->where('title', 'like', '%%' . $search . '%%')
            ->get(['id', 'title'])
        ;

        return [
            'data' => $spaces
        ];
    }

    private function getDateRanges($request)
    {
        // getting range of last 30 days
        $defaultStartDate = gmdate('Y-m-d', strtotime('-30 days'));
        $defaultEndDate = gmdate('Y-m-d');

        // getting start and end date from user request
        $startDate = $request->getSafe('start_date', 'sanitize_text_field', $defaultStartDate);
        $endDate = $request->getSafe('end_date', 'sanitize_text_field', $defaultEndDate);

        return [$startDate, $endDate];
    }

}
