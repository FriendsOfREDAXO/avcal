# avcal - Belegungskalender


⚠️ Die Entwicklung des AddOns wurde eingestellt und wird an dieser Stelle nicht länger gepflegt. / Deprecated

Einfacher Belegungskalender für REDAXO 5, portiert aus REDAXO 4

Bindet einen Belegungskalender ein, der per AJAX im Backend bearbeitet werden kann. Es können mehrere Objekte verwaltet werden. Das AddOn liefert keine Buchungsfunktionen oder dergleichen. Diese können ggf. leicht in Kombination mit YForm realisiert werden. 

![Screenshot](https://raw.githubusercontent.com/FriendsOfREDAXO/avcal/assets/screenshot.png)

Basiert auf: 

- REDAXO4-AddOn: Belegungskalender von Tim Filler
- REDAXO4-AddOn: mp Belegungskalender / maple park
- [Belegegungskalender von Chris Bolson](https://www.ajaxavailabilitycalendar.com)

Ein CSS für das Frontend findet sich im Assets-Ordner. 

## Änderungen gegenüber 4.x - Version: 

- Settings entfernt
- Hilfeseite entfernt
- Modul: Anzahl der Tabellen je Zeile entfernt, sollte je Präsenz per CSS gelöst werden. 
- Anpassung an REDAXO 5 - Layout

## Alternative, viel ausführlicher, besser und moderner

[Buchungskalender](https://github.com/dtpop/buchungskalender)

## Credits

**Friends Of REDAXO**  
http://www.redaxo.org  
https://github.com/FriendsOfREDAXO  

**Projekt-Lead**
[Thomas Skerbis](https://github.com/skerbis)


## Modul

Ausgabemodul für das Frontend

### Modul-Eingabe

```php
<?php

// Modul-Input

if (rex_addon::get('avcal')->isAvailable()) {

  // Generate selects 
  
  $select_object = new rex_select();
  $select_object->setName("REX_INPUT_VALUE[1]");
  $select_object->setSize(1);
  $select_object->setAttribute('class', 'form-control selectpicker');
  $select_object->addSqlOptions('SELECT `name`, `id`
                                 FROM `'.rex::getTablePrefix().'avcal_objects`
                                 WHERE `status` = 1');
  $select_object->setSelected("REX_VALUE[1]");

  $select_month_num = new rex_select();
  $select_month_num->setName("REX_INPUT_VALUE[2]");
  $select_month_num->setSize(1);
  $select_month_num->setAttribute('class', 'form-control selectpicker');
  $select_month_num->addArrayOptions(range(1, 18), false);
  $select_month_num->setSelected("REX_VALUE[2]");

  $select_show_nav = new rex_select();
  $select_show_nav->setName("REX_INPUT_VALUE[4]");
  $select_show_nav->setSize(1);
  $select_show_nav->setAttribute('class', 'form-control selectpicker');
  $select_show_nav->addOption(rex_i18n::msg('yes'), '1');
  $select_show_nav->addOption(rex_i18n::msg('no'), '0');
  $select_show_nav->setSelected("REX_VALUE[4]");
  ?>

<div class="form-horizontal">

    <div class="form-group">
        <label class="col-sm-4 control-label"><?=rex_i18n::msg('avcal_module_select_object')?></label>
        <div class="col-sm-8">
            <?=$select_object->get()?>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-4 control-label"><?=rex_i18n::msg('avcal_module_month_to_show')?></label>
        <div class="col-sm-8">
            <?=$select_month_num->get()?>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-4 control-label"><?=rex_i18n::msg('avcal_module_show_navigation')?></label>
        <div class="col-sm-8">
            <?=$select_show_nav->get()?>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-4 control-label"><?=rex_i18n::msg('avcal_module_show_navigation')?></label>
        <div class="col-sm-8">
            <?=$select_show_nav->get()?>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-4 control-label"><?=rex_i18n::msg('avcal_label_for', rex_i18n::msg('avcal_booked_none'))?></label>
        <div class="col-sm-8">
            <input
                class="form-control"
                type="text"
                size="40"
                name="REX_INPUT_VALUE[5]"
                value="REX_VALUE[5]"/>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-4 control-label"><?=rex_i18n::msg('avcal_label_for', rex_i18n::msg('avcal_booked_all'))?></label>
        <div class="col-sm-8">
            <input
                class="form-control"
                type="text"
                size="40"
                name="REX_INPUT_VALUE[6]"
                value="REX_VALUE[6]"/>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-4 control-label"><?=rex_i18n::msg('avcal_label_for', rex_i18n::msg('avcal_booked_am'))?></label>
        <div class="col-sm-8">
            <input
                class="form-control"
                type="text"
                size="40"
                name="REX_INPUT_VALUE[7]"
                value="REX_VALUE[7]"/>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-4 control-label"><?=rex_i18n::msg('avcal_label_for', rex_i18n::msg('avcal_booked_pm'))?></label>
        <div class="col-sm-8">
            <input
                class="form-control"
                type="text"
                size="40"
                name="REX_INPUT_VALUE[8]"
                value="REX_VALUE[8]"/>
        </div>
    </div>

</div>

<?php

} else {

  // addon is not available
  echo rex_view::error('Dieses Modul benötigt das "avcal" Addon!');

}

?>
```


### Modul-Ausgabe

```php

<link href="/assets/addons/avcal/frontend.css" rel="stylesheet" media="screen" type="text/css">
<div class="avcal">
<?php
// Modul-Output
if (rex_addon::get('avcal')->isAvailable()) {
  // show calendar for the selected object
  $object_id = "REX_VALUE[1]";

    if ($object_id > 0) {
    // number of months to show on one page
    $month_to_show = "REX_VALUE[2]";

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

```

    
    
