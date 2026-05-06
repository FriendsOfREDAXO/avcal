<?php

namespace FriendsOfRedaxo\AvCal\Api;

use DateTimeImmutable;
use DateTimeZone;
use rex;
use rex_api_function;
use rex_api_result;
use rex_response;
use rex_sql;

/**
 * iCal export API for the availability calendar.
 *
 * Registered as: rex_api_function::register('avcal_ical', IcalExport::class)
 * Usage (frontend): /?rex-api-call=avcal_ical&object_id=1
 * Optional:         &year=2025&months=12   (defaults: current year, 12 months)
 *
 * @author (c) Friends Of REDAXO
 * @license MIT
 */
class IcalExport extends rex_api_function
{
    /** Publicly accessible – no login required */
    protected $published = true;

    public function execute(): rex_api_result
    {
        rex_response::cleanOutputBuffers();

        $objectId = rex_request('object_id', 'integer', 0);
        $year     = rex_request('year', 'integer', (int) date('Y'));
        $months   = min(24, max(1, rex_request('months', 'integer', 12)));

        if ($objectId <= 0) {
            rex_response::setStatus(rex_response::HTTP_BAD_REQUEST);
            rex_response::sendJson(['error' => 'Missing object_id']);
            exit;
        }

        // Load object
        $objSql = rex_sql::factory();
        $objSql->setTable(rex::getTable('avcal_objects'));
        $objSql->setWhere('id = :id AND status = 1', [':id' => $objectId]);
        $objSql->select('name, description');

        if (0 === $objSql->getRows()) {
            rex_response::setStatus(rex_response::HTTP_NOT_FOUND);
            rex_response::sendJson(['error' => 'Object not found']);
            exit;
        }

        $objectName = (string) $objSql->getValue('name');

        // Load bookings for the requested period
        $startDate = (new DateTimeImmutable())->setDate($year, 1, 1)->setTime(0, 0, 0);
        $endDate   = (new DateTimeImmutable())->setDate($year, $months, 1)->modify('last day of this month')->setTime(23, 59, 59);

        $bookingSql = rex_sql::factory();
        $rows = $bookingSql->getArray(
            'SELECT booked_day, state FROM ' . rex::getTable('avcal') .
            ' WHERE object_id = :oid AND booked_day BETWEEN :start AND :end ORDER BY booked_day',
            [':oid' => $objectId, ':start' => $startDate->format('Y-m-d'), ':end' => $endDate->format('Y-m-d')],
        );

        $ical = $this->buildIcal($objectName, $rows);

        rex_response::setHeader('Content-Type', 'text/calendar; charset=utf-8');
        rex_response::setHeader('Content-Disposition', 'attachment; filename="avcal-' . $objectId . '.ics"');
        rex_response::setHeader('Cache-Control', 'no-cache, no-store');

        echo $ical;
        exit;
    }

    /**
     * @param list<array<string, bool|float|int|string|null>> $rows
     */
    private function buildIcal(string $objectName, array $rows): string
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Ymd\THis\Z');
        $uid = gethostname() ?: 'avcal';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//FriendsOfREDAXO//AVCAL//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escapeIcalText($objectName),
        ];

        foreach ($rows as $i => $row) {
            $day   = isset($row['booked_day']) ? (string) $row['booked_day'] : '';
            $state = isset($row['state'])      ? (string) $row['state']      : 'all';
            $dt    = DateTimeImmutable::createFromFormat('Y-m-d', $day);
            if (!$dt instanceof DateTimeImmutable) {
                continue;
            }

            [$dtStart, $dtEnd, $summary] = match ($state) {
                'am'    => [$dt->format('Ymd\T060000'), $dt->format('Ymd\T120000'), $objectName . ' (Vormittag)'],
                'pm'    => [$dt->format('Ymd\T120000'), $dt->format('Ymd\T180000'), $objectName . ' (Nachmittag)'],
                default => [$dt->format('Ymd'), $dt->modify('+1 day')->format('Ymd'), $objectName . ' (Ganztägig)'],
            };

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:avcal-' . $objectName . '-' . $day . '-' . $state . '-' . $i . '@' . $uid;
            $lines[] = 'DTSTAMP:' . $now;

            if ('all' === $state) {
                $lines[] = 'DTSTART;VALUE=DATE:' . $dtStart;
                $lines[] = 'DTEND;VALUE=DATE:' . $dtEnd;
            } else {
                $lines[] = 'DTSTART:' . $dtStart;
                $lines[] = 'DTEND:' . $dtEnd;
            }

            $lines[] = 'SUMMARY:' . $this->escapeIcalText($summary);
            $lines[] = 'CLASS:PUBLIC';
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'TRANSP:OPAQUE';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    private function escapeIcalText(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(';', '\\;', $text);
        $text = str_replace(',', '\\,', $text);
        $text = str_replace("\n", '\\n', $text);
        return $text;
    }
}
