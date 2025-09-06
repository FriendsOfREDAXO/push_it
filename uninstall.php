<?php
/** @var rex_addon $this */

// Optional: Daten behalten oder löschen
$keepData = true; // Auf false setzen wenn Daten gelöscht werden sollen

if (!$keepData) {
    $sql = rex_sql::factory();
    
    // Tabellen löschen
    $sql->setQuery('DROP TABLE IF EXISTS `rex_pushi_it_subscriptions`');
    $sql->setQuery('DROP TABLE IF EXISTS `rex_pushi_it_notifications`');
}
