<?php

/**
 * This file is part of the avcal package.
 *
 * @author (c) Friends Of REDAXO
 * @license MIT
 */

// Register API functions (REDAXO 5.17+ explicit registration for namespaced classes)
rex_api_function::register('avcal_booking', \FriendsOfRedaxo\AvCal\Api\Booking::class);
rex_api_function::register('avcal_ical', \FriendsOfRedaxo\AvCal\Api\IcalExport::class);

if (rex::isBackend() && rex::getUser()) {
    rex_view::addJsFile($this->getAssetsUrl('backend.js'));
    rex_view::addCssFile($this->getAssetsUrl('backend.css'));
}
