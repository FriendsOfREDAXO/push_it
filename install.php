<?php
rex_sql_table::get(rex::getTable('push_it_notifications'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('title', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('body', 'text', true))
    ->ensureColumn(new rex_sql_column('url', 'varchar(500)', true))
    ->ensureColumn(new rex_sql_column('icon', 'varchar(500)', true))
    ->ensureColumn(new rex_sql_column('badge', 'varchar(500)', true))
    ->ensureColumn(new rex_sql_column('image', 'varchar(500)', true))
    ->ensureColumn(new rex_sql_column('notification_options', 'longtext', true))
    ->ensureColumn(new rex_sql_column('topics', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('user_type', 'enum(\'backend\',\'frontend\',\'both\')', false, 'frontend'))
    ->ensureColumn(new rex_sql_column('sent_to', 'int(10) unsigned', false, '0'))
    ->ensureColumn(new rex_sql_column('delivery_errors', 'int(10) unsigned', false, '0'))
    ->ensureColumn(new rex_sql_column('created_by', 'int(10) unsigned', true))
    ->ensureColumn(new rex_sql_column('created', 'datetime'))
    ->ensureIndex(new rex_sql_index('created', ['created']))
    ->ensureIndex(new rex_sql_index('user_type', ['user_type']))
    ->ensure();

rex_sql_table::get(rex::getTable('push_it_subscriptions'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('user_id', 'int(10) unsigned', true))
    ->ensureColumn(new rex_sql_column('user_type', 'enum(\'backend\',\'frontend\')', false, 'frontend'))
    ->ensureColumn(new rex_sql_column('endpoint', 'varchar(1000)'))
    ->ensureColumn(new rex_sql_column('p256dh', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('auth', 'varchar(255)'))
    ->ensureColumn(new rex_sql_column('topics', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('ua', 'varchar(500)', true))
    ->ensureColumn(new rex_sql_column('lang', 'varchar(20)', true))
    ->ensureColumn(new rex_sql_column('domain', 'varchar(255)', true))
    ->ensureColumn(new rex_sql_column('created', 'datetime'))
    ->ensureColumn(new rex_sql_column('updated', 'datetime', true))
    ->ensureColumn(new rex_sql_column('last_error', 'text', true))
    ->ensureColumn(new rex_sql_column('active', 'tinyint(1)', false, '1'))
    ->ensureIndex(new rex_sql_index('endpoint_unique', ['endpoint'], rex_sql_index::UNIQUE))
    ->ensureIndex(new rex_sql_index('user_id', ['user_id']))
    ->ensureIndex(new rex_sql_index('user_type', ['user_type']))
    ->ensureIndex(new rex_sql_index('active', ['active']))
    ->ensure();

// Berechtigungen registrieren
if (rex::isBackend()) {
    rex_perm::register('push_it[]');
    rex_perm::register('push_it[subscriptions]');
    rex_perm::register('push_it[send]');
}
