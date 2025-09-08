<?php
use FriendsOfREDAXO\PushIt\Service\BackendNotificationManager;
use FriendsOfREDAXO\PushIt\Service\SettingsManager;
use FriendsOfREDAXO\PushIt\Service\SubscriptionManager;

$addon = rex_addon::get('push_it');

// BackendNotificationManager für Status-Anzeigen
$backendManager = new BackendNotificationManager();
$settingsManager = new SettingsManager();
$subscriptionManager = new SubscriptionManager();

// Aktueller Status
$settings = $settingsManager->getSettings();
$hasVapidKeys = !empty($settings['publicKey']) && !empty($settings['privateKey']);

?>

<div class="rex-page-main">

    <?php if (!$hasVapidKeys): ?>
        <?= $backendManager->renderVapidWarning() ?>
    <?php else: ?>
        
        <!-- Push-It Dashboard -->
        <div class="row">
            <div class="col-lg-6">
                <!-- Backend Benachrichtigungen Status -->
                <?php 
                $fragment = new rex_fragment();
                $fragment->setVar('title', 'Backend-Benachrichtigungen', false);
                $fragment->setVar('body', $backendManager->renderAutomaticNotificationsInfo(true), false);
                echo $fragment->parse('core/page/section.php');
                ?>
            </div>
            
            <div class="col-lg-6">
                <!-- System Error Monitoring Status -->
                <?php 
                $fragment = new rex_fragment();
                $fragment->setVar('title', 'System Error Monitoring', false);
                $fragment->setVar('body', $backendManager->renderErrorMonitoringInfo(), false);
                echo $fragment->parse('core/page/section.php');
                ?>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <!-- Abonnements Übersicht -->
                <?php 
                $subscriptionStats = $subscriptionManager->getSubscriptionStats();
                $statsContent = '
                <div class="row">
                    <div class="col-sm-3">
                        <div class="panel panel-primary">
                            <div class="panel-body text-center">
                                <h3>' . $subscriptionStats['total'] . '</h3>
                                <p>Gesamt Abonnements</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="panel panel-success">
                            <div class="panel-body text-center">
                                <h3>' . $subscriptionStats['active'] . '</h3>
                                <p>Aktive Abonnements</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="panel panel-info">
                            <div class="panel-body text-center">
                                <h3>' . $subscriptionStats['backend'] . '</h3>
                                <p>Backend-Benutzer</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="panel panel-default">
                            <div class="panel-body text-center">
                                <h3>' . $subscriptionStats['frontend'] . '</h3>
                                <p>Frontend-Benutzer</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="btn-group" role="group">
                    <a href="' . rex_url::backendPage('push_it/send') . '" class="btn btn-primary">
                        <i class="rex-icon fa-paper-plane"></i> Nachricht senden
                    </a>
                    <a href="' . rex_url::backendPage('push_it/subscriptions') . '" class="btn btn-default">
                        <i class="rex-icon fa-users"></i> Abonnements verwalten
                    </a>
                    <a href="' . rex_url::backendPage('push_it/history') . '" class="btn btn-default">
                        <i class="rex-icon fa-history"></i> Verlauf anzeigen
                    </a>
                    <a href="' . rex_url::backendPage('push_it/settings') . '" class="btn btn-default">
                        <i class="rex-icon fa-cog"></i> Einstellungen
                    </a>
                </div>';
                
                $fragment = new rex_fragment();
                $fragment->setVar('title', 'Push-It Übersicht', false);
                $fragment->setVar('body', $statsContent, false);
                echo $fragment->parse('core/page/section.php');
                ?>
            </div>
        </div>

    <?php endif; ?>

</div>