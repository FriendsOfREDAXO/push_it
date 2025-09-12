<?php
/** @var rex_addon $this */

// Update Script: Umbenennung von pushi_it zu push_it

$sql = rex_sql::factory();

// Tabellen umbenennen falls sie noch die alten Namen haben
try {
    // Prüfen ob alte Tabellen existieren
    $oldSubsExists = $sql->setQuery("SHOW TABLES LIKE 'rex_pushi_it_subscriptions'")->getRows() > 0;
    $oldNotifExists = $sql->setQuery("SHOW TABLES LIKE 'rex_pushi_it_notifications'")->getRows() > 0;
    
    if ($oldSubsExists) {
        $sql->setQuery("RENAME TABLE `rex_pushi_it_subscriptions` TO `rex_push_it_subscriptions`");
        echo "Tabelle rex_pushi_it_subscriptions zu rex_push_it_subscriptions umbenannt.\n";
    }
    
    if ($oldNotifExists) {
        $sql->setQuery("RENAME TABLE `rex_pushi_it_notifications` TO `rex_push_it_notifications`");
        echo "Tabelle rex_pushi_it_notifications zu rex_push_it_notifications umbenannt.\n";
    }
    
} catch (rex_sql_exception $e) {
    // Tabellen existieren vermutlich nicht oder sind bereits umbenannt
}

// Sicherstellen dass die neuen Tabellen existieren (falls noch nicht erstellt)
$sql->setQuery("
CREATE TABLE IF NOT EXISTS `rex_push_it_subscriptions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL,
  `user_type` ENUM('backend', 'frontend') NOT NULL DEFAULT 'frontend',
  `endpoint` VARCHAR(1000) NOT NULL,
  `p256dh` VARCHAR(255) NOT NULL,
  `auth` VARCHAR(255) NOT NULL,
  `topics` VARCHAR(255) NULL,
  `ua` VARCHAR(500) NULL,
  `lang` VARCHAR(20) NULL,
  `domain` VARCHAR(255) NULL,
  `created` DATETIME NOT NULL,
  `updated` DATETIME NULL,
  `last_error` TEXT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `endpoint_unique` (`endpoint`),
  KEY `user_id` (`user_id`),
  KEY `user_type` (`user_type`),
  KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$sql->setQuery("
CREATE TABLE IF NOT EXISTS `rex_push_it_notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `body` TEXT NULL,
  `url` VARCHAR(500) NULL,
  `icon` VARCHAR(500) NULL,
  `topics` VARCHAR(255) NULL,
  `user_type` ENUM('backend', 'frontend', 'both') NOT NULL DEFAULT 'frontend',
  `sent_to` INT UNSIGNED NOT NULL DEFAULT 0,
  `delivery_errors` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_by` INT UNSIGNED NULL,
  `created` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `created` (`created`),
  KEY `user_type` (`user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Berechtigungen aktualisieren
if (rex::isBackend()) {
    rex_perm::register('push_it[]');
    
    // Alte Berechtigung entfernen falls sie existiert
    try {
        $sql->setQuery("DELETE FROM rex_user_role_perms WHERE perm = 'pushi_it[]'");
        $sql->setQuery("DELETE FROM rex_user_perms WHERE perm = 'pushi_it[]'");
    } catch (Exception $e) {
        // Ignorieren falls Tabellen nicht existieren
    }
}
