<?php

namespace FriendsOfRedaxo\AvCal;

use DateTimeImmutable;
use IntlDateFormatter;
use rex;
use rex_article;
use rex_clang;
use rex_csrf_token;
use rex_sql;
use rex_url;

/**
 * Availability Calendar main class.
 *
 * Renders a monthly HTML calendar table with booked-day highlighting.
 * Supports view mode (frontend) and edit mode (backend).
 *
 * @author (c) Friends Of REDAXO
 * @license MIT
 */
class Avcal
{
    private string $mode = 'view';
    private int $object_id;
    private int $year;
    private int $month;
    /** @var array<string, string> */
    private array $booked_dates = [];
    /** @var array<string, mixed> */
    private array $options = [
        'week_start'        => 1,
        'base_link'         => null,
        'mark_today'        => true,
        'today_date_class'  => 'today',
        'mark_passed'       => false,
        'passed_date_class' => 'passed',
        'booked_date_class' => 'booked',
        'table_six_rows'    => true,
    ];

    public function __construct(int $object_id = null, string $date = null, int $year = null, int $month = null)
    {
        $this->object_id = $object_id ?? 0;

        if (null === $year || null === $month) {
            $dt = null !== $date
                ? (DateTimeImmutable::createFromFormat('Y-m-d', $date) ?: new DateTimeImmutable())
                : new DateTimeImmutable();

            $this->year  = (int) $dt->format('Y');
            $this->month = (int) $dt->format('m');
        } else {
            $this->year  = $year;
            $this->month = $month;
        }
    }

    public function setEditMode(): void
    {
        $this->mode = 'edit';
        $this->options['base_link'] = 'index.php?page=avcal/calendar&object_id=' . $this->object_id;
    }

    /**
     * @param mixed $value
     */
    public function setOption(string $key, $value): bool
    {
        if (array_key_exists($key, $this->options)) {
            $this->options[$key] = $value;
            return true;
        }
        return false;
    }

    /**
     * @return mixed
     */
    public function getOption(string $key)
    {
        return $this->options[$key] ?? null;
    }

    /**
     * Returns all booked dates for the object's current month as [date => state].
     *
     * @return array<string, string>
     */
    public function getBookedDates(int $year = null, int $month = null): array
    {
        $this->setBookedDates($year ?? $this->year, $month ?? $this->month);
        return $this->booked_dates;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function getDayOfWeek(int $timestamp): int
    {
        // date('N') returns 1 (Monday) through 7 (Sunday) – always a valid int
        return (int) date('N', $timestamp);
    }

    /**
     * Resolves month/year overflow using DateTimeImmutable.
     *
     * @return array{int, int}
     */
    private function resolveYearMonth(int $year, int $month): array
    {
        $dt = (new DateTimeImmutable())->setDate($year, $month, 1);
        return [(int) $dt->format('Y'), (int) $dt->format('m')];
    }

    private function setBookedDates(int $year, int $month): bool
    {
        [$year, $month] = $this->resolveYearMonth($year, $month);

        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('avcal'));
        $sql->setWhere(
            'object_id = :oid AND YEAR(booked_day) = :year AND MONTH(booked_day) = :month',
            [':oid' => $this->object_id, ':year' => $year, ':month' => $month],
        );
        $sql->select('booked_day, state');

        if ($sql->getRows() > 0) {
            $dates = [];
            for ($i = 0; $i < $sql->getRows(); $i++) {
                $dates[(string) $sql->getValue('booked_day')] = (string) $sql->getValue('state');
                $sql->next();
            }
            $this->booked_dates = $dates;
            return true;
        }

        $this->booked_dates = [];
        return false;
    }

    /**
     * Returns the ICU locale string based on the current REDAXO language,
     * e.g. "de_de" -> "de_DE", "en_gb" -> "en_GB".
     */
    private function getLocale(): string
    {
        $lang  = (string) rex::getProperty('lang', 'de_de');
        $parts = explode('_', $lang);
        if (2 === count($parts)) {
            return strtolower($parts[0]) . '_' . strtoupper($parts[1]);
        }
        return 'de_DE';
    }

    // -------------------------------------------------------------------------
    // Public rendering methods
    // -------------------------------------------------------------------------

    /**
     * Renders a single month as an HTML <table>.
     *
     * In edit mode the table gets data-object-id, data-api-url and data-csrf-token
     * attributes that the JS backend.js reads for AJAX booking toggling.
     *
     * @param int|null $year           Override year  (null = use instance year)
     * @param int|null $month          Override month (null = use instance month)
     * @param string   $calendar_class CSS class for the <table> element
     */
    public function getMonthView(int $year = null, int $month = null, string $calendar_class = 'calendar'): string
    {
        $year  = $year  ?? $this->year;
        $month = $month ?? $this->month;

        [$year, $month] = $this->resolveYearMonth($year, $month);

        $this->setBookedDates($year, $month);

        $locale      = $this->getLocale();
        $weekStart   = (int) ($this->getOption('week_start') ?? 1);
        $monthPad    = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $monthStart  = new DateTimeImmutable("{$year}-{$monthPad}-01");
        $daysInMonth = (int) $monthStart->format('t');
        $firstDayDow = $this->getDayOfWeek($monthStart->getTimestamp());
        $prepend     = (($firstDayDow - $weekStart) + 7) % 7;
        $today       = (new DateTimeImmutable())->format('Y-m-d');

        $captionFmt = new IntlDateFormatter($locale, IntlDateFormatter::FULL, IntlDateFormatter::FULL);
        $captionFmt->setPattern('MMMM yyyy');

        // CSRF token embedded as data attribute so JS can include it in requests
        $csrfValue = '';
        $apiUrl    = '';
        if ('edit' === $this->mode) {
            $csrfValue = rex_csrf_token::factory('avcal_booking')->getValue();
            $apiUrl    = rex_url::backendController(['rex-api-call' => 'avcal_booking']);
        }

        // Build <col> + <th> header row
        $col = '';
        $th  = '';
        for ($i = 0, $j = $weekStart, $t = (3 + $weekStart) * 86400; $i < 7; $i++, $j++, $t += 86400) {
            $dayFmt      = datefmt_create($locale, IntlDateFormatter::FULL, IntlDateFormatter::FULL, 'UTC', IntlDateFormatter::TRADITIONAL, 'cccc');
            $dayFullName = $dayFmt ? (string) $dayFmt->format($t) : '';
            $dayEn       = strtolower(date('l', $t));
            $col .= '<col class="' . rex_escape($dayEn) . '" />';
            $th  .= '<th scope="col" title="' . rex_escape(ucfirst($dayFullName)) . '">'
                  . rex_escape(mb_strtoupper(mb_substr($dayFullName, 0, 1)))
                  . '</th>';
            $j = (7 === $j) ? 0 : $j;
        }

        // Table opening tag with optional data attributes for backend JS
        $tableAttr = 'class="' . rex_escape($calendar_class) . '" cellspacing="0"';
        if ('edit' === $this->mode) {
            $tableAttr .= ' data-object-id="' . $this->object_id . '"'
                        . ' data-api-url="' . rex_escape($apiUrl) . '"'
                        . ' data-csrf-token="' . rex_escape($csrfValue) . '"';
        }

        $headClass = rex::isBackend() ? ' class="bg-primary"' : '';

        $out  = '<table ' . $tableAttr . '>' . "\n";
        $out .= '<caption>' . ucfirst((string) $captionFmt->format($monthStart->getTimestamp())) . '</caption>' . "\n";
        $out .= $col . "\n";
        $out .= '<thead' . $headClass . '><tr>' . $th . '</tr></thead>' . "\n";
        $out .= '<tbody>' . "\n" . '<tr>';

        $rows = 1;
        $cell = 1;

        // Leading padding cells
        for ($i = 0; $i < $prepend; $i++) {
            $out .= '<td class="pad">&nbsp;</td>';
            $cell++;
        }

        // Day cells
        $titleFmt = datefmt_create($locale, IntlDateFormatter::FULL, IntlDateFormatter::FULL, 'UTC', IntlDateFormatter::TRADITIONAL, 'EEEE, d. MMMM yyyy');

        for ($day = 1; $day <= $daysInMonth; $day++) {
            if ($cell > 7) {
                $out .= '</tr>' . "\n" . '<tr>';
                $cell = 1;
                $rows++;
            }

            $dayPad  = str_pad((string) $day, 2, '0', STR_PAD_LEFT);
            $dayDate = "{$year}-{$monthPad}-{$dayPad}";
            $classes = [];

            if ($this->getOption('mark_today') && $dayDate === $today) {
                $classes[] = (string) $this->getOption('today_date_class');
            }
            if ($this->getOption('mark_passed') && $dayDate < $today) {
                $classes[] = (string) $this->getOption('passed_date_class');
            }
            if (isset($this->booked_dates[$dayDate])) {
                $classes[] = 'booked-' . $this->booked_dates[$dayDate];
            }

            $classAttr = $classes !== [] ? ' class="' . implode(' ', array_map('rex_escape', $classes)) . '"' : '';
            $dayDt     = new DateTimeImmutable($dayDate);
            $titleText = $titleFmt ? ucwords((string) $titleFmt->format($dayDt)) : $dayDate;

            $out .= '<td' . $classAttr . ' data-date="' . rex_escape($dayDate) . '" title="' . rex_escape($titleText) . '">';

            if ('edit' === $this->mode) {
                // Use <button> instead of <a> to avoid browser Alt+click download behavior
                $out .= '<button type="button" data-date="' . rex_escape($dayDate) . '">' . $day . '</button>';
            } else {
                $out .= $day;
            }

            $out .= '</td>';
            $cell++;
        }

        // Trailing padding cells (only when row is incomplete)
        if ($cell <= 7) {
            while ($cell <= 7) {
                $out .= '<td class="pad">&nbsp;</td>';
                $cell++;
            }
            $out .= '</tr>' . "\n";
        }

        // Optional 6th row padding to keep all months the same height
        if ($this->getOption('table_six_rows') && $rows <= 5) {
            $out .= '<tr class="pad-row">' . str_repeat('<td class="pad">&nbsp;</td>', 7) . '</tr>' . "\n";
        }

        $out .= '</tbody>' . "\n" . '</table>' . "\n";

        return $out;
    }

    /**
     * Returns a period heading <h3> for the given month range.
     */
    public function getPeriod(int $view = 1, int $year = null, int $month = null): string
    {
        $year   = $year  ?? $this->year;
        $month  = $month ?? $this->month;
        $view   = max(1, $view);
        $locale = $this->getLocale();

        $startDate = (new DateTimeImmutable())->setDate($year, $month, 1);
        $endDate   = (new DateTimeImmutable())->setDate($year, $month + $view - 1, 1);

        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::FULL, IntlDateFormatter::FULL);
        $formatter->setPattern('MMMM yyyy');

        $startTs = $startDate->getTimestamp();
        $endTs   = $endDate->getTimestamp();

        $dateTxt = $startTs < $endTs
            ? $formatter->format($startTs) . ' – ' . $formatter->format($endTs)
            : $formatter->format($startTs);

        return '<h3 class="period">' . rex_escape($dateTxt) . '</h3>' . "\n";
    }

    /**
     * Returns a legend list for the given labels array [state => label].
     *
     * @param array<string, string>|null $labels
     */
    public function getLegend(?array $labels = null): string
    {
        if (!is_array($labels) || [] === $labels) {
            return '';
        }

        $out = '<ul class="legend">';
        foreach ($labels as $state => $label) {
            if ('' !== $label) {
                $out .= '<li class="booked-' . rex_escape($state) . '">' . rex_escape($label) . '</li>';
            }
        }
        $out .= '</ul>' . "\n";

        return $out;
    }

    /**
     * Returns a previous/next navigation bar.
     */
    public function getNav(int $view = 1, int $year = null, int $month = null): string
    {
        $year   = $year  ?? $this->year;
        $month  = $month ?? $this->month;
        $view   = max(1, $view);
        $locale = $this->getLocale();

        $prevStart = (new DateTimeImmutable())->setDate($year, $month - $view, 1);
        $prevEnd   = (new DateTimeImmutable())->setDate($year, $month - 1, 1);
        $currStart = (new DateTimeImmutable())->setDate($year, $month, 1);
        $currEnd   = (new DateTimeImmutable())->setDate($year, $month + $view - 1, 1);
        $nextStart = (new DateTimeImmutable())->setDate($year, $month + $view, 1);
        $nextEnd   = (new DateTimeImmutable())->setDate($year, $month + $view + $view - 1, 1);

        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::FULL, IntlDateFormatter::FULL);
        $formatter->setPattern('MMMM yyyy');

        $prevTxt = $formatter->format($prevStart->getTimestamp()) . ' – ' . $formatter->format($prevEnd->getTimestamp());
        $currTxt = $formatter->format($currStart->getTimestamp()) . ' – ' . $formatter->format($currEnd->getTimestamp());
        $nextTxt = $formatter->format($nextStart->getTimestamp()) . ' – ' . $formatter->format($nextEnd->getTimestamp());

        if ('edit' === $this->mode) {
            $base     = (string) ($this->getOption('base_link') ?? 'index.php?page=avcal/calendar');
            $prevLink = $base . '&date=' . $prevStart->format('Y-m-d');
            $nextLink = $base . '&date=' . $nextStart->format('Y-m-d');
        } else {
            $nowStart = (new DateTimeImmutable())->setDate((int) date('Y'), (int) date('m'), 1);
            $prevLink = $prevEnd->getTimestamp() < $nowStart->getTimestamp()
                ? false
                : rex_getUrl(rex_article::getCurrentId(), rex_clang::getCurrentId(), ['date' => $prevStart->format('Y-m-d')]);
            $nextLink = rex_getUrl(rex_article::getCurrentId(), rex_clang::getCurrentId(), ['date' => $nextStart->format('Y-m-d')]);
        }

        $out  = '<ul class="prev_next">';
        $out .= '<li class="prev">';
        $out .= false === $prevLink
            ? '&nbsp;'
            : '<a href="' . rex_escape($prevLink) . '" title="' . rex_escape($prevTxt) . '">' . rex_escape($prevTxt) . '</a>';
        $out .= '</li>';
        $out .= '<li class="curr"><span>' . rex_escape($currTxt) . '</span></li>';
        $out .= '<li class="next"><a href="' . rex_escape($nextLink) . '" title="' . rex_escape($nextTxt) . '">' . rex_escape($nextTxt) . '</a></li>';
        $out .= '</ul>' . "\n";

        return $out;
    }

    /**
     * Returns an iCal subscribe URL for this object (frontend use).
     *
     * Returns a raw (unescaped) URL. Escape for HTML contexts yourself:
     * Example:  <a href="<?= rex_escape($cal->getIcalUrl()) ?>">Als Kalender abonnieren</a>
     */
    public function getIcalUrl(int $year = null, int $months = 12): string
    {
        $params = ['rex-api-call' => 'avcal_ical', 'object_id' => $this->object_id];
        if (null !== $year) {
            $params['year']   = $year;
            $params['months'] = $months;
        }
        return rex_getUrl(rex_article::getCurrentId(), rex_clang::getCurrentId(), $params, '&');
    }
}
