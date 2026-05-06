<?php

/**
 * This file is part of the avcal package.
 *
 * @author (c) Friends Of REDAXO
 * @license MIT
 */

rex_sql_table::get(rex::getTable('avcal'))
    ->ensureColumn(new rex_sql_column('id', 'int(11) unsigned', false, null, 'auto_increment'))
    ->ensureColumn(new rex_sql_column('object_id', 'int(11) unsigned', false, '0'))
    ->ensureColumn(new rex_sql_column('booked_day', 'date', false, '0000-00-00'))
    ->ensureColumn(new rex_sql_column('state', 'char(3)', false, 'all'))
    ->setPrimaryKey('id')
    ->ensureIndex(new rex_sql_index('obj_booked', ['object_id', 'booked_day'], rex_sql_index::UNIQUE))
    ->ensure();

rex_sql_table::get(rex::getTable('avcal_objects'))
    ->ensureColumn(new rex_sql_column('id', 'int(11) unsigned', false, null, 'auto_increment'))
    ->ensureColumn(new rex_sql_column('name', 'varchar(150)', false, ''))
    ->ensureColumn(new rex_sql_column('description', 'text', true))
    ->ensureColumn(new rex_sql_column('status', 'tinyint(1) unsigned', false, '0'))
    ->setPrimaryKey('id')
    ->ensure();
