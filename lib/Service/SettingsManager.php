<?php

namespace FriendsOfREDAXO\PushIt\Service;

use rex_addon;
use rex_view;
use rex_escape;
use rex_url;
use Exception;

/**
 * Service-Klasse für die Verwaltung der Push-It Einstellungen
 */
class SettingsManager
{
    private rex_addon $addon;
    
    public function __construct()
    {
        $this->addon = rex_addon::get('push_it');
    }
    
    /**
     * Prüft ob die Minishlink WebPush Library verfügbar ist
     * 
     * @return bool
     */
    public function isLibraryAvailable(): bool
    {
        return class_exists('\\Minishlink\\WebPush\\VAPID');
    }
    
    /**
     * Speichert die Einstellungen
     * 
     * @param array $data
     * @return bool
     */
    public function saveSettings(array $data): bool
    {
        try {
            $this->addon->setConfig('subject', $data['subject'] ?? '');
            $this->addon->setConfig('publicKey', $data['publicKey'] ?? '');
            $this->addon->setConfig('privateKey', $data['privateKey'] ?? '');
            $this->addon->setConfig('backend_token', $data['backend_token'] ?? '');
            $this->addon->setConfig('backend_enabled', $data['backend_enabled'] ?? false);
            $this->addon->setConfig('frontend_enabled', $data['frontend_enabled'] ?? false);
            $this->addon->setConfig('admin_notifications', $data['admin_notifications'] ?? false);
            $this->addon->setConfig('backend_only_topics', $data['backend_only_topics'] ?? 'system,admin,critical');
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Generiert neue VAPID-Schlüssel
     * 
     * @return array ['success' => bool, 'message' => string, 'keys' => array|null]
     */
    public function generateVapidKeys(): array
    {
        if (!$this->isLibraryAvailable()) {
            return [
                'success' => false,
                'message' => 'Composer-Abhängigkeiten fehlen. Bitte im AddOn-Verzeichnis "composer install" ausführen.',
                'keys' => null
            ];
        }
        
        try {
            $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
            
            $this->addon->setConfig('publicKey', $keys['publicKey']);
            $this->addon->setConfig('privateKey', $keys['privateKey']);
            
            return [
                'success' => true,
                'message' => 'VAPID-Schlüssel wurden erfolgreich generiert.',
                'keys' => $keys
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Fehler beim Generieren der VAPID-Schlüssel: ' . $e->getMessage(),
                'keys' => null
            ];
        }
    }
    
    /**
     * Generiert einen neuen Backend-Token
     * 
     * @return array ['success' => bool, 'message' => string, 'token' => string|null]
     */
    public function generateBackendToken(): array
    {
        try {
            // Alten Token merken um Subscriptions zu invalidieren
            $oldToken = $this->addon->getConfig('backend_token');
            
            $newToken = bin2hex(random_bytes(32)); // 64 Zeichen hexadezimal
            $this->addon->setConfig('backend_token', $newToken);
            
            // Bei Token-Änderung alle Backend-Subscriptions deaktivieren
            // Nutzer müssen sich neu anmelden mit dem neuen Token
            if ($oldToken && $oldToken !== $newToken) {
                $sql = \rex_sql::factory();
                $sql->setQuery("
                    UPDATE rex_push_it_subscriptions 
                    SET active = 0, 
                        updated = NOW(),
                        topics = CONCAT(topics, ',token-expired')
                    WHERE user_type = 'backend' AND active = 1
                ");
                
                $affectedRows = $sql->getRows();
                $message = $affectedRows > 0 
                    ? "Backend-Token wurde neu generiert. {$affectedRows} bestehende Backend-Subscriptions wurden deaktiviert."
                    : 'Backend-Token wurde neu generiert.';
            } else {
                $message = 'Backend-Token wurde erfolgreich generiert.';
            }
            
            return [
                'success' => true,
                'message' => $message,
                'token' => $newToken
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Fehler beim Generieren des Backend-Tokens: ' . $e->getMessage(),
                'token' => null
            ];
        }
    }
    
    /**
     * Lädt alle aktuellen Einstellungen
     * 
     * @return array
     */
    public function getSettings(): array
    {
        return [
            'subject' => $this->addon->getConfig('subject', ''),
            'publicKey' => $this->addon->getConfig('publicKey', ''),
            'privateKey' => $this->addon->getConfig('privateKey', ''),
            'backend_token' => $this->addon->getConfig('backend_token', ''),
            'backend_enabled' => $this->addon->getConfig('backend_enabled', true),
            'frontend_enabled' => $this->addon->getConfig('frontend_enabled', true),
            'admin_notifications' => $this->addon->getConfig('admin_notifications', true),
            'backend_only_topics' => $this->addon->getConfig('backend_only_topics', 'system,admin,critical')
        ];
    }
    
    /**
     * Erstellt das HTML für das Einstellungsformular
     * 
     * @param array $settings
     * @return string
     */
    public function renderSettingsForm(array $settings): string
    {
        $libAvailable = $this->isLibraryAvailable();
        $hasBackendToken = !empty($settings['backend_token']);
        
        return '
        <form action="' . rex_url::currentBackendPage() . '" method="post">
            <fieldset class="rex-form-col-1">
                ' . $this->renderVapidSection($settings, $libAvailable) . '
                ' . $this->renderBackendTokenSection($settings, $hasBackendToken) . '
                ' . $this->renderFeatureSection($settings) . '
                ' . $this->renderActionButtons() . '
            </fieldset>
        </form>';
    }
    
    /**
     * Erstellt den VAPID-Bereich des Formulars
     * 
     * @param array $settings
     * @param bool $libAvailable
     * @return string
     */
    private function renderVapidSection(array $settings, bool $libAvailable): string
    {
        $hasKeys = !empty($settings['publicKey']) && !empty($settings['privateKey']);
        
        return '
        <div class="rex-form-group form-group">
            <label class="control-label" for="subject">Subject (mailto: oder URL)</label>
            <input class="form-control" id="subject" name="subject" value="' . rex_escape($settings['subject']) . '" />
            <p class="help-block">Erforderlich für VAPID. Verwenden Sie eine mailto:-Adresse oder eine URL Ihrer Domain.</p>
        </div>
        
        <div class="rex-form-group form-group">
            <label class="control-label" for="publicKey">VAPID Public Key</label>
            <div class="input-group">
                <textarea class="form-control" id="publicKey" name="publicKey" rows="3">' . rex_escape($settings['publicKey']) . '</textarea>
                <span class="input-group-btn">
                    <button class="btn btn-' . ($libAvailable ? 'success' : 'warning') . '" name="generate" value="1" type="submit" ' . ($libAvailable ? '' : 'disabled') . '>
                        <i class="rex-icon fa-key"></i> ' . ($hasKeys ? 'Neu generieren' : 'Generieren') . '
                    </button>
                </span>
            </div>
            ' . (!$libAvailable ? '<p class="help-block text-warning">⚠️ WebPush-Library nicht verfügbar. Bitte "composer install" im AddOn-Verzeichnis ausführen.</p>' : '') . '
        </div>
        
        <div class="rex-form-group form-group">
            <label class="control-label" for="privateKey">VAPID Private Key</label>
            <textarea class="form-control" id="privateKey" name="privateKey" rows="3">' . rex_escape($settings['privateKey']) . '</textarea>
            <p class="help-block">⚠️ Privater Schlüssel - niemals öffentlich zugänglich machen!</p>
        </div>
        
        <hr>';
    }
    
    /**
     * Erstellt den Backend-Token-Bereich des Formulars
     * 
     * @param array $settings
     * @param bool $hasToken
     * @return string
     */
    private function renderBackendTokenSection(array $settings, bool $hasToken): string
    {
        $tokenPreview = $hasToken ? substr($settings['backend_token'], 0, 16) . '...' : '';
        $statusClass = $hasToken ? 'success' : 'warning';
        $statusText = $hasToken ? 'Token vorhanden' : 'Kein Token generiert';
        
        $warningText = $hasToken ? 
            '<div class="alert alert-warning" style="margin-top: 10px;">
                <strong>⚠️ Wichtiger Hinweis:</strong> Bei der Neu-Generierung des Backend-Tokens werden alle bestehenden Backend-Subscriptions ungültig. 
                Alle Backend-Nutzer müssen sich nach der Token-Änderung erneut für Push-Benachrichtigungen anmelden.
            </div>' : '';
        
        return '
        <div class="rex-form-group form-group">
            <label class="control-label" for="backend_token">Backend-Token</label>
            <div class="input-group">
                <input class="form-control" id="backend_token" name="backend_token" value="' . rex_escape($settings['backend_token']) . '" readonly />
                <span class="input-group-btn">
                    <button class="btn btn-warning" name="generate_token" value="1" type="submit"
                            onclick="return confirm(\'Backend-Token ' . ($hasToken ? 'neu generieren' : 'generieren') . '?' . ($hasToken ? '\\n\\n⚠️ ACHTUNG: Alle Backend-Nutzer müssen sich nach der Token-Änderung erneut für Push-Benachrichtigungen anmelden!' : '') . '\')">
                        <i class="rex-icon fa-refresh"></i> ' . ($hasToken ? 'Neu generieren' : 'Generieren') . '
                    </button>
                </span>
            </div>
            <p class="help-block">
                Sicherer Token zur Authentifizierung von Backend-Subscriptions. Wird automatisch an Backend-JavaScript übertragen.
                <br><span class="label label-' . $statusClass . '">' . $statusText . '</span>
                ' . ($hasToken ? '<br><small class="text-muted">Vorschau: ' . $tokenPreview . '</small>' : '') . '
            </p>
            ' . $warningText . '
        </div>
        
        <hr>';
    }
    
    /**
     * Erstellt den Feature-Bereich des Formulars
     * 
     * @param array $settings
     * @return string
     */
    private function renderFeatureSection(array $settings): string
    {
        return '
        <div class="rex-form-group form-group">
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="backend_enabled" value="1" ' . ($settings['backend_enabled'] ? 'checked' : '') . ' />
                    Backend-Benachrichtigungen aktivieren
                </label>
                <p class="help-block">Ermöglicht Push-Notifications für REDAXO-Backend-Benutzer.</p>
            </div>
        </div>
        
        <div class="rex-form-group form-group">
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="frontend_enabled" value="1" ' . ($settings['frontend_enabled'] ? 'checked' : '') . ' />
                    Frontend-Benachrichtigungen aktivieren
                </label>
                <p class="help-block">Ermöglicht Push-Notifications für Website-Besucher.</p>
            </div>
        </div>
        
        <div class="rex-form-group form-group">
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="admin_notifications" value="1" ' . ($settings['admin_notifications'] ? 'checked' : '') . ' />
                    Automatische Admin-Benachrichtigungen
                </label>
                <p class="help-block">Sendet automatisch Benachrichtigungen bei System-Events (Fehler, Updates, etc.).</p>
            </div>
        </div>
        
        <div class="rex-form-group form-group">
            <label class="control-label" for="backend_only_topics">Backend-Only Topics</label>
            <input class="form-control" id="backend_only_topics" name="backend_only_topics" 
                   value="' . rex_escape($settings['backend_only_topics']) . '" 
                   placeholder="system,admin,critical" />
            <p class="help-block">
                <strong>Sicherheitseinstellung:</strong> Diese Topics können nur von Backend-Benutzern abonniert werden und sind für Frontend-Benutzer nicht verfügbar.<br>
                Topics mit Komma trennen (z.B. system,admin,critical). Default: system,admin,critical
            </p>
        </div>';
    }
    
    /**
     * Erstellt die Action-Buttons
     * 
     * @return string
     */
    private function renderActionButtons(): string
    {
        return '
        <div class="rex-form-group form-group">
            <button class="btn btn-primary" name="save" value="1" type="submit">
                <i class="rex-icon fa-save"></i> Einstellungen speichern
            </button>
        </div>';
    }
    
    /**
     * Prüft ob ein Topic Backend-Only ist
     * 
     * @param string $topic
     * @return bool
     */
    public function isBackendOnlyTopic(string $topic): bool
    {
        $backendOnlyTopics = $this->getBackendOnlyTopics();
        return in_array(trim($topic), $backendOnlyTopics, true);
    }
    
    /**
     * Gibt die Liste der Backend-Only Topics zurück
     * 
     * @return array
     */
    public function getBackendOnlyTopics(): array
    {
        $topicsString = $this->addon->getConfig('backend_only_topics', 'system,admin,critical');
        return array_filter(array_map('trim', explode(',', $topicsString)));
    }
    
    /**
     * Rendert eine Übersicht der Topic-Sicherheitseinstellungen
     * 
     * @return string
     */
    public function renderTopicSecurityInfo(): string
    {
        $backendOnlyTopics = $this->getBackendOnlyTopics();
        
        if (empty($backendOnlyTopics)) {
            return '<div class="alert alert-info">
                <h4><i class="rex-icon fa-info-circle"></i> Topic-Sicherheit</h4>
                <p>Aktuell sind keine Backend-Only Topics konfiguriert. Alle Topics können von Frontend- und Backend-Benutzern abonniert werden.</p>
            </div>';
        }
        
        $topicsList = '';
        foreach ($backendOnlyTopics as $topic) {
            $topicsList .= '<span class="label label-warning">' . rex_escape($topic) . '</span> ';
        }
        
        return '<div class="alert alert-warning">
            <h4><i class="rex-icon fa-shield"></i> Topic-Sicherheit</h4>
            <p><strong>Backend-Only Topics:</strong> ' . $topicsList . '</p>
            <p>Diese Topics können nur von Backend-Benutzern abonniert werden und sind für Frontend-Benutzer gesperrt.</p>
        </div>';
    }
    
    /**
     * Filtert Topics und entfernt Backend-Only Topics für Frontend-Subscriptions
     * 
     * @param string $topics
     * @param string $userType
     * @return string
     */
    public function filterTopicsForUserType(string $topics, string $userType): string
    {
        if ($userType === 'backend') {
            // Backend-User können alle Topics haben
            return $topics;
        }
        
        // Frontend-User: Backend-Only Topics entfernen
        $requestedTopics = array_filter(array_map('trim', explode(',', $topics)));
        $backendOnlyTopics = $this->getBackendOnlyTopics();
        
        $blockedTopics = array_intersect($requestedTopics, $backendOnlyTopics);
        if (!empty($blockedTopics)) {
            // Sicherheitswarnung loggen
            error_log(sprintf(
                'SECURITY WARNING: Frontend user attempted to subscribe to backend-only topics: %s from IP %s',
                implode(',', $blockedTopics),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ));
        }
        
        $allowedTopics = array_diff($requestedTopics, $backendOnlyTopics);
        
        return implode(',', $allowedTopics);
    }
    
    /**
     * Validiert die VAPID-Konfiguration
     * 
     * @return array ['valid' => bool, 'issues' => array]
     */
    public function validateVapidConfig(): array
    {
        $settings = $this->getSettings();
        $issues = [];
        
        if (empty($settings['subject'])) {
            $issues[] = 'Subject ist nicht konfiguriert';
        } elseif (!filter_var($settings['subject'], FILTER_VALIDATE_EMAIL) && !filter_var($settings['subject'], FILTER_VALIDATE_URL)) {
            $issues[] = 'Subject muss eine gültige E-Mail-Adresse oder URL sein';
        }
        
        if (empty($settings['publicKey'])) {
            $issues[] = 'VAPID Public Key ist nicht konfiguriert';
        }
        
        if (empty($settings['privateKey'])) {
            $issues[] = 'VAPID Private Key ist nicht konfiguriert';
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues
        ];
    }
    
    /**
     * Erstellt ein Status-Panel für die Konfiguration
     * 
     * @return string
     */
    public function renderConfigStatus(): string
    {
        $validation = $this->validateVapidConfig();
        $settings = $this->getSettings();
        
        $vapidStatus = $validation['valid'] ? 'success' : 'danger';
        $vapidText = $validation['valid'] ? 'VAPID korrekt konfiguriert' : 'VAPID unvollständig';
        
        $tokenStatus = !empty($settings['backend_token']) ? 'success' : 'warning';
        $tokenText = !empty($settings['backend_token']) ? 'Backend-Token verfügbar' : 'Backend-Token nicht generiert';
        
        $html = '
        <div class="alert alert-info">
            <h4><i class="rex-icon fa-info-circle"></i> Konfigurations-Status</h4>
            <ul class="list-unstyled">
                <li><span class="label label-' . $vapidStatus . '">' . $vapidText . '</span></li>
                <li><span class="label label-' . $tokenStatus . '">' . $tokenText . '</span></li>
                <li><span class="label label-' . ($settings['backend_enabled'] ? 'success' : 'default') . '">Backend: ' . ($settings['backend_enabled'] ? 'Aktiviert' : 'Deaktiviert') . '</span></li>
                <li><span class="label label-' . ($settings['frontend_enabled'] ? 'success' : 'default') . '">Frontend: ' . ($settings['frontend_enabled'] ? 'Aktiviert' : 'Deaktiviert') . '</span></li>
            </ul>';
        
        if (!$validation['valid']) {
            $html .= '<h5>Probleme:</h5><ul>';
            foreach ($validation['issues'] as $issue) {
                $html .= '<li class="text-danger">' . rex_escape($issue) . '</li>';
            }
            $html .= '</ul>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
}
