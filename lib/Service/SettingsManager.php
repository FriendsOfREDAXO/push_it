<?php

namespace FriendsOfREDAXO\PushIt\Service;

use rex_addon;
use rex_view;
use rex_escape;
use rex_url;
use rex_i18n;
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
                'message' => rex_i18n::msg('pushit_webpush_library_warning'),
                'keys' => null
            ];
        }
        
        try {
            $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
            
            $this->addon->setConfig('publicKey', $keys['publicKey']);
            $this->addon->setConfig('privateKey', $keys['privateKey']);
            
            return [
                'success' => true,
                'message' => rex_i18n::msg('pushit_vapid_keys_generated'),
                'keys' => $keys
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => rex_i18n::msg('pushit_vapid_generate_error', '', $e->getMessage()),
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
                    ? rex_i18n::msg('pushit_backend_token_regenerated', '', $affectedRows)
                    : rex_i18n::msg('pushit_backend_token_regenerated_simple');
            } else {
                $message = rex_i18n::msg('pushit_backend_token_generated');
            }
            
            return [
                'success' => true,
                'message' => $message,
                'token' => $newToken
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => rex_i18n::msg('pushit_backend_token_generate_error', '', $e->getMessage()),
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
            <label class="control-label" for="subject">' . rex_i18n::msg('pushit_subject_label') . '</label>
            <input class="form-control" id="subject" name="subject" value="' . rex_escape($settings['subject']) . '" />
            <p class="help-block">' . rex_i18n::msg('pushit_subject_help') . '</p>
        </div>
        
        <div class="rex-form-group form-group">
            <label class="control-label" for="publicKey">' . rex_i18n::msg('pushit_vapid_public_key_label') . '</label>
            <div class="input-group">
                <textarea class="form-control" id="publicKey" name="publicKey" rows="3">' . rex_escape($settings['publicKey']) . '</textarea>
                <span class="input-group-btn">
                    <button class="btn btn-' . ($libAvailable ? 'success' : 'warning') . '" name="generate" value="1" type="submit" ' . ($libAvailable ? '' : 'disabled') . '>
                        <i class="rex-icon fa-key"></i> ' . ($hasKeys ? rex_i18n::msg('pushit_regenerate_button') : rex_i18n::msg('pushit_generate_button')) . '
                    </button>
                </span>
            </div>
            ' . (!$libAvailable ? '<p class="help-block text-warning">' . rex_i18n::msg('pushit_webpush_library_warning') . '</p>' : '') . '
        </div>
        
        <div class="rex-form-group form-group">
            <label class="control-label" for="privateKey">' . rex_i18n::msg('pushit_vapid_private_key_label') . '</label>
            <textarea class="form-control" id="privateKey" name="privateKey" rows="3">' . rex_escape($settings['privateKey']) . '</textarea>
            <p class="help-block">' . rex_i18n::msg('pushit_vapid_private_key_help') . '</p>
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
        $statusText = $hasToken ? rex_i18n::msg('pushit_token_present') : rex_i18n::msg('pushit_no_token_generated');
        
        $action = $hasToken ? rex_i18n::msg('pushit_regenerate_button') : rex_i18n::msg('pushit_generate_button');
        $confirmMsg = rex_i18n::msg('pushit_backend_token_confirm', '', $action);
        $warningMsg = $hasToken ? '\\n\\n' . rex_i18n::msg('pushit_backend_token_confirm_warning') : '';
        
        $warningText = $hasToken ? 
            '<div class="alert alert-warning" style="margin-top: 10px;">
                <strong>⚠️ ' . rex_i18n::msg('pushit_backend_token_warning_text') . '</strong>
            </div>' : '';
        
        return '
        <div class="rex-form-group form-group">
            <label class="control-label" for="backend_token">' . rex_i18n::msg('pushit_backend_token_label') . '</label>
            <div class="input-group">
                <input class="form-control" id="backend_token" name="backend_token" value="' . rex_escape($settings['backend_token']) . '" readonly />
                <span class="input-group-btn">
                    <button class="btn btn-warning" name="generate_token" value="1" type="submit"
                            onclick="return confirm(\'' . $confirmMsg . $warningMsg . '\')">
                        <i class="rex-icon fa-refresh"></i> ' . $action . '
                    </button>
                </span>
            </div>
            <p class="help-block">
                ' . rex_i18n::msg('pushit_backend_token_help') . '
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
                    ' . rex_i18n::msg('pushit_backend_notifications_enable') . '
                </label>
                <p class="help-block">' . rex_i18n::msg('pushit_backend_notifications_help') . '</p>
            </div>
        </div>
        
        <div class="rex-form-group form-group">
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="frontend_enabled" value="1" ' . ($settings['frontend_enabled'] ? 'checked' : '') . ' />
                    ' . rex_i18n::msg('pushit_frontend_notifications_enable') . '
                </label>
                <p class="help-block">' . rex_i18n::msg('pushit_frontend_notifications_help') . '</p>
            </div>
        </div>
        
        <div class="rex-form-group form-group">
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="admin_notifications" value="1" ' . ($settings['admin_notifications'] ? 'checked' : '') . ' />
                    ' . rex_i18n::msg('pushit_admin_notifications_enable') . '
                </label>
                <p class="help-block">' . rex_i18n::msg('pushit_admin_notifications_help') . '</p>
            </div>
        </div>
        
        <div class="rex-form-group form-group">
            <label class="control-label" for="backend_only_topics">' . rex_i18n::msg('pushit_backend_only_topics_label') . '</label>
            <input class="form-control" id="backend_only_topics" name="backend_only_topics" 
                   value="' . rex_escape($settings['backend_only_topics']) . '" 
                   placeholder="system,admin,critical" />
            <p class="help-block">
                <strong>' . rex_i18n::msg('pushit_topic_security_description') . '</strong><br>
                ' . rex_i18n::msg('pushit_topics_comma_separated') . '
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
                <i class="rex-icon fa-save"></i> ' . rex_i18n::msg('pushit_save_settings_button') . '
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
                <h4><i class="rex-icon fa-info-circle"></i> ' . rex_i18n::msg('pushit_topic_security_title') . '</h4>
                <p>' . rex_i18n::msg('pushit_no_backend_only_topics') . '</p>
            </div>';
        }
        
        $topicsList = '';
        foreach ($backendOnlyTopics as $topic) {
            $topicsList .= '<span class="label label-warning">' . rex_escape($topic) . '</span> ';
        }
        
        return '<div class="alert alert-warning">
            <h4><i class="rex-icon fa-shield"></i> ' . rex_i18n::msg('pushit_topic_security_title') . '</h4>
            <p><strong>' . rex_i18n::msg('pushit_backend_only_topics_label') . ':</strong> ' . $topicsList . '</p>
            <p>' . rex_i18n::msg('pushit_backend_only_topics_info') . '</p>
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
            $issues[] = rex_i18n::msg('pushit_subject_not_configured');
        } elseif (!filter_var($settings['subject'], FILTER_VALIDATE_EMAIL) && !filter_var($settings['subject'], FILTER_VALIDATE_URL)) {
            $issues[] = rex_i18n::msg('pushit_subject_invalid');
        }
        
        if (empty($settings['publicKey'])) {
            $issues[] = rex_i18n::msg('pushit_vapid_public_key_not_configured');
        }
        
        if (empty($settings['privateKey'])) {
            $issues[] = rex_i18n::msg('pushit_vapid_private_key_not_configured');
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
        $vapidText = $validation['valid'] ? rex_i18n::msg('pushit_vapid_correctly_configured') : rex_i18n::msg('pushit_vapid_incomplete');
        
        $tokenStatus = !empty($settings['backend_token']) ? 'success' : 'warning';
        $tokenText = !empty($settings['backend_token']) ? rex_i18n::msg('pushit_backend_token_available') : rex_i18n::msg('pushit_backend_token_not_generated');
        
        $html = '
        <div class="alert alert-info">
            <h4><i class="rex-icon fa-info-circle"></i> ' . rex_i18n::msg('pushit_configuration_status') . '</h4>
            <ul class="list-unstyled">
                <li><span class="label label-' . $vapidStatus . '">' . $vapidText . '</span></li>
                <li><span class="label label-' . $tokenStatus . '">' . $tokenText . '</span></li>
                <li><span class="label label-' . ($settings['backend_enabled'] ? 'success' : 'default') . '">' . ($settings['backend_enabled'] ? rex_i18n::msg('pushit_backend_enabled') : rex_i18n::msg('pushit_backend_disabled')) . '</span></li>
                <li><span class="label label-' . ($settings['frontend_enabled'] ? 'success' : 'default') . '">' . ($settings['frontend_enabled'] ? rex_i18n::msg('pushit_frontend_enabled') : rex_i18n::msg('pushit_frontend_disabled')) . '</span></li>
            </ul>';
        
        if (!$validation['valid']) {
            $html .= '<h5>' . rex_i18n::msg('pushit_problems') . ':</h5><ul>';
            foreach ($validation['issues'] as $issue) {
                $html .= '<li class="text-danger">' . rex_escape($issue) . '</li>';
            }
            $html .= '</ul>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
}
