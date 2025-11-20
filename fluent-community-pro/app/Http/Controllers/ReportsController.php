<?php

namespace FluentCommunityPro\App\Http\Controllers;

use FluentCommunityPro\App\Services\Analytics\AnalyticsService;
use FluentCommunity\Framework\Http\Request\Request;

class ReportsController extends Controller
{

    public function getOverviewWidget(Request $request)
    {
        list($startDate, $endDate) = $this->getDateRanges($request);

        return [
            'data' => AnalyticsService::create()->overview($startDate, $endDate)->getWidget()
        ];
    }

    public function activityReport(Request $request)
    {
        // getting last 30 days data
        list( $startDate, $endDate ) = $this->getDateRanges($request);

        $activity = $request->getSafe('activity', 'sanitize_text_field', 'posts');

        return [
            'data' => AnalyticsService::create()->overview($startDate, $endDate)->getActivity($activity)
        ];
    }

    public function popularDayTimeReport(Request $request)
    {
        list( $startDate, $endDate ) = $this->getDateRanges($request);

        return [
            'data' => AnalyticsService::create()->overview($startDate, $endDate)->getPopularDayTime()
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
