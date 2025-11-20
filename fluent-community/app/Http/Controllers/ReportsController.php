<?php

namespace FluentCommunity\App\Http\Controllers;

use FluentCommunity\App\Services\Analytics\AnalyticsService;
use FluentCommunity\Framework\Http\Request\Request;

class ReportsController extends Controller
{
    public function overview(Request $request)
    {

        $startDate = $request->getSafe('start_date', 'sanitize_text_field', gmdate('Y-m-d', strtotime('-30 days')));
        $endDate = $request->getSafe('end_date', 'sanitize_text_field', gmdate('Y-m-d'));

        $data = AnalyticsService::create()->getOverview($startDate, $endDate);

        return [
            'data' => $data
        ];
    }

}
