<?php

namespace FluentCommunityPro\App\Services\Analytics;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\App\Models\Comment;
use FluentCommunity\App\Models\Space;
use FluentCommunity\App\Models\Feed;

class Overview
{
    protected $provider;

    protected $startDate;

    protected $endDate;

    public function __construct($provider, $startDate = null, $endDate = null)
    {
        $this->provider = $provider;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function getWidget()
    {
        $dateRanges = $this->getDateRanges($this->startDate, $this->endDate);

        return [
            'members'  => $this->provider->getWidgetCountsWithComparison($dateRanges, new XProfile(), __('Members', 'fluent-community-pro')),
            'posts'    => $this->provider->getWidgetCountsWithComparison($dateRanges, new Feed(), __('Posts', 'fluent-community-pro')),
            'comments' => $this->provider->getWidgetCountsWithComparison($dateRanges, new Comment(), __('Comments', 'fluent-community-pro')),
            'spaces'   => $this->provider->getWidgetCountsWithComparison($dateRanges, new Space(), __('Spaces', 'fluent-community-pro'))
        ];
    }

    public function getActivity($activity, $field = 'created_at')
    {
        $providers = [
            'comments' => [
                'model' => Comment::class,
                'title' => __('Comments', 'fluent-community-pro'),
            ],
            'members'  => [
                'model' => XProfile::class,
                'title' => __('Members', 'fluent-community-pro'),
            ],
            'posts'    => [
                'model' => Feed::class,
                'title' => __('Posts', 'fluent-community-pro'),
            ],
            'spaces'   => [
                'model' => Space::class,
                'title' => __('Spaces', 'fluent-community-pro'),
            ],
        ];


        if (!array_key_exists($activity, $providers)) {
            throw new \InvalidArgumentException('Invalid activity type provided.');
        }


        $data = $this
            ->provider->reports($this->startDate, $this->endDate)
            ->getModelDataBySequence(
                new $providers[$activity]['model'](),
                $field
            )
        ;

        $datePeriods = array_keys($data);

        $statistics = [
            'activity' => $activity,
            'title'    => $providers[$activity]['title'],
        ];

        $statisticsData = [];

        foreach ($datePeriods as $date) {
            $statisticsData[] = [
                'date' => $date,
                'data' => Arr::get($data, $date, 0),
            ];
        }

        // failsafe for sorting
        usort($statisticsData, function ($a, $b) {
            return strtotime($a['date']) <=> strtotime($b['date']);
        });

        $statistics['data'] = $statisticsData;


        // the casting is necessary to avoid the error of the type array<LIKE>. make sure the result is array
        return (array)$statistics;
    }

    public function getPopularDayTime()
    {
        $activitiesModels = [Comment::class, Feed::class, Space::class, XProfile::class];
        // Set default start and end dates if not provided
        $startDate = $this->startDate ? new \DateTime($this->startDate) : new \DateTime('last sunday -6 days');
        $endDate = $this->endDate ? new \DateTime($this->endDate) : new \DateTime('last sunday');

        // Define time segments for each day
        $timeSegments = $this->provider->generateTimeSegments(6, false);

        // Define days of the week
        $weekDays = $this->provider->generateWeekdays();

        // Define models to count activities from
        $tableData = [];

        // Initialize the structure for table data
        foreach ($timeSegments as $segment) {
            $row = ['time_range' => $segment];
            foreach ($weekDays as $day) {
                $row[$day] = 0;  // Initialize counts for each day
            }
            $tableData[] = $row;
        }

        // Loop through each day in the date range
        for ($date = clone $startDate; $date <= $endDate; $date->modify('+1 day')) {
            $dayOfWeek = $date->format('l');  // Get the day of the week

            // Loop through each time segment
            foreach ($timeSegments as $index => $segment) {
                list($startHour, $endHour) = explode('-', $segment);

                $startDateTime = clone $date;
                $endDateTime = clone $date;

                $startDateTime->modify($startHour);
                $endDateTime->modify($endHour)->modify('+1 minute');  // Include the last minute

                // Count activities for each model within the time segment
                foreach ($activitiesModels as $model) {
                    $count = $model::whereBetween('created_at', [
                        $startDateTime->format('Y-m-d H:i:s'),
                        $endDateTime->format('Y-m-d H:i:s')
                    ])->count();
                    $tableData[$index][$dayOfWeek] += $count;
                }
            }
        }

        // Prepare columns for the table
        $weekDays = $this->provider->getWeekDaysColumns();

        // Return the table data
        return [
            'columns' => $weekDays,
            'data'    => $tableData
        ];
    }

    public function getDateRanges($startDate, $endDate)
    {
        return [
            'start_date' => gmdate('Y-m-d', strtotime($startDate)) . ' 00:00:00',
            'end_date'   => gmdate('Y-m-d', strtotime($endDate)) . ' 23:59:59'
        ];
    }
}
