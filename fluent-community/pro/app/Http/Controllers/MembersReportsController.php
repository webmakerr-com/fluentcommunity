<?php

namespace FluentCommunityPro\App\Http\Controllers;

use FluentCommunityPro\App\Services\Analytics\AnalyticsService;
use FluentCommunity\Framework\Http\Request\Request;

class MembersReportsController extends Controller
{

    public function widget(Request $request)
    {
        list($startDate, $endDate) = $this->getDateRanges($request);

        return [
            'data' => AnalyticsService::create()->members($startDate, $endDate)->getMemberWidget()
        ];
    }

    public function activity(Request $request)
    {
        list($startDate, $endDate) = $this->getDateRanges($request);

        return [
            'data' => AnalyticsService::create()->members($startDate, $endDate)->getActivity()
        ];
    }

    public function getTopMembers(Request $request)
    {
        list($startDate, $endDate) = $this->getDateRanges($request);

        return [
            'data' => AnalyticsService::create()->members($startDate, $endDate)->getTopMembers()
        ];
    }

    public function topPostStarter(Request $request)
    {
        list($startDate, $endDate) = $this->getDateRanges($request);

        return [
            'data' => AnalyticsService::create()->members($startDate, $endDate)->topPostStarter()
        ];
    }

    public function topCommenters(Request $request)
    {
        list($startDate, $endDate) = $this->getDateRanges($request);

        return [
            'data' => AnalyticsService::create()->members($startDate, $endDate)->topCommenters()
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
