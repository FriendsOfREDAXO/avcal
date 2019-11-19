<?php
$func = rex_request('func','string','');
$table = 'avcal_objects'; 

$func = rex_request('func', 'string');
$id = rex_request('id', 'integer');
if ($func == 'setstatus') {
    $status = (rex_request('oldstatus', 'int') + 1) % 2;
    rex_sql::factory()
        ->setTable(rex::getTable($table))
        ->setWhere('id = :id', ['id' => $id])
        ->setValue('status', $status)
        ->update();
    echo rex_view::success($this->i18n('item_status_saved'));
    $func = '';
}

if ($func == 'delete') {

  $sql = rex_sql::factory();
  $sql->setTable(rex::getTablePrefix().'avcal_objects');
  $sql->setWhere('id = '. $id);
  $sql->delete();
  echo rex_view::success($this->i18n('avcal_object_deleted'));
  $func = '';

}

if (in_array($func,['add','edit'])) {
    // Formular-Objekt erstellen
    $form = rex_form::factory(rex::getTable($table), 'Objekt', 'id=' . rex_request('id', 'int', 0),'post',false);
    // Die ID muss immer mit übergeben werden, sonst funktioniert das Speichern nicht
    $form->addParam('id',rex_request('id', 'int', 0));
    // Sortierparameter werden ebenso behalten wie die Position in der Liste
    $form->addParam('sort',rex_request('sort', 'string', ''));
    $form->addParam('sorttype',rex_request('sorttype', 'string', ''));
    $form->addParam('start',rex_request('start', 'int', 0));

    $field = $form->addTextField('name');
    $field->setLabel($this->i18n('avcal_label_name'));
    $field->getValidator()->add( 'notEmpty', 'Das Feld Nachname darf nicht leer sein.');

    $field = $form->addTextAreaField('description');
    $field->setLabel($this->i18n('avcal_label_description'));

    $field = $form->addSelectField('status');
    $field->setLabel($this->i18n('avcal_label_status'));
    $select = $field->getSelect();
    $select->setSize(1);
    $select->addOption($this->i18n('status_online'), 1);
    $select->addOption($this->i18n('status_offline'), 0);

    // Formular auslesen
    $content = $form->get();

} else {
    // Listen-Objekt erstellen. 10 Datensätze pro Seite
    $list = rex_list::factory('SELECT id,name,description,status FROM '.rex::getTable($table), 10, $table, 0);

    // Icon für die erste Spalte "+" = hinzufügen
    $th_icon = '<a href="'.$list->getUrl(['func' => 'add']).'" title="'.rex_i18n::msg('add').'"><i class="rex-icon rex-icon-add-action"></i></a>';
    // Edit-Icon
    $td_icon = '<i class="rex-icon fa-file-text-o"></i>';
    $list->addColumn($th_icon, $td_icon, 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
    $list->setColumnParams($th_icon, ['func' => 'edit', 'id' => '###id###', 'start' => rex_request('start', 'int', NULL)]);

    $list->setColumnLabel('name', 'Name');
    $list->setColumnParams('name', array('func' => 'edit', 'id' => '###id###'));

    $list->setColumnLabel('status', $this->i18n('status'));
    $list->setColumnParams('status', ['func' => 'setstatus', 'oldstatus' => '###status###', 'id' => '###id###']);
    $list->setColumnLayout('status', ['<th class="rex-table-action">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnFormat('status', 'custom', function ($params) {
        /** @var rex_list $list */
        $list = $params['list'];
        if ($list->getValue('status') == 1) {
            $str = $list->getColumnLink('status', '<span class="rex-online"><i class="rex-icon rex-icon-active-true"></i> ' . $this->i18n('status_online') . '</span>');
        } else {
            $str = $list->getColumnLink('status', '<span class="rex-offline"><i class="rex-icon rex-icon-active-false"></i> ' . $this->i18n('status_offline') . '</span>');
        }
        return $str;
    });

  $list->addColumn($this->i18n('avcal_label_functions'), $this->i18n('avcal_function_delete'));
  $list->setColumnParams($this->i18n('avcal_label_functions'), array('func' => 'delete', 'id' => '###id###'));
  $list->addLinkAttribute($this->i18n('avcal_label_functions'), 'onclick', 'return confirm(\''.$this->i18n('delete').' ?\')');
  // die Spalte name wird sortierbar
  $list->setColumnSortable('name');
  // Liste auslesen
  $content = $list->get();
}
// Listen- und Formularinhalt in Fragment "section" ausgeben
$fragment = new rex_fragment();
$fragment->setVar('title', 'Formlabel', false);
$fragment->setVar('body', $content, false);
$content = $fragment->parse('core/page/section.php');

$fragment = new rex_fragment();
echo $content;










