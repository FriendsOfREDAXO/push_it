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

rex_sql_table::get(rex::getTable('push_it_subscription_topics'))
    ->ensurePrimaryIdColumn()
    ->ensureColumn(new rex_sql_column('subscription_id', 'int(10) unsigned'))
    ->ensureColumn(new rex_sql_column('topic', 'varchar(40)'))
    ->ensureIndex(new rex_sql_index('subscription_id', ['subscription_id']))
    ->ensureIndex(new rex_sql_index('topic', ['topic']))
    ->ensureIndex(new rex_sql_index('subscription_topic_unique', ['subscription_id', 'topic'], rex_sql_index::UNIQUE))
    ->ensure();

// Legacy CSV-Topics in Pivot-Tabelle synchronisieren.
$subscriptionTable = rex::getTable('push_it_subscriptions');
$topicTable = rex::getTable('push_it_subscription_topics');
$sql = rex_sql::factory();
$topicSql = rex_sql::factory();

$sql->setQuery('SELECT id, topics FROM ' . $subscriptionTable . ' WHERE topics IS NOT NULL AND topics <> ""');
for ($i = 0; $i < $sql->getRows(); ++$i) {
    $subscriptionId = (int) $sql->getValue('id');
    $topicsRaw = (string) $sql->getValue('topics');
    $topics = array_unique(array_filter(array_map('trim', explode(',', strtolower($topicsRaw)))));

    $topicSql->setQuery('DELETE FROM ' . $topicTable . ' WHERE subscription_id = ?', [$subscriptionId]);

    $normalizedTopics = [];
    foreach ($topics as $topic) {
        if (preg_match('/^[a-z0-9_-]{1,40}$/', $topic) !== 1) {
            continue;
        }

        $normalizedTopics[] = $topic;
        $topicSql->setQuery(
            'INSERT IGNORE INTO ' . $topicTable . ' (subscription_id, topic) VALUES (?, ?)',
            [$subscriptionId, $topic]
        );
    }

    $topicSql->setQuery(
        'UPDATE ' . $subscriptionTable . ' SET topics = ? WHERE id = ?',
        [implode(',', $normalizedTopics), $subscriptionId]
    );

    $sql->next();
}
