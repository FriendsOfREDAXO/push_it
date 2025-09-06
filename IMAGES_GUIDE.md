# 📱 Push Notification Bilder & Icons - Anleitung

## 🖼️ URL-Formate für Bilder

### ✅ **Unterstützte URL-Formate:**

#### **1. Lokale REDAXO-Dateien:**
```
/media/notification-icon.png          ← Media-Pool Datei
/assets/addons/pushi_it/icon.png      ← AddOn-Assets
/assets/core/logo.png                 ← Core-Assets
```

#### **2. Externe HTTPS-URLs:**
```
https://cdn.example.com/icon.png      ← CDN/Externe Quelle
https://example.com/media/badge.png   ← Externe Website
https://avatars.githubusercontent.com/u/123456 ← API-Endpoints
```

#### **3. Absolute lokale Pfade:**
```
https://localhost:8443/media/icon.png  ← Vollständige URL (bei externem Zugriff)
https://yoursite.com/media/icon.png    ← Live-Domain
```

---

## 🚫 **Nicht unterstützt:**

- ❌ **HTTP** (unverschlüsselt) - nur **HTTPS** erlaubt!
- ❌ **Relative Pfade** ohne `/` - z.B. `media/icon.png`
- ❌ **Data-URLs** - z.B. `data:image/png;base64,...`
- ❌ **Lokale file://** Pfade

---

## 📐 **Bild-Spezifikationen:**

### **🔹 Icon (Hauptsymbol):**
- **Größe:** 192x192px (empfohlen)
- **Format:** PNG, JPG, WebP
- **Verwendung:** Haupticon der Benachrichtigung
- **Beispiel:** App-Logo, Firmenlogo

### **🔹 Badge (Android-Symbol):**
- **Größe:** 72x72px (empfohlen)
- **Format:** PNG (monochrom!)
- **Farbe:** Schwarz/Weiß, keine Farben
- **Verwendung:** Android Notification Bar
- **Beispiel:** Vereinfachtes Icon, Zahl, Symbol

### **🔹 Image (Hero-Bild):**
- **Größe:** 360x240px (3:2 Verhältnis)
- **Format:** JPG, PNG, WebP
- **Verwendung:** Großes Bild in der Notification
- **Beispiel:** Produktbild, News-Foto, Banner

---

## 💡 **Beispiele aus der Praxis:**

### **Lokale Dateien (empfohlen):**
```php
$options = [
    'icon' => '/media/company-logo.png',
    'badge' => '/media/notification-badge.png', 
    'image' => '/media/product-hero.jpg'
];
```

### **CDN/Externe Quellen:**
```php
$options = [
    'icon' => 'https://cdn.yoursite.com/icons/notification.png',
    'badge' => 'https://cdn.yoursite.com/badges/new.png',
    'image' => 'https://images.yoursite.com/products/hero.jpg'
];
```

### **Mix aus lokal und extern:**
```php
$options = [
    'icon' => '/assets/addons/pushi_it/icon.png',      // Lokal
    'badge' => '/media/custom-badge.png',              // Media-Pool
    'image' => 'https://cdn.example.com/hero.jpg'     // Extern
];
```

---

## 🔧 **Technische Details:**

### **Browser-Verhalten:**
- Bilder werden **einmalig geladen** beim Empfang der Notification
- **Caching** durch Browser (ca. 24h)
- Bei **Offline-Geräten**: Nur bereits gecachte Bilder werden angezeigt

### **CORS-Anforderungen:**
- Externe Bilder benötigen **CORS-Header**
- `Access-Control-Allow-Origin: *` oder spezifische Domain
- Bei CDNs meist automatisch konfiguriert

### **Performance-Tipps:**
- **Lokale Dateien** = Schnellstes Laden
- **Optimierte Bildgrößen** verwenden (nicht > 500KB pro Bild)
- **WebP-Format** für beste Kompression
- **CDN nutzen** für externe Bilder

---

## 🚀 **Best Practices:**

1. **Fallback immer definieren:**
   ```php
   'icon' => $customIcon ?: '/assets/addons/pushi_it/icon.png'
   ```

2. **Bilder vorab testen:**
   - URL im Browser öffnen
   - HTTPS-Erreichbarkeit prüfen
   - Dateigröße kontrollieren

3. **Responsive Design:**
   - Verschiedene Bildschirmgrößen berücksichtigen
   - Icon sollte auch klein gut erkennbar sein

4. **Branding konsistent halten:**
   - Einheitliche Icons verwenden
   - Corporate Design einhalten
   - Markenfarben respektieren
