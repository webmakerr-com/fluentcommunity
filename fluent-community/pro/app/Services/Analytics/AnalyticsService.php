<?php

namespace FluentCommunityPro\App\Services\Analytics;

use \DateTime;
use FluentCommunity\Framework\Support\Arr;
use FluentCommunity\App\Models\XProfile;
use FluentCommunity\App\Models\Comment;
use FluentCommunity\App\Models\Model;
use FluentCommunity\App\Models\Space;
use FluentCommunity\App\Models\Feed;
use FluentCommunityPro\App\Core\App;

class AnalyticsService
{
    /**
     * Get chart statistics for the specified date range.
     *
     * This method retrieves the count of posts, comments, and members for each date
     * within the specified date range. It generates an array of statistics where each
     * entry contains the date and the corresponding counts of posts, comments, and members.
     *
     * @param string $startDate The start date for the data retrieval in 'Y-m-d' format.
     * @param string $endDate The end date for the data retrieval in 'Y-m-d' format.
     * @return array An array of associative arrays, each containing 'date', 'posts', 'comments', and 'members' keys.
     * @throws \DateMalformedStringException
     * @throws \DateMalformedIntervalStringException
     * @throws \DateMalformedPeriodStringException
     */
    public function getChartStatistics($startDate, $endDate, $activity = 'posts', $field = 'created_at', $query = null)
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

        $ModelQuery = (new $providers[$activity]['model'])->query()->when($query, $query);


        $data = $this
            ->reports($startDate, $endDate)
            ->getModelDataBySequence(
                $ModelQuery,
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


    /**
     * Get widget counts with comparison to the previous period.
     *
     * This method calculates the total count of records for the specified date range
     * and compares it with the count from the previous period (one month before).
     * It returns the total count and the percentage difference between the two periods.
     *
     * @param array $dateRanges An associative array containing 'start_date' and 'end_date' keys with formatted date-time values.
     * @param \FluentCommunity\App\Models\Model $model The model instance to query.
     * @return array An associative array containing 'total_records' and 'comparison' keys.
     * @throws \DateMalformedStringException
     */
    public function getWidgetCountsWithComparison($dateRanges, $model, $title, $field = 'created_at')
    {
        $currentStart = DateTime::createFromFormat('Y-m-d H:i:s', $dateRanges['start_date']);
        $currentEnd = DateTime::createFromFormat('Y-m-d H:i:s', $dateRanges['end_date']);

        $previousStart = (clone $currentStart)->modify('-1 month');
        $previousEnd = (clone $currentEnd)->modify('-1 month');

        $currentCount = $this->getCountByDateRange($currentStart, $currentEnd, $model, $field);
        $previousCount = $this->getCountByDateRange($previousStart, $previousEnd, $model, $field);

        $comparison = $this->calculatePercentageDifference($currentCount, $previousCount);

        return [
            'total_records' => $currentCount,
            'comparison'    => $comparison,
            'title'         => $title,
        ];
    }

    /**
     * Calculate the percentage difference between the current and previous counts.
     *
     * This method calculates the percentage difference between the current count and the previous count.
     * If the previous count is zero, it returns '+100' if the current count is greater than zero, otherwise '0'.
     * The percentage difference is calculated as ((currentCount - previousCount) / previousCount) * 100.
     * The result is rounded to the nearest integer and prefixed with a '+' if the difference is positive.
     *
     * @param int $currentCount The count of records for the current period.
     * @param int $previousCount The count of records for the previous period.
     * @return string The percentage difference, prefixed with '+' if positive.
     */
    protected function calculatePercentageDifference($currentCount, $previousCount)
    {
        if ($previousCount == 0) {
            return $currentCount > 0 ? '+100' : '0';
        }

        $difference = $currentCount - $previousCount;
        $percentageDifference = ($difference / $previousCount) * 100;

        return ($difference >= 0 ? '+' : '') . round($percentageDifference);
    }

    /**
     * Get the count of records within a specified date range.
     *
     * This method retrieves the total count of records for a given model within the specified
     * date range. It queries the model's `created_at` field to count the records that fall
     * between the provided start and end dates.
     *
     * @param DateTime $startDate The start date for the data retrieval.
     * @param DateTime $endDate The end date for the data retrieval.
     * @param Model $model The model instance to query.
     * @return int The total count of records within the specified date range.
     */
    protected function getCountByDateRange(DateTime $startDate, DateTime $endDate, $model, $field = 'created_at')
    {
        return $model
            ->whereBetween(
                $field,
                [$startDate->format('Y-m-d H:i:s'), $endDate->format('Y-m-d H:i:s')]
            )
            ->count()
        ;
    }


    /**
     * Create a new instance of the class.
     *
     * This static method creates and returns a new instance of the class it is called on.
     * It uses the `static` keyword to ensure that the correct class type is instantiated,
     * even if the method is called on a subclass.
     *
     * @return static A new instance of the class.
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Generate time segments for a day.
     *
     * This method generates an array of time segments for a 24-hour day, divided into the specified number of segments.
     * Each segment is represented as a string in the format 'start_time-end_time'. The method ensures that the number
     * of segments divides 24 hours perfectly by adjusting to the closest higher divisor if necessary.
     *
     * @param int $segmentsCount The number of segments to divide the day into. Must be a positive integer.
     * @param bool $is24HourFormat Whether to use 24-hour format for the time segments. Defaults to false (12-hour format).
     * @return array An array of time segments, each represented as a string in the format 'start_time-end_time'.
     * @throws \InvalidArgumentException|\DateMalformedStringException If the segments count is less than 1.
     */
    public function generateTimeSegments($segmentsCount, $is24HourFormat = false)
    {

        // Validate input
        if (($segmentsCount < 1) || ($segmentsCount > 24)) {
            throw new \InvalidArgumentException('Segment count must be at least 1.');
        }

        // Ensure the segments count divides 24 hours perfectly
        $divisors = [1, 2, 3, 4, 6, 8, 12, 24]; // Divisors of 24 hours

        if (!in_array($segmentsCount, $divisors)) {
            // Find the closest higher divisor if possible
            foreach ($divisors as $divisor) {
                if ($divisor > $segmentsCount) {
                    $segmentsCount = $divisor;
                    break;
                }
            }
        }

        $hoursPerSegment = 24 / $segmentsCount;
        $timeSegments = [];
        $startTime = new DateTime('00:00');

        for ($i = 0; $i < $segmentsCount; $i++) {

            $endTime = clone $startTime;
            $endTime->modify("+{$hoursPerSegment} hours")->modify('-1 minute');  // End time is just before the next segment starts


            $startFormat = 'g:i A';
            $endFormat = 'g:i A';

            if ($is24HourFormat) {
                $startFormat = 'H:i';
                $endFormat = 'H:i';
            }

            $formattedStartTime = $startTime->format($startFormat);
            $formattedEndTime = $endTime->format($endFormat);
            $timeSegments[] = "{$formattedStartTime}-{$formattedEndTime}";

            // Set start time to the beginning of the next segment
            $startTime = $endTime->modify('+1 minute');
        }

        return $timeSegments;
    }

    /**
     * Generate an ordered list of weekdays starting from a specified day.
     *
     * This method generates an array of weekdays starting from the specified day.
     * The input day can be provided in full form (e.g., 'Monday') or as a three-letter abbreviation (e.g., 'Mon').
     * The method normalizes the input to ensure it matches the array keys and reorders the weekdays accordingly.
     *
     * @param string $startDay The day to start the week from. Defaults to 'Monday'.
     * @return array An array of weekdays starting from the specified day.
     * @throws \InvalidArgumentException If the provided start day is invalid.
     */
    public function generateWeekdays($startDay = 'Monday')
    {
        // Define all weekdays in full form for output consistency
        $weekdays = [
            'Monday',
            'Tuesday', 
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday'
        ];

        // Normalize the input to ensure it matches array keys
        $startDay = ucfirst(strtolower($startDay));
        if (strlen($startDay) == 3) {  // If the abbreviation is provided
            $startDay = DateTime::createFromFormat('D', $startDay)->format('l');
        }

        // Validate start day
        if (!in_array($startDay, $weekdays)) {
            throw new \InvalidArgumentException('Invalid start day provided.');
        }

        // Find the index of the start day
        $startIndex = array_search($startDay, $weekdays);

        // Reorder the array so it starts from the given start day
        $orderedWeekdays = array_merge(array_slice($weekdays, $startIndex), array_slice($weekdays, 0, $startIndex));

        return $orderedWeekdays;
    }

    public function getWeekDaysColumns()
    {
        $columns = [
            [
                'prop' => 'time_range',
                'label' => __('Time Range', 'fluent-community-pro'),
            ],
            [
                'prop' => 'Monday',
                'label' => __('Mon', 'fluent-community-pro'),
            ],
            [
                'prop' => 'Tuesday',
                'label' => __('Tue', 'fluent-community-pro'),
            ],
            [
                'prop' => 'Wednesday',
                'label' => __('Wed', 'fluent-community-pro'),
            ],
            [
                'prop' => 'Thursday',
                'label' => __('Thu', 'fluent-community-pro'),
            ],
            [
                'prop' => 'Friday',
                'label' => __('Fri', 'fluent-community-pro'),
            ],
            [
                'prop' => 'Saturday',
                'label' => __('Sat', 'fluent-community-pro'),
            ],
            [
                'prop' => 'Sunday',
                'label' => __('Sun', 'fluent-community-pro'),
            ],
        ];

        return $columns;
    }

    public function reports($startDate, $endDate): ReportGenerator
    {
        return new ReportGenerator(
            $startDate,
            $endDate,
            App::getInstance('db')
        );
    }

    public function overview($startDate, $endDate)
    {
        return new Overview($this, $startDate, $endDate);
    }

    public function members($startDate, $endDate)
    {
        return new Members($this, $startDate, $endDate);
    }

    public function spaces($startDate, $endDate)
    {
        return new Spaces($this, $startDate, $endDate);
    }
}
