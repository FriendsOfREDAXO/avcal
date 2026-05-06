/**
 * AvCal – Backend JavaScript
 *
 * Handles click events on calendar day cells in edit mode.
 * Modifier keys:
 *   no modifier  → toggle full day  (state: all)
 *   Shift + click → morning only    (state: am)
 *   Alt + click  → afternoon only   (state: pm)
 */
/* global $ */
(function () {
  'use strict';

  /**
   * Toggle the booking state of a single day via the rex_api_avcal_booking API.
   *
   * @param {HTMLTableElement} table
   * @param {HTMLTableCellElement} td
   * @param {string} state  'all' | 'am' | 'pm'
   */
  function toggleBooking(table, td, state) {
    var objectId  = table.dataset.objectId;
    var date      = td.dataset.date;
    var apiUrl    = table.dataset.apiUrl;
    var csrfToken = table.dataset.csrfToken;

    if (!objectId || !date || !apiUrl) {
      return;
    }

    var body = new URLSearchParams({
      object_id:    objectId,
      date:         date,
      state:        state,
      _csrf_token:  csrfToken || '',
    });

    fetch(apiUrl, {
      method:  'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body:    body.toString(),
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('HTTP ' + response.status);
        }
        return response.json();
      })
      .then(function (data) {
        // Remove all booking classes first
        td.classList.remove('booked-all', 'booked-am', 'booked-pm');
        if (data.css_class) {
          td.classList.add(data.css_class);
        }
      })
      .catch(function (err) {
        console.error('[avcal] Booking request failed:', err);
      });
  }

  // rex:ready is a jQuery event fired by REDAXO – must use jQuery to listen
  $(document).on('rex:ready', function () {
    $(document).on('click', 'table.calendar tbody button', function (e) {
      e.preventDefault();

      var btn   = e.currentTarget;
      var td    = btn.closest('td');
      var table = btn.closest('table.calendar');
      if (!td || !table) { return; }

      var state = 'all';
      if (e.shiftKey) { state = 'am'; }
      if (e.altKey)   { state = 'pm'; }

      toggleBooking(table, td, state);
    });
  });
}());
