<?php

declare(strict_types=1);

use FriendsOfREDAXO\PushIt\Service\BackendNotificationManager;
use FriendsOfREDAXO\PushIt\Service\SettingsManager;
use FriendsOfREDAXO\PushIt\Service\SubscriptionManager;

$addon = rex_addon::get('push_it');
$isAdmin = rex::getUser()?->isAdmin() ?? false;

$settingsManager = new SettingsManager();
$backendManager  = new BackendNotificationManager();
$subManager      = new SubscriptionManager();

$settings     = $settingsManager->getSettings();
$hasVapidKeys = ($settings['publicKey'] ?? '') !== '' && ($settings['privateKey'] ?? '') !== '';
$hasSubject   = ($settings['subject'] ?? '') !== '';
$hasToken     = ($settings['backend_token'] ?? '') !== '';
$libraryOk    = $settingsManager->isLibraryAvailable();

// Setup-Schritte ermitteln
$setupDone = $hasVapidKeys && $hasSubject && $hasToken;
$setupStep = 0;
if (!$libraryOk) {
    $setupStep = -1; // Bibliothek fehlt
} elseif (!$hasVapidKeys || !$hasSubject) {
    $setupStep = 1;
} elseif (!$hasToken) {
    $setupStep = 2;
}

?>

<?php if ($setupStep === -1): ?>
<!-- Bibliothek fehlt -->
<?php
$fragment = new rex_fragment();
$fragment->setVar('class', 'danger', false);
$fragment->setVar('title', rex_i18n::msg('pushit_webpush_library_warning'), false);
$fragment->setVar('body', '<p>' . rex_i18n::msg('pushit_library_install_hint') . '</p>', false);
echo $fragment->parse('core/ui/messages.php');
?>

<?php elseif (!$setupDone && $isAdmin): ?>
<!-- Setup-Wizard -->
<?php
$step1Done = $hasVapidKeys && $hasSubject;
$step2Done = $hasToken;

$wizardBody = '<div class="pushit-setup-wizard">';

// Schritt 1: VAPID
$s1Icon  = $step1Done ? 'fa-check-circle text-success' : 'fa-circle-o text-muted';
$s1Class = $step1Done ? 'list-group-item-success' : ($setupStep === 1 ? '' : 'list-group-item-default');
$wizardBody .= '
<div class="list-group">
  <div class="list-group-item ' . $s1Class . '">
    <h4 class="list-group-item-heading">
      <i class="rex-icon ' . $s1Icon . '"></i>
      ' . rex_i18n::msg('pushit_setup_step1_title') . '
    </h4>
    <p class="list-group-item-text">' . rex_i18n::msg('pushit_setup_step1_desc') . '</p>
    ' . (!$step1Done ? '<a href="' . rex_url::backendPage('push_it/settings') . '" class="btn btn-primary btn-sm">
        <i class="rex-icon fa-key"></i> ' . rex_i18n::msg('pushit_setup_go_to_settings') . '
    </a>' : '') . '
  </div>
  <div class="list-group-item ' . ($step2Done ? 'list-group-item-success' : ($setupStep === 2 ? '' : 'list-group-item-default')) . '">
    <h4 class="list-group-item-heading">
      <i class="rex-icon ' . ($step2Done ? 'fa-check-circle text-success' : 'fa-circle-o text-muted') . '"></i>
      ' . rex_i18n::msg('pushit_setup_step2_title') . '
    </h4>
    <p class="list-group-item-text">' . rex_i18n::msg('pushit_setup_step2_desc') . '</p>
    ' . (!$step2Done && $step1Done ? '<a href="' . rex_url::backendPage('push_it/settings') . '" class="btn btn-primary btn-sm">
        <i class="rex-icon fa-shield"></i> ' . rex_i18n::msg('pushit_setup_go_to_settings') . '
    </a>' : '') . '
  </div>
  <div class="list-group-item list-group-item-default">
    <h4 class="list-group-item-heading">
      <i class="rex-icon fa-circle-o text-muted"></i>
      ' . rex_i18n::msg('pushit_setup_step3_title') . '
    </h4>
    <p class="list-group-item-text">' . rex_i18n::msg('pushit_setup_step3_desc') . '</p>
  </div>
</div>
</div>';

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('pushit_setup_wizard_title'), false);
$fragment->setVar('body', $wizardBody, false);
echo $fragment->parse('core/page/section.php');
?>

<?php else: ?>
<!-- Dashboard (Setup abgeschlossen) -->
<?php
$stats = $subManager->getSubscriptionStats();
$frontendStats  = $stats['frontend'];
$backendStats   = $stats['backend'];
$totalActive    = $stats['active'];
$totalAll       = $stats['total'];

// Stat-Karten
$statsBody = '
<div class="row">
  <div class="col-sm-3">
    <div class="panel panel-primary">
      <div class="panel-body text-center">
        <div style="font-size:2em;font-weight:bold;">' . $totalAll . '</div>
        <small>' . rex_i18n::msg('pushit_stat_total') . '</small>
      </div>
    </div>
  </div>
  <div class="col-sm-3">
    <div class="panel panel-success">
      <div class="panel-body text-center">
        <div style="font-size:2em;font-weight:bold;">' . $totalActive . '</div>
        <small>' . rex_i18n::msg('pushit_stat_active') . '</small>
      </div>
    </div>
  </div>
  <div class="col-sm-3">
    <div class="panel panel-info">
      <div class="panel-body text-center">
        <div style="font-size:2em;font-weight:bold;">' . $backendStats['active_count'] . '</div>
        <small>' . rex_i18n::msg('pushit_stat_backend') . '</small>
      </div>
    </div>
  </div>
  <div class="col-sm-3">
    <div class="panel panel-default">
      <div class="panel-body text-center">
        <div style="font-size:2em;font-weight:bold;">' . $frontendStats['active_count'] . '</div>
        <small>' . rex_i18n::msg('pushit_stat_frontend') . '</small>
      </div>
    </div>
  </div>
</div>
<div class="btn-group" role="group">
  <a href="' . rex_url::backendPage('push_it/send') . '" class="btn btn-primary">
    <i class="rex-icon fa-paper-plane"></i> ' . rex_i18n::msg('pushit_dashboard_send') . '
  </a>
  <a href="' . rex_url::backendPage('push_it/subscriptions') . '" class="btn btn-default">
    <i class="rex-icon fa-users"></i> ' . rex_i18n::msg('pushit_dashboard_subscriptions') . '
  </a>
  <a href="' . rex_url::backendPage('push_it/history') . '" class="btn btn-default">
    <i class="rex-icon fa-history"></i> ' . rex_i18n::msg('pushit_dashboard_history') . '
  </a>
  <a href="' . rex_url::backendPage('push_it/settings') . '" class="btn btn-default">
    <i class="rex-icon fa-cog"></i> ' . rex_i18n::msg('pushit_dashboard_settings') . '
  </a>
</div>';

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('pushit_dashboard_title'), false);
$fragment->setVar('body', $statsBody, false);
echo $fragment->parse('core/page/section.php');
?>

<div class="row">
  <div class="col-lg-6">
    <?php
    $fragment = new rex_fragment();
    $fragment->setVar('title', rex_i18n::msg('pushit_automatic_notifications_title'), false);
    $fragment->setVar('body', $backendManager->renderAutomaticNotificationsInfo($isAdmin), false);
    echo $fragment->parse('core/page/section.php');
    ?>
  </div>
  <div class="col-lg-6">
    <?php
    $fragment = new rex_fragment();
    $fragment->setVar('title', rex_i18n::msg('pushit_monitoring_title'), false);
    $fragment->setVar('body', $backendManager->renderErrorMonitoringInfo(), false);
    echo $fragment->parse('core/page/section.php');
    ?>
  </div>
</div>

<?php endif; ?>