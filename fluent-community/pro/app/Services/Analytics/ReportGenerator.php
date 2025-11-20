<?php

namespace FluentCommunityPro\App\Services\Analytics;

class ReportGenerator
{

    protected $from;
    protected $to;
    protected $period;
    protected $dbInstance;
    protected $frequency;

    public function __construct($startDate, $endDate, $dbInstance)
    {
        $this->dbInstance = $dbInstance;

        $this->period = $this->generateDatePeriods(
            $this->from = $this->fromDate($startDate),
            $this->to = $this->toDate($endDate),
            $this->frequency = $this->getFrequency($this->from, $this->to)
        );
    }

    public function getModelDataBySequence($modelInstance, $field = 'created_at')
    {
        list($groupBy, $orderBy) = $this->getGroupAndOrder($this->frequency);

        $items = $modelInstance
            ->select($this->prepareSelect($this->dbInstance, $this->frequency))
            ->whereBetween($field, [$this->from->format('Y-m-d'), $this->to->format('Y-m-d')])
            ->groupBy($groupBy)
            ->orderBy($orderBy, 'ASC')
            ->get()
        ;

        return $this->getResult($this->period, $items);
    }

    public function fromDate($date = '-30 days')
    {
        return new \DateTime($date);
    }

    public function toDate($date = '+1 days')
    {
        return new \DateTime($date);
    }

    protected function generateDatePeriods($from, $to, $interval = null)
    {
        return new \DatePeriod($from, new \DateInterval($interval ?: 'P1D'), $to);
    }

    public function getFrequency($from, $to)
    {
        $numDays = $to->diff($from)->format('%a');

        if ($numDays > 62 && $numDays <= 92) {
            return 'P1W';
        }

        if ($numDays > 92) {
            return 'P1M';
        }

        return 'P1D';
    }

    public function prepareSelect($dbInstance, $frequency, $dateField = 'created_at')
    {
        $select = [
            $dbInstance->raw('COUNT(id) AS count'),
            $dbInstance->raw('DATE(' . $dateField . ') AS date')
        ];

        if ($frequency == 'P1W') {
            $select[] = $dbInstance->raw('WEEK(created_at) week');
        } else if ($frequency == 'P1M') {
            $select[] = $dbInstance->raw('MONTH(created_at) month');
        }

        return $select;
    }

    protected function getGroupAndOrder($frequency)
    {
        $orderBy = $groupBy = 'date';

        if ($frequency == 'P1W') {
            $orderBy = $groupBy = 'week';
        }

        if ($frequency == 'P1M') {
            $orderBy = $groupBy = 'month';
        }

        return [$groupBy, $orderBy];
    }

    protected function getDateRanges($period)
    {
        $range = [];

        $formatter = 'basicFormatter';

        if ($this->isMonthly($period)) {
            $formatter = 'monYearFormatter';
        }

        foreach ($period as $date) {
            $date = $this->{$formatter}($date);
            $range[$date] = 0;
        }

        return $range;
    }

    protected function getResult($period, $items)
    {
        $range = $this->getDateRanges($period);

        $formatter = 'basicFormatter';

        if ($this->isMonthly($period)) {
            $formatter = 'monYearFormatter';
        }

        foreach ($items as $item) {
            $date = $this->{$formatter}($item->date);
            $range[$date] = (int)$item->count;
        }

        return $range;
    }

    protected function isMonthly($period)
    {
        return !!$period->getDateInterval()->m;
    }

    protected function basicFormatter($date)
    {
        if (is_string($date)) {
            $date = new \DateTime($date);
        }

        return $date->format('Y-m-d');
    }

    protected function monYearFormatter($date)
    {
        if (is_string($date)) {
            $date = new \DateTime($date);
        }

        return $date->format('M Y');
    }

    public function getDatePeriods($format = 'Y-m-d')
    {
        $dates = [];

        foreach ($this->period as $date) {
            $dates[] = $date->format($format);
        }

        return $dates;
    }
}
