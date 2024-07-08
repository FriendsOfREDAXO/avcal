<?php
class avcal
{
    private string $mode = 'view';
    private int $object_id;
    private string $date;
    private int $year;
    private int $month;
    private int $day;
    private array $booked_dates = [];
    private array $options = [
        'week_start' => 1,
        'base_link' => null,
        'mark_today' => true,
        'today_date_class' => 'today',
        'mark_passed' => false,
        'passed_date_class' => 'passed',
        'booked_date_class' => 'booked',
        'table_six_rows' => true,
    ];

    public function __construct(int $object_id = null, string $date = null, int $year = null, int $month = null)
    {
        $this->object_id = $object_id ?? 0;

        if (is_null($year) || is_null($month)) {
            if (!is_null($date)) {
                $this->date = date('Y-m-d', strtotime($date));
                $this->year = (int)date('Y', strtotime($date));
                $this->month = (int)date('m', strtotime($date));
                $this->day = (int)date('d', strtotime($date));
            } else {
                $this->date = date('Y-m-d');
                $this->year = (int)date('Y');
                $this->month = (int)date('m');
                $this->day = (int)date('d');
            }
        } else {
            $this->year = $year;
            $this->month = $month;
        }
    }

    public function setEditMode(): void
    {
        $this->mode = 'edit';
        $this->options['base_link'] = 'index.php?page=avcal/calendar&amp;object_id=' . $this->object_id;
    }

    public function setOption(string $key, string $value): bool
    {
        if (array_key_exists($key, $this->options)) {
            $this->options[$key] = $value;
            return true;
        }
        return false;
    }

    public function getOption(string $key): string
    {
        return $this->options[$key] ?? '';
    }

    private function getDayOfWeek(int $date): int
    {
        $day_of_week = (int)date('N', $date);
        if (!is_numeric($day_of_week)) {
            $day_of_week = (int)date('w', $date);
            if ($day_of_week == 0) {
                $day_of_week = 7;
            }
        }
        return $day_of_week;
    }

    private function setBookedDates(int $year = null, int $month = null): bool
    {
        $year = $year ?? $this->year;
        $month = $month ?? $this->month;

        if ($month > 12) {
            $month -= 12;
            $year += 1;
        } elseif ($month < 1) {
            $month += 12;
            $year -= 1;
        }

        $sql = rex_sql::factory();
        $sql->setTable(rex::getTablePrefix() . 'avcal');
        $sql->setWhere("object_id = {$this->object_id} AND YEAR(booked_day) = {$year} AND MONTH(booked_day) = {$month}");
        $sql->select('booked_day, state');
        if ($sql->getRows() > 0) {
            $dates = [];
            for ($i = 0; $i < $sql->getRows(); $i++) {
                $dates[$sql->getValue('booked_day')] = $sql->getValue('state');
                $sql->next();
            }
            $this->booked_dates = $dates;
            return true;
        }
        return false;
    }

    public function getMonthView(int $year = null, int $month = null, string $calendar_class = 'calendar'): string
    {
        $year = $year ?? $this->year;
        $month = $month ?? str_pad((string)$this->month, 2, '0', STR_PAD_LEFT);

        if ($month > 12) {
            $month = str_pad((string)($month - 12), 2, '0', STR_PAD_LEFT);
            $year += 1;
        } elseif ($month < 1) {
            $month = str_pad((string)($month + 12), 2, '0', STR_PAD_LEFT);
            $year -= 1;
        }

        $this->setBookedDates($year, $month);
        $week_start = (int)$this->getOption('week_start');
        $month_start_date = strtotime("{$year}-{$month}-01");
        $first_day_falls_on = $this->getDayOfWeek($month_start_date);
        $days_in_month = (int)date('t', $month_start_date);
        $month_end_date = strtotime("{$year}-{$month}-{$days_in_month}");
        $start_week_offset = $first_day_falls_on - $week_start;
        $prepend = ($start_week_offset < 0) ? 7 - abs($start_week_offset) : $start_week_offset;
        $last_day_falls_on = $this->getDayOfWeek($month_end_date);

        $formatter = new IntlDateFormatter('de_DE', IntlDateFormatter::FULL, IntlDateFormatter::FULL);
        $formatter->setPattern('MMMM yyyy');

        $output = '<table class="' . $calendar_class . '" cellspacing="0">' . "\n";
        $output .= '<caption>' . ucfirst($formatter->format($month_start_date)) . '</caption>' . "\n";

        $col = '';
        $th = '';
        for ($i = 1, $j = $week_start, $t = (3 + $week_start) * 86400; $i <= 7; $i++, $j++, $t += 86400) {
            $localized_day_name = datefmt_create('de_DE', IntlDateFormatter::FULL, IntlDateFormatter::FULL, 'UTC', IntlDateFormatter::TRADITIONAL, 'cccc')->format($t);
            $day_name = strtolower(date('l', $t));
            $col .= '<col class="' . $day_name . '" />';
            $th .= '<th scope="col" title="' . ucfirst($localized_day_name) . '">' . strtoupper($localized_day_name[0]) . '</th>';
            $j = ($j == 7) ? 0 : $j;
        }

        $output .= $col . "\n";

        $headstyle = '';
        if (rex::isBackend()) {
            $headstyle = ' class="bg-primary"';
        }

        $output .= '<thead' . $headstyle . '>' . "\n";
        $output .= '<tr>';
        $output .= $th;
        $output .= '</tr>' . "\n";
        $output .= '</thead>' . "\n";

        $output .= '<tbody>' . "\n";
        $output .= '<tr>';

        $rows = 1;

        for ($i = 1; $i <= $prepend; $i++) {
            $output .= '<td class="pad">&nbsp;</td>';
        }

        for ($day = 1, $cell = $prepend + 1; $day <= $days_in_month; $day++, $cell++) {
            if ($cell == 1 && $day != 1) {
                $rows++;
                $output .= '<tr>';
            }

            $day = str_pad((string)$day, 2, '0', STR_PAD_LEFT);
            $day_date = "{$year}-{$month}-{$day}";

            if ($this->getOption('mark_today') && $day_date == date('Y-m-d')) {
                $classes[] = $this->getOption('today_date_class');
            }
            if ($this->getOption('mark_passed') && $day_date < date('Y-m-d')) {
                $classes[] = $this->getOption('passed_date_class');
            }
            if (is_array($this->booked_dates) && array_key_exists($day_date, $this->booked_dates)) {
                $classes[] = 'booked-' . $this->booked_dates[$day_date];
            }

            $day_class = isset($classes) ? ' class="' . implode(' ', $classes) . '"' : '';
            $output .= '<td' . $day_class . ' title="' . ucwords(datefmt_create('de_DE', IntlDateFormatter::FULL, IntlDateFormatter::FULL, 'UTC', IntlDateFormatter::TRADITIONAL, 'EEEE, d. MMMM yyyy')->format(strtotime($day_date))) . '">';
            unset($day_class, $classes);

            if ($this->mode == 'edit') {
                $output .= '<a href="' . $this->getOption('base_link') . '&amp;date=' . $day_date . '">' . (int)$day . '</a>';
            } else {
                $output .= (int)$day;
            }

            $output .= '</td>';

            if ($cell == 7) {
                $output .= '</tr>' . "\n";
                $cell = 0;
            }
        }

        if ($cell > 1) {
            for ($i = $cell; $i <= 7; $i++) {
                $output .= '<td class="pad">&nbsp;</td>';
            }
            $output .= '</tr>' . "\n";
        }

        if ($this->getOption('table_six_rows') && $rows <= 5) {
            $output .= '<tr class="pad-row">';
            $output .= str_repeat('<td class="pad">&nbsp;</td>', 7);
            $output .= '</tr>' . "\n";
        }

        $output .= '</tbody>' . "\n";
        $output .= '</table>' . "\n";

        return $output;
    }

    public function getPeriod(int $view = 1, int $year = null, int $month = null): string
    {
        $year = $year ?? $this->year;
        $month = $month ?? $this->month;
        $view = max(1, $view);

        $start_date = gmmktime(0, 0, 0, $month, 1, $year);
        $end_date = gmmktime(0, 0, 0, $month + $view - 1, 1, $year);

        $formatter = new IntlDateFormatter('de_DE', IntlDateFormatter::FULL, IntlDateFormatter::FULL);
        $formatter->setPattern('MMMM yyyy');

        $date_txt = $start_date < $end_date
            ? $formatter->format($start_date) . ' - ' . $formatter->format($end_date)
            : $formatter->format($start_date);

        return '<h3 class="period">' . $date_txt . '</h3>' . "\n";
    }

    public function getLegend(?array $labels = null): string
    {
        if (is_array($labels) && count($labels) > 0) {
            $output = '<ul class="legend">';
            foreach ($labels as $state => $label) {
                if ($label != '') {
                    $output .= '<li class="booked-' . $state . '">' . $label . '</li>';
                }
            }
            $output .= '</ul>' . "\n";
            return $output;
        }
        return '';
    }

    public function getNav(int $view = 1, int $year = null, int $month = null): string
    {
        $year = $year ?? $this->year;
        $month = $month ?? $this->month;

        $prev_start_date = gmmktime(0, 0, 0, $month - $view, 1, $year);
        $prev_end_date = gmmktime(0, 0, 0, $month - 1, 1, $year);
        $curr_start_date = gmmktime(0, 0, 0, $month, 1, $year);
        $curr_end_date = gmmktime(0, 0, 0, $month + $view - 1, 1, $year);
        $next_start_date = gmmktime(0, 0, 0, $month + $view, 1, $year);
        $next_end_date = gmmktime(0, 0, 0, $month + $view + $view - 1, 1, $year);

        $formatter = new IntlDateFormatter('de_DE', IntlDateFormatter::FULL, IntlDateFormatter::FULL);
        $formatter->setPattern('MMMM yyyy');

        $prev_date_txt = $formatter->format($prev_start_date) . ' - ' . $formatter->format($prev_end_date);
        $curr_date_txt = $formatter->format($curr_start_date) . ' - ' . $formatter->format($curr_end_date);
        $next_date_txt = $formatter->format($next_start_date) . ' - ' . $formatter->format($next_end_date);

        if ($this->mode == 'edit') {
            $prev_link = $this->getOption('base_link') . '&amp;date=' . date('Y-m-d', $prev_start_date);
            $next_link = $this->getOption('base_link') . '&amp;date=' . date('Y-m-d', $next_start_date);
        } else {
            $now_start_date = gmmktime(0, 0, 0, date('m'), 1, date('Y'));
            if ($this->mode == 'view' && $prev_end_date < $now_start_date) {
                $prev_link = false;
            } else {
                $prev_link = rex_getUrl(rex_article::getCurrentId(), rex_clang::getCurrentId(), ['date' => date('Y-m-d', $prev_start_date)]);
            }
            $next_link = rex_getUrl(rex_article::getCurrentId(), rex_clang::getCurrentId(), ['date' => date('Y-m-d', $next_start_date)]);
        }

        $output = '<ul class="prev_next"><li class="prev">';
        if ($prev_link === false) {
            $output .= '&nbsp;';
        } else {
            $output .= '<a href="' . $prev_link . '" title="' . $prev_date_txt . '">' . $prev_date_txt . '</a>';
        }
        $output .= '</li><li class="curr">';
        $output .= '<span>' . $curr_date_txt . '</span>';
        $output .= '</li><li class="next">';
        $output .= '<a href="' . $next_link . '" title="' . $next_date_txt . '">' . $next_date_txt . '</a>';
        $output .= '</li></ul>' . "\n";

        return $output;
    }
}
?>
