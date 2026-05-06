<?php

namespace FriendsOfRedaxo\AvCal\Api;

use DateTimeImmutable;
use rex;
use rex_api_function;
use rex_api_result;
use rex_csrf_token;
use rex_response;
use rex_sql;
use rex_user;

/**
 * API endpoint for toggling booked days in the availability calendar.
 *
 * Registered as: rex_api_function::register('avcal_booking', Booking::class)
 * Usage: index.php?rex-api-call=avcal_booking
 * POST params: object_id, date (Y-m-d), state (all|am|pm), _csrf_token
 *
 * @author (c) Friends Of REDAXO
 * @license MIT
 */
class Booking extends rex_api_function
{
    /** Backend-only: requires authenticated user */
    protected $published = false;

    public function execute(): rex_api_result
    {
        rex_response::cleanOutputBuffers();

        $user = rex::getUser();
        if (!$user instanceof rex_user) {
            rex_response::setStatus(rex_response::HTTP_UNAUTHORIZED);
            rex_response::sendJson(['error' => 'Unauthorized']);
            exit;
        }

        if (!rex_csrf_token::factory('avcal_booking')->isValid()) {
            rex_response::setStatus(rex_response::HTTP_FORBIDDEN);
            rex_response::sendJson(['error' => 'Invalid CSRF token']);
            exit;
        }

        $objectId = rex_request('object_id', 'integer', 0);
        $date     = rex_request('date', 'string', '');
        $state    = rex_request('state', 'string', 'all');

        // Validate date format strictly
        if (1 !== preg_match('/^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])$/', $date)) {
            rex_response::setStatus(rex_response::HTTP_BAD_REQUEST);
            rex_response::sendJson(['error' => 'Invalid date format']);
            exit;
        }

        // Validate that date is actually a valid calendar date
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$dt instanceof DateTimeImmutable || $dt->format('Y-m-d') !== $date) {
            rex_response::setStatus(rex_response::HTTP_BAD_REQUEST);
            rex_response::sendJson(['error' => 'Invalid date']);
            exit;
        }

        // Validate state
        if (!in_array($state, ['all', 'am', 'pm'], true)) {
            rex_response::setStatus(rex_response::HTTP_BAD_REQUEST);
            rex_response::sendJson(['error' => 'Invalid state']);
            exit;
        }

        // Validate object exists and is active
        $objCheck = rex_sql::factory();
        $objCheck->setTable(rex::getTable('avcal_objects'));
        $objCheck->setWhere('id = :id AND status = 1', [':id' => $objectId]);
        $objCheck->select('id');
        if (0 === $objCheck->getRows()) {
            rex_response::setStatus(rex_response::HTTP_NOT_FOUND);
            rex_response::sendJson(['error' => 'Object not found']);
            exit;
        }

        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('avcal'));
        $sql->setWhere(
            'object_id = :oid AND booked_day = :day',
            [':oid' => $objectId, ':day' => $date],
        );
        $sql->select('id, state');

        if ($sql->getRows() > 0) {
            $dbId    = (int) $sql->getValue('id');
            $dbState = (string) $sql->getValue('state');

            // Clicking am on an all/pm day → set to am; pm on all/am → set to pm
            if (('am' === $state && in_array($dbState, ['all', 'pm'], true)) ||
                ('pm' === $state && in_array($dbState, ['all', 'am'], true))) {
                rex_sql::factory()
                    ->setTable(rex::getTable('avcal'))
                    ->setWhere('id = :id', [':id' => $dbId])
                    ->setValue('state', $state)
                    ->update();
                rex_response::sendJson(['css_class' => 'booked-' . $state]);
            } else {
                // Same state or full-day click again → delete
                rex_sql::factory()
                    ->setTable(rex::getTable('avcal'))
                    ->setWhere('id = :id', [':id' => $dbId])
                    ->delete();
                rex_response::sendJson(['css_class' => '']);
            }
        } else {
            rex_sql::factory()
                ->setTable(rex::getTable('avcal'))
                ->setValue('object_id', $objectId)
                ->setValue('booked_day', $date)
                ->setValue('state', $state)
                ->insert();
            rex_response::sendJson(['css_class' => 'booked-' . $state]);
        }

        exit;
    }
}
