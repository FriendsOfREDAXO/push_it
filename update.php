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
  `endpoint` TEXT NOT NULL,
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
  UNIQUE KEY `endpoint_unique` (`endpoint`(255)),
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

// Security-Update: User-Token Tabelle erstellen
$sql->setQuery("
CREATE TABLE IF NOT EXISTS `rex_push_it_user_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `created` DATETIME NOT NULL,
  `expires_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `token` (`token`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Security-Update: Weitere neue Spalten in bestehenden Tabellen hinzufügen falls nicht vorhanden
try {
    // Prüfen ob notification_options Spalte existiert
    $checkColumns = $sql->setQuery("SHOW COLUMNS FROM rex_push_it_notifications LIKE 'notification_options'")->getRows();
    if ($checkColumns === 0) {
        $sql->setQuery("ALTER TABLE rex_push_it_notifications ADD COLUMN notification_options LONGTEXT NULL AFTER image");
    }
    
    // Prüfen ob badge Spalte existiert
    $checkBadge = $sql->setQuery("SHOW COLUMNS FROM rex_push_it_notifications LIKE 'badge'")->getRows();
    if ($checkBadge === 0) {
        $sql->setQuery("ALTER TABLE rex_push_it_notifications ADD COLUMN badge VARCHAR(500) NULL AFTER icon");
    }
    
    // Prüfen ob image Spalte existiert
    $checkImage = $sql->setQuery("SHOW COLUMNS FROM rex_push_it_notifications LIKE 'image'")->getRows();
    if ($checkImage === 0) {
        $sql->setQuery("ALTER TABLE rex_push_it_notifications ADD COLUMN image VARCHAR(500) NULL AFTER badge");
    }
    
    // Security-Update: expires_at Spalte auf NULL setzen können für Backend-User Tokens
    $checkTokenTable = $sql->setQuery("SHOW TABLES LIKE 'rex_push_it_user_tokens'")->getRows();
    if ($checkTokenTable > 0) {
        $sql->setQuery("ALTER TABLE rex_push_it_user_tokens MODIFY expires_at DATETIME NULL");
    }
} catch (Exception $e) {
    // Bei Fehlern ignorieren - Spalten sind vermutlich bereits vorhanden
}

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
