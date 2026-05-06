<?php

/**
 * AvCal – Backend calendar page.
 *
 * Renders the booking calendar for the selected object.
 * Actual day toggling is handled via rex_api_avcal_booking (AJAX).
 *
 * @author (c) Friends Of REDAXO
 * @license MIT
 */

$month_to_show = 12;

$object_id = rex_request('object_id', 'integer', 0);
$date      = rex_request('date', 'string', date('Y-m-d'));

// Validate date, fall back to today
$dtParsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
if (!$dtParsed instanceof DateTimeImmutable || $dtParsed->format('Y-m-d') !== $date) {
    $dtParsed = new DateTimeImmutable();
    $date     = $dtParsed->format('Y-m-d');
}

$year  = (int) $dtParsed->format('Y');
$month = (int) $dtParsed->format('m');

// Load active objects
$sql     = rex_sql::factory();
$objects = $sql->getArray(
    'SELECT `name`, `id` FROM `' . rex::getTable('avcal_objects') . '` WHERE `status` = 1 ORDER BY `name`',
);

// Auto-select first object if none given
if (0 === $object_id && count($objects) > 0) {
    $object_id = (int) $objects[0]['id'];
}

if (count($objects) < 1) {
    // No objects yet – show hint
    $fragment = new rex_fragment();
    $fragment->setVar('class', 'warning');
    $fragment->setVar('body', $this->i18n('avcal_first_create_objects'), false);
    echo $fragment->parse('core/page/section.php');
} else {
    // --- Object selector ---
    $select = new rex_select();
    $select->setName('object_id');
    $select->setId('object_id');
    $select->setSize(1);
    $select->setAttribute('onchange', 'this.form.submit()');
    $select->setAttribute('class', 'form-control selectpicker');
    foreach ($objects as $obj) {
        $select->addOption((string) $obj['name'], (int) $obj['id']);
    }
    $select->setSelected($object_id);

    $objectPanel = '
<form action="' . rex_url::currentBackendPage() . '" method="get">
  <input type="hidden" name="page" value="avcal" />
  <input type="hidden" name="subpage" value="calendar" />
  <div class="form-horizontal">
    <div class="form-group">
      <label for="object_id" class="col-sm-2 control-label">' . $this->i18n('avcal_label_object') . '</label>
      <div class="col-sm-10">' . $select->get() . '</div>
    </div>
  </div>
</form>';

    $fragment = new rex_fragment();
    $fragment->setVar('class', 'edit');
    $fragment->setVar('title', $this->i18n('avcal_select_object'), false);
    $fragment->setVar('body', $objectPanel, false);
    $objectSection = $fragment->parse('core/page/section.php');

    // --- Calendar ---
    $calendar = new \FriendsOfRedaxo\AvCal\Avcal($object_id, $date, $year, $month);
    $calendar->setOption('week_start', 1);
    $calendar->setEditMode();

    $calview = '';
    for ($i = 0; $i < $month_to_show; $i++) {
        $calview .= $calendar->getMonthView($year, $month + $i);
        if ($i % 3 === 2) {
            $calview .= '<div class="rex-clearer"></div>';
        }
    }

    $calPanel = $calendar->getNav($month_to_show) . $calview;

    $fragment = new rex_fragment();
    $fragment->setVar('class', 'default avcal');
    $fragment->setVar('title', $this->i18n('avcal_edit_booked_days'), false);
    $fragment->setVar('body', $calPanel, false);
    $calSection = $fragment->parse('core/page/section.php');

    // --- Legend ---
    $legendBody = '
<div class="legend">
  <dl class="booking-states">
    <dt class="booked-none"></dt><dd>' . $this->i18n('avcal_booked_none') . '</dd>
    <dt class="booked-am"></dt><dd>' . $this->i18n('avcal_booked_am') . '</dd>
    <dt class="booked-pm"></dt><dd>' . $this->i18n('avcal_booked_pm') . '</dd>
    <dt class="booked-all"></dt><dd>' . $this->i18n('avcal_booked_all') . '</dd>
  </dl>
</div>';

    $fragment = new rex_fragment();
    $fragment->setVar('class', 'default avcal');
    $fragment->setVar('title', $this->i18n('avcal_legend'), false);
    $fragment->setVar('body', $legendBody, false);
    $legendSection = $fragment->parse('core/page/section.php');

    // --- Instructions ---
    $instrBody = '<p>' . $this->i18n('avcal_instructions') . '</p>';

    $fragment = new rex_fragment();
    $fragment->setVar('class', 'default avcal');
    $fragment->setVar('body', $instrBody, false);
    $instrSection = $fragment->parse('core/page/section.php');

    // --- iCal hint ---
    // Pass false to disable auto-escaping so rex_escape() below works exactly once
    $icalUrl  = rex_url::frontendController([
        'rex-api-call' => 'avcal_ical',
        'object_id'    => $object_id,
    ], false);
    $icalBody = '<p><a href="' . rex_escape($icalUrl) . '" title="' . $this->i18n('avcal_ical_link_title') . '">'
              . '<i class="rex-icon fa-calendar-o"></i> ' . $this->i18n('avcal_ical_export') . '</a></p>';

    $fragment = new rex_fragment();
    $fragment->setVar('class', 'default avcal');
    $fragment->setVar('body', $icalBody, false);
    $icalSection = $fragment->parse('core/page/section.php');

    // --- Layout ---
    echo '<div class="row">';
    echo '<div class="col-lg-8">' . $calSection . '</div>';
    echo '<div class="col-lg-4">' . $objectSection . $legendSection . $instrSection . $icalSection . '</div>';
    echo '</div>';
}
