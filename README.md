# avcal
🐣 Einfacher Belegungskalender für REDAXO 5

![Screenshot](https://raw.githubusercontent.com/FriendsOfREDAXO/avcal/assets/screenshot.png)

Basiert auf: 

- Belegungskalender von Tim Filler
- mp Belegungskalender / maple park



## Modul-Eingabe

```php
// Modul-Input

if (rex_addon::get('avcal')->isAvailable()) {

  // select an object
  $select_object = new rex_select();
  $select_object->setName("REX_INPUT_VALUE[1]");
  $select_object->setSize(1);
  $select_object->addSqlOptions('SELECT `name`, `id`
                                 FROM `'.rex::getTablePrefix().'avcal_objects`
                                 WHERE `status` = 1');
  $select_object->setSelected("REX_VALUE[1]");
  echo rex_i18n::msg('avcal_module_select_object').': ';
  $select_object->show();

  echo '<br /><br />';

  // select the number of month to show
  $select_month_num = new rex_select();
  $select_month_num->setName("REX_INPUT_VALUE[2]");
  $select_month_num->setSize(1);
  $select_month_num->addArrayOptions(range(1, 18), false);
  $select_month_num->setSelected("REX_VALUE[2]");
  echo rex_i18n::msg('avcal_module_month_to_show').': ';
  $select_month_num->show();

  echo '<br /><br />';

  // select the number of tables (=months) in a row
  $select_month_per_row = new rex_select();
  $select_month_per_row->setName("REX_INPUT_VALUE[3]");
  $select_month_per_row->setSize(1);
  $select_month_per_row->addArrayOptions(range(1, 4), false);
  $select_month_per_row->setSelected("REX_VALUE[3]");
  echo rex_i18n::msg('avcal_module_month_per_row').': ';
  $select_month_per_row->show();

  echo '<br /><br />';

  // select if a navigation should be displayed
  $select_show_nav = new rex_select();
  $select_show_nav->setName("REX_INPUT_VALUE[4]");
  $select_show_nav->setSize(1);
  $select_show_nav->addOption(rex_i18n::msg('yes'), '1');
  $select_show_nav->addOption(rex_i18n::msg('no'), '0');
  $select_show_nav->setSelected("REX_VALUE[4]");
  echo rex_i18n::msg('avcal_module_show_navigation').': ';
  $select_show_nav->show();

  echo '<br /><br />';

  // set the labels for the different booking states
  ?>

  <?php echo rex_i18n::msg('avcal_label_for', rex_i18n::msg('avcal_booked_none')); ?>: <input type="text" size="40" name="REX_INPUT_VALUE[5]" value="REX_VALUE[5]" />
  <br /><br />

  <?php echo rex_i18n::msg('avcal_label_for', rex_i18n::msg('avcal_booked_all')); ?>: <input type="text" size="40" name="REX_INPUT_VALUE[6]" value="REX_VALUE[6]" />
  <br /><br />

  <?php echo rex_i18n::msg('avcal_label_for', rex_i18n::msg('avcal_booked_am')); ?>: <input type="text" size="40" name="REX_INPUT_VALUE[7]" value="REX_VALUE[7]" />
  <br /><br />

  <?php echo rex_i18n::msg('avcal_label_for', rex_i18n::msg('avcal_booked_pm')); ?>: <input type="text" size="40" name="REX_INPUT_VALUE[8]" value="REX_VALUE[8]" />
  <br /><br />

  <?php

} else {

  // addon is not available
  echo rex_view::error('Dieses Modul benötigt das "avcal" Addon!');

}

?>
```


## Modul-Ausgabe

```php

<div class="avcal">
<?php
// Modul-Output
if (rex_addon::get('avcal')->isAvailable()) {
  // show calendar for the selected object
  $object_id = "REX_VALUE[1]";

    if ($object_id > 0) {
    // number of months to show on one page
    $month_to_show = "REX_VALUE[2]";

    // how many months to show in a row
    $month_per_row = "REX_VALUE[3]";

    // should a navigation be displayed
    $show_nav = "REX_VALUE[4]";

    // the labels for the legend
    $labels = array();
    if ("REX_VALUE[5]" != '') {
      $labels['none'] = "REX_VALUE[5]";
    }
    if ("REX_VALUE[6]" != '') {
      $labels['all'] = "REX_VALUE[6]";
    }
    if ("REX_VALUE[7]" != '') {
      $labels['am'] = "REX_VALUE[7]";
    }
    if ("REX_VALUE[8]" != '') {
      $labels['pm'] = "REX_VALUE[8]";
    }

    // initialize date parameters
    $year2 = rex_request('date', 'string', date('Y'));
    $date  = $year2;
    $year = rex_request('year', 'string', date('Y', strtotime($date)));
    $month = rex_request('month', 'string', date('m', strtotime($date)));

    // create instance of calendar class
     $calendar = new avcal($object_id, $date, $year, $month);

$calendar->setOption('week_start', 1);

    //$calendar->setOption('table_six_rows', true);

    // set clearer for frontend/backend
    if (rex::isBackend()) {
      $clear = '<div class="rex-clearer"></div>';
    } else {
      $clear = '<div class="clear"></div>';
    }

    // show navigation ?
    if ($show_nav == 1) {
      echo $calendar->getNav($month_to_show);
      echo $clear;
    } else {
      echo $calendar->getPeriod($month_to_show);
      echo $clear;
    }

    // show the calendar
    for ($i = 0; $i < $month_to_show; $i++) {
      echo $calendar->getMonthView($year, $month + $i);
      if (($i % $month_per_row + 1) == $month_per_row) echo $clear;
    }

    // show the legend
    if (count($labels) > 0) {
      echo $calendar->getLegend($labels);
      echo $clear;
    }

  } else {

    // no object selected
    echo rex_view::error('Kein Objekt ausgewählt!');

  }

} else {

  // addon is not available
  echo rex_view::error('Dieses Modul benötigt das "mp_availability_calendar" Addon!');

}

    ?></div>
    
    
