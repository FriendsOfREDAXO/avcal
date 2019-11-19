<?php
/**
 * Belegungskalendar Addon
 * @author bade[at]maple-park[dot]de Heinz Bade
 * @author <a href="http://www.maple-park.de">www.maple-park.de</a>
 * @package redaxo 4.3
 * @version $Id: calendar.inc.php,v 1.2 2012/02/18 17:33:00 elektra Exp $
 */

// number of months to show on one page
#$month_to_show = $REX['ADDON'][$page]['month_to_show'];
$month_to_show = 12;

// initialize request parameters
$call_by = rex_request('call_by', 'string');
$object_id = rex_request('object_id', 'integer', 0);
$date = rex_request('date', 'string', date('Y-m-d'));
$year = rex_request('year', 'string', date('Y', strtotime($date)));
$month = rex_request('month', 'string', date('m', strtotime($date)));
$func = rex_request('func', 'string');
$state = rex_request('state', 'string');

// initialize a database connection
$sql = rex_sql::factory();


// database actions and response for ajax requests
if ($call_by == 'ajax') {

  // clean all active output buffers and start a new one
  ob_end_clean();
  ob_start();

  // send header
  header('Content-Type: text/plain');
  header('Cache-Control: no-cache');

  // look up in database if the day is already booked
  $sql->setTable(rex::getTablePrefix().'avcal');
  $sql->setWhere("object_id = ". $object_id." AND booked_day = '". $date ."'");
  $sql->select('id, state');

  // if in database
  if ($sql->getRows() > 0) {
    $db_id = $sql->getValue('id');
    $db_state = $sql->getValue('state');
    if (($state == 'am' && ($db_state == 'all' || $db_state == 'pm')) || ($state == 'pm' && ($db_state == 'all' || $db_state == 'am'))) {
      // -> update the entry
      $sql->setTable(rex::getTablePrefix().'avcal');
      $sql->setWhere('id = '. $db_id);
      $sql->setValue('state', $state);
      // -> send as response the new css-class
      if ($sql->update())
	  {
		  echo 'booked-'.$state;
	  }
    }
    else {
      // -> delete the entry
      $sql->setTable(rex::getTablePrefix().'avcal');
      $sql->setWhere('id = '. $db_id);
      // -> send as response "deleted"
       if ($sql->delete())
	   {
		   echo 'data-deleted';
	   }
    }
  }
  // else (if not in database)
  else {
    // -> add the entry
    $sql->setTable(rex::getTablePrefix().'avcal');
    $sql->setValue('object_id', $object_id);
    $sql->setValue('booked_day', $date);
    $sql->setValue('state', $state);
    // -> send as response the new css-class
   if ($sql->insert())
   {
	   echo 'booked-'.$state;
   }
	
  }
  // if error
  if ($sql->hasError()) {
    // -> send as response "error"
    // ToDo: send more informations about the error
    echo 'error';
  }
 

  // send the content of the output buffer
  ob_end_flush();
  exit();

}


// select the object
$select_object = new rex_select();
$select_object->setName('object_id');
$select_object->setId('object_id');
$select_object->setSize(1);
$select_object->setAttribute('onchange', 'this.form.submit()');
$select_object->setAttribute('class', 'form-control selectpicker');
$objects = $sql->getArray('SELECT `name`, `id` FROM `'.rex::getTablePrefix().'avcal_objects` WHERE `status` = 1');
foreach ($objects as $object) {
  $select_object->addOption($object['name'], $object['id']);
}
if ($object_id == 0 && count($objects) > 0) {
  $object_id = $objects[0]['id'];
}
$select_object->setSelected($object_id);



// create instance of calendar class

$calendar = new avcal($object_id, $date, $year, $month);
$calendar->setOption('week_start', 1);
$calendar->setEditMode();


// check if there are objects
if (count($objects) < 1) {
 // Warnung ausgeben rex5
}
// show the calendar
else {
$objectlabel =  $this->i18n("avcal_label_object");   
$panel = '
<form action="'.rex_url::currentBackendPage().'" method="get">

<div class="form-horizontal">
<input type="hidden" name="page" value="avcal" />
          <input type="hidden" name="subpage" value="calendar" />
          <input type="hidden" name="func" value="select_object" />
<div class="form-group">
	<label  for="object_id" class="col-sm-2 control-label">'.$objectlabel.'</label>
	    <div class="col-sm-10">
	         '.$select_object->get().'
	    </div>
</div>
</div>
    </form>
';


    
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit');
$fragment->setVar('title', rex_i18n::msg('avcal_select_object'), false);
$fragment->setVar('body', $panel, false);
$objectpanel = $fragment->parse('core/page/section.php');






$calview = '';    
for ($i = 0; $i < $month_to_show; $i++) {
  $calview .= $calendar->getMonthView($year, $month + $i);
     if ($i % 3 == 2) echo '<div class="rex-clearer"></div>';
}    

$panel = $calendar->getNav($month_to_show);
$panel .= $calview;    

$fragment = new rex_fragment();
$fragment->setVar('class', 'default avcal');
$fragment->setVar('title', rex_i18n::msg('avcal_edit_booked_days'), false);
$fragment->setVar('body', $panel, false);
$content = $fragment->parse('core/page/section.php');

echo '<div class="row">';
echo '<div class="col-lg-8">'.$content.'</div>';     


$legend = ' <div class="legend">
      <dl class="booking-states">
        <dt class="booked-none"></dt><dd>'.$this->i18n('avcal_booked_none').'</dd>
        <dt class="booked-am"></dt><dd>'.$this->i18n('avcal_booked_am').'</dd>
        <dt class="booked-pm"></dt><dd>'. $this->i18n('avcal_booked_pm').'</dd>
        <dt class="booked-all"></dt><dd>'.$this->i18n('avcal_booked_all').'</dd>
      </dl>
    </div>
';

$fragment = new rex_fragment();
$fragment->setVar('class', 'default avcal');
$fragment->setVar('title', rex_i18n::msg('avcal_legend'), false);
$fragment->setVar('body', $legend, false);
$content = $fragment->parse('core/page/section.php');
echo '<div class="col-lg-4">'.$objectpanel.$content.'</div>';      

$fragment = new rex_fragment();
$fragment->setVar('class', 'default avcal');

$fragment->setVar('body', rex_i18n::msg('avcal_instructions'), false);
$content = $fragment->parse('core/page/section.php');
echo '<div class="col-lg-4">'.$content.'</div>';          






echo '</div>';



}

?>


