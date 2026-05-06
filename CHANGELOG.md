# Changelog

## [3.0.0] – 2026-05-06

### Breaking Changes
- Alle Klassen wurden in den Namespace `FriendsOfRedaxo\AvCal` verschoben
- Hauptklasse `avcal` → `FriendsOfRedaxo\AvCal\Avcal` (`lib/Avcal.php`)
- API-Klassen verwenden jetzt explizite Registrierung per `rex_api_function::register()` (REDAXO 5.17+)
  - `rex_api_avcal_booking` → `FriendsOfRedaxo\AvCal\Api\Booking`
  - `rex_api_avcal_ical` → `FriendsOfRedaxo\AvCal\Api\IcalExport`
- Minimale REDAXO-Version: **5.17.1**, PHP: **8.2+**

### Added
- iCal-Export-Endpunkt (`rex-api-call=avcal_ical`) – Kalender als `.ics` abonnieren
- Backend-Link zum iCal-Export auf der Kalenderseite
- Englische Übersetzung (`lang/en_gb.lang`)
- Dark-Mode-Unterstützung im Backend via CSS Custom Properties

### Changed
- Komplette Neuentwicklung der Hauptklasse mit modernem PHP 8.2 (`DateTimeImmutable`, `match`, Named Arguments)
- Datenbankabfragen durchgehend parametrisiert (kein SQL-Injection-Risiko)
- DB-Schema-Erstellung von `boot.php` in `install.php` verschoben (Anti-Pattern beseitigt)
- Buchungszustands-Visualisierung von GIF-Bildern auf CSS-Gradienten mit Custom Properties umgestellt
- Klickbare Tageszellen als `<button type="button">` statt `<a href="#">` (verhindert Alt+Klick-Download im Browser)
- Navigation und Monatsbeschriftung nutzen `IntlDateFormatter` für lokalisierte Ausgaben
- Backend-JS auf jQuery-Event-Delegation mit `$(document).on('rex:ready')` umgestellt

### Fixed
- Alt+Klick auf Kalendertag löste im Browser einen Datei-Download aus
- Klick auf Tageszelle hatte nach Button-Wechsel keine Wirkung (falsches Event-Binding via nativen `addEventListener` statt jQuery `rex:ready`)
- Doppeltes HTML-Escaping in iCal-URL führte zu `amp;object_id` statt `object_id` im Request

### Removed
- Statische GIF-Grafiken für Buchungszustände
- Direktes Schreiben des DB-Schemas in `boot.php`
