<?php

namespace FriendsOfREDAXO\PushIt\Service;

use FriendsOfREDAXO\PushIt\Service\NotificationService;
use rex_addon;
use rex_view;
use rex_escape;
use rex_url;
use rex_sql;

/**
 * Service-Klasse für das Senden von Push-Notifications
 */
class SendManager
{
    private rex_addon $addon;
    private NotificationService $notificationService;
    
    public function __construct()
    {
        $this->addon = rex_addon::get('push_it');
        $this->notificationService = new NotificationService();
    }
    
    /**
     * Sendet eine Push-Notification basierend auf den übergebenen Daten
     * 
     * @param array $data
     * @param bool $isAdmin
     * @return array ['success' => bool, 'message' => string, 'result' => array|null]
     */
    public function sendNotification(array $data, bool $isAdmin): array
    {
        if (empty($data['title']) || empty($data['body'])) {
            return [
                'success' => false,
                'message' => 'Titel und Nachricht sind erforderlich.',
                'result' => null
            ];
        }
        
        try {
            $topicsArray = $this->parseTopics($data['topics'] ?? '');
            $options = $this->buildOptions($data, $isAdmin);
            
            $result = $this->notificationService->sendNotification(
                $data['title'],
                $data['body'],
                $data['url'] ?? '',
                $data['user_type'] ?? 'frontend',
                $topicsArray,
                $options
            );
            
            if ($result['success']) {
                $message = sprintf(
                    'Benachrichtigung wurde erfolgreich gesendet! Gesendet: %d, Fehler: %d, Gesamt: %d',
                    $result['sent'],
                    $result['failed'],
                    $result['total']
                );
                
                return [
                    'success' => true,
                    'message' => $message,
                    'result' => $result
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Fehler beim Senden: ' . ($result['error'] ?? 'Unbekannter Fehler'),
                    'result' => $result
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Fehler beim Senden der Benachrichtigung: ' . $e->getMessage(),
                'result' => null
            ];
        }
    }
    
    /**
     * Parst den Topics-String in ein Array
     * 
     * @param string $topicsString
     * @return array
     */
    private function parseTopics(string $topicsString): array
    {
        return array_filter(array_map('trim', explode(',', $topicsString)));
    }
    
    /**
     * Erstellt die Options für die Notification
     * 
     * @param array $data
     * @param bool $isAdmin
     * @return array
     */
    private function buildOptions(array $data, bool $isAdmin): array
    {
        $options = [];
        
        // Bilder nur für Admins
        if ($isAdmin) {
            if (!empty($data['icon'])) {
                $options['icon'] = $data['icon'];
            }
            if (!empty($data['badge'])) {
                $options['badge'] = $data['badge'];
            }
            if (!empty($data['image'])) {
                $options['image'] = $data['image'];
            }
        }
        
        return $options;
    }
    
    /**
     * Rendert JavaScript für die Send-Seite
     */
    public function renderJavaScript(): string
    {
        $publicKey = $this->addon->getConfig('publicKey');
        
        if (!$publicKey) {
            return '';
        }
        
        $nonce = \rex_response::getNonce();
        
        return '<script type="text/javascript" nonce="' . $nonce . '">
        window.PushItPublicKey = ' . json_encode($publicKey) . ';
        
        function testOwnSubscription() {
            if (!window.PushIt) {
                alert(PushIt.i18n.get("pushit_not_available"));
                return;
            }
            
            PushIt.subscribe("frontend", "test")
                .then(() => alert(PushIt.i18n.get("test_subscription_success")))
                .catch(err => alert(PushIt.i18n.get("error_prefix") + ": " + err.message));
        }
        </script>';
    }
    
    /**
     * Rendert das Send-Formular
     */
    public function renderSendForm(array $formData, bool $isAdmin): string
    {
        $title = $formData['title'] ?? '';
        $body = $formData['body'] ?? '';
        $url = $formData['url'] ?? '';
        $userType = $formData['user_type'] ?? 'frontend';
        $topics = $formData['topics'] ?? '';
        $icon = $formData['icon'] ?? '';
        $badge = $formData['badge'] ?? '';
        $image = $formData['image'] ?? '';
        
        $content = '
        <form action="' . rex_url::currentBackendPage() . '" method="post">
            <fieldset class="rex-form-col-1">
                <div class="rex-form-group form-group">
                    <label class="control-label" for="title">Titel *</label>
                    <input class="form-control" id="title" name="title" value="' . rex_escape($title) . '" required />
                    <p class="help-block">Haupt-Überschrift der Benachrichtigung</p>
                </div>
                
                <div class="rex-form-group form-group">
                    <label class="control-label" for="body">Nachricht *</label>
                    <textarea class="form-control" id="body" name="body" rows="3" required>' . rex_escape($body) . '</textarea>
                    <p class="help-block">Text der Benachrichtigung</p>
                </div>
                
                <div class="rex-form-group form-group">
                    <label class="control-label" for="url">Link (URL)</label>
                    <input class="form-control" id="url" name="url" value="' . rex_escape($url) . '" placeholder="https://example.com" />
                    <p class="help-block">URL die beim Klick auf die Benachrichtigung geöffnet wird (optional)</p>
                </div>
                
                <hr>
                <h4>Zielgruppe</h4>
                
                <div class="rex-form-group form-group">
                    <label class="control-label" for="user_type">Empfänger-Typ</label>
                    <select class="form-control" id="user_type" name="user_type">
                        <option value="frontend"' . ($userType === 'frontend' ? ' selected' : '') . '>Frontend-Benutzer</option>
                        <option value="backend"' . ($userType === 'backend' ? ' selected' : '') . '>Backend-Benutzer</option>
                        <option value="all"' . ($userType === 'all' ? ' selected' : '') . '>Alle Benutzer</option>
                    </select>
                    <p class="help-block">Wählen Sie die Zielgruppe für die Benachrichtigung</p>
                </div>
                
                <div class="rex-form-group form-group">
                    <label class="control-label" for="topics">Topics</label>
                    <input class="form-control" id="topics" name="topics" value="' . rex_escape($topics) . '" placeholder="news,updates,alerts" />
                    <p class="help-block">Kommagetrennte Liste von Topics für gezielte Benachrichtigungen (optional)</p>
                </div>';

        // Erweiterte Optionen nur für Admins
        if ($isAdmin) {
            $content .= '
                <hr>
                <h4>Erweiterte Optionen <small class="text-muted">(nur für Administratoren)</small></h4>
                
                <div class="rex-form-group form-group">
                    <label class="control-label" for="icon">Icon-URL</label>
                    <input class="form-control" id="icon" name="icon" value="' . rex_escape($icon) . '" placeholder="/media/notification-icon.png" />
                    <p class="help-block">URL zum Icon der Benachrichtigung (192x192px empfohlen)</p>
                </div>
                
                <div class="rex-form-group form-group">
                    <label class="control-label" for="badge">Badge-URL</label>
                    <input class="form-control" id="badge" name="badge" value="' . rex_escape($badge) . '" placeholder="/media/badge.png" />
                    <p class="help-block">URL zum Badge (72x72px, monochrom empfohlen)</p>
                </div>
                
                <div class="rex-form-group form-group">
                    <label class="control-label" for="image">Hero-Image-URL</label>
                    <input class="form-control" id="image" name="image" value="' . rex_escape($image) . '" placeholder="/media/hero-image.jpg" />
                    <p class="help-block">URL zum großen Bild in der Benachrichtigung</p>
                </div>';
        }

        $content .= '
                <hr>
                <div class="rex-form-group form-group">
                    <button class="btn btn-primary" name="send" value="1" type="submit">
                        <i class="rex-icon fa-paper-plane"></i> Benachrichtigung senden
                    </button>
                    <button class="btn btn-default" type="reset">
                        <i class="rex-icon fa-eraser"></i> Formular zurücksetzen
                    </button>
                </div>
            </fieldset>
        </form>';

        // Test-Funktionen hinzufügen wenn VAPID verfügbar
        if ($this->addon->getConfig('publicKey')) {
            $nonce = \rex_response::getNonce();
            $content .= '
            <div class="alert alert-info">
                <h4><i class="rex-icon fa-info-circle"></i> Test-Funktionen</h4>
                <p>Sie können Push-Notifications für sich selbst testen:</p>
                <button class="btn btn-sm btn-info" id="test-subscription-btn">
                    <i class="rex-icon fa-bell"></i> Test-Subscription erstellen
                </button>
                
                <script type="text/javascript" nonce="' . $nonce . '">
                    document.getElementById("test-subscription-btn").addEventListener("click", function() {
                        testOwnSubscription();
                    });
                </script>
            </div>';
        }

        return $content;
    }
    
    /**
     * Rendert das Preview-Panel
     */
    public function renderPreviewPanel(array $formData): string
    {
        if (empty($formData['title']) && empty($formData['body'])) {
            return '';
        }
        
        $title = $formData['title'] ?? '';
        $body = $formData['body'] ?? '';
        $url = $formData['url'] ?? '';
        $topics = $formData['topics'] ?? '';
        
        return '
        <div class="well">
            <h4>📱 Vorschau der Benachrichtigung</h4>
            <div style="background: #f5f5f5; border: 1px solid #ddd; border-radius: 6px; padding: 15px; margin: 10px 0;">
                <div style="display: flex; align-items: flex-start;">
                    <div style="width: 32px; height: 32px; background: #007cba; border-radius: 4px; margin-right: 12px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                        🔔
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: bold; font-size: 14px; margin-bottom: 4px;">
                            ' . rex_escape($title) . '
                        </div>
                        <div style="color: #666; font-size: 13px; line-height: 1.4;">
                            ' . rex_escape($body) . '
                        </div>
                        ' . ($url ? '<div style="color: #007cba; font-size: 12px; margin-top: 4px;">🔗 ' . rex_escape($url) . '</div>' : '') . '
                        ' . ($topics ? '<div style="margin-top: 6px;"><small class="text-muted">Topics: ' . rex_escape($topics) . '</small></div>' : '') . '
                    </div>
                </div>
            </div>
        </div>';
    }
    
    /**
     * Rendert das Statistiken-Panel
     */
    public function renderStatisticsPanel(): string
    {
        $sql = \rex_sql::factory();
        $sql->setQuery("
            SELECT user_type, COUNT(*) as count 
            FROM rex_push_it_subscriptions 
            WHERE active = 1 
            GROUP BY user_type
        ");

        $subscriptionStats = [
            'frontend' => 0,
            'backend' => 0
        ];

        for ($i = 0; $i < $sql->getRows(); $i++) {
            $subscriptionStats[$sql->getValue('user_type')] = $sql->getValue('count');
            $sql->next();
        }

        // Letzte Notifications zählen
        $sqlNotif = \rex_sql::factory();
        $sqlNotif->setQuery("
            SELECT COUNT(*) as total 
            FROM rex_push_it_notifications 
            WHERE created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");

        $recentNotifications = $sqlNotif->getRows() > 0 ? $sqlNotif->getValue('total') : 0;

        return '
        <div class="row">
            <div class="col-md-3">
                <div class="panel panel-primary">
                    <div class="panel-body text-center">
                        <h3>' . $subscriptionStats['frontend'] . '</h3>
                        <p>Frontend-Abonnenten</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel panel-info">
                    <div class="panel-body text-center">
                        <h3>' . $subscriptionStats['backend'] . '</h3>
                        <p>Backend-Abonnenten</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel panel-success">
                    <div class="panel-body text-center">
                        <h3>' . ($subscriptionStats['frontend'] + $subscriptionStats['backend']) . '</h3>
                        <p>Gesamt aktiv</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="panel panel-default">
                    <div class="panel-body text-center">
                        <h3>' . $recentNotifications . '</h3>
                        <p>Letzte 30 Tage</p>
                    </div>
                </div>
            </div>
        </div>';
    }
}
