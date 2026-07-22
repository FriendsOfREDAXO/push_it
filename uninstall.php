<?php
/** @var rex_addon $this */

// Optional: Daten behalten oder löschen
$keepData = (bool) rex_config::get('push_it', 'keep_data_on_uninstall', true);

if (!$keepData) {
    $sql = rex_sql::factory();
    
    // Tabellen löschen
    $sql->setQuery('DROP TABLE IF EXISTS `rex_push_it_subscriptions`');
    $sql->setQuery('DROP TABLE IF EXISTS `rex_push_it_subscription_topics`');
    $sql->setQuery('DROP TABLE IF EXISTS `rex_push_it_notifications`');
}
