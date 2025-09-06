<?php
/** @var rex_addon $this */

// Datenbank-Tabelle für Subscriptions erstellen
$sql = rex_sql::factory();

// Tabelle für Push-Subscriptions
$sql->setQuery("
CREATE TABLE IF NOT EXISTS `rex_pushi_it_subscriptions` (
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

// Tabelle für Notifications-Log
$sql->setQuery("
CREATE TABLE IF NOT EXISTS `rex_pushi_it_notifications` (
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

// Berechtigungen registrieren
if (rex::isBackend()) {
    rex_perm::register('pushi_it[]');
    rex_perm::register('pushi_it[subscriptions]');
    rex_perm::register('pushi_it[send]');
}
