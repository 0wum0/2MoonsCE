# 2Moons CE — Community Edition
 [![Discussions](https://github.com/0wum0/2MoonsCE/discussions/16)]
<div align="center">

[![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-8892BF.svg?style=for-the-badge&logo=php)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPLv2-blue.svg?style=for-the-badge)](LICENSE)
[![Version](https://img.shields.io/badge/Version-CE--2025-00ffff.svg?style=for-the-badge)](#changelog)
[![Engine](https://img.shields.io/badge/Template-Twig%203-brightgreen.svg?style=for-the-badge)](https://twig.symfony.com/)
[![DB](https://img.shields.io/badge/Database-PDO%20%2F%20MariaDB-orange.svg?style=for-the-badge)](https://www.php.net/manual/en/book.pdo.php)

</div>

---

## Das Spiel

**2Moons CE – Community Edition** ist ein rundenbasiertes Weltraum-Strategiespiel im Browser.
Du startest mit einem einzelnen Planeten irgendwo in einem von neun Galaxien – und von da an liegt es an dir, wie weit du kommst.

Baue Bergwerke und Kraftwerke, um deine Produktion von **Metall, Kristall und Deuterium** hochzuskalieren. Erforsche neue Technologien, die deinen Flotten mehr Schlagkraft und deinen Verteidigungsanlagen mehr Standfestigkeit geben. Schick Kolonisationsschiffe aus, um neue Planeten zu besiedeln, errichte Monde und nutze Sprungstore, um Flotten blitzschnell zu verlegen.

Kämpfe alleine oder schließe dich einer **Allianz** an – plane Angriffe gemeinsam, schütze schwächere Mitglieder, schließe Nichtangriffspakte oder erkläre anderen Allianzen den Krieg. Die Galaxiekarte zeigt dir in Echtzeit, wer wo fliegt und wer ein lohnendes Ziel darstellt.

> _Das Universum gehört denen, die es sich nehmen._

**2Moons CE** ist ein Community-Fork des klassischen Open-Source-Spiels **2Moons**, vollständig modernisiert für **PHP 8.3+**, mit einem komplett neu gestaltetem Interface, PDO-Datenbankschicht, Twig-Templates und einem erweiterbaren Plugin- und Modulsystem.

---

## Features auf einen Blick

### Gameplay
- **Ressourcenwirtschaft** — Metall, Kristall, Deuterium; Minen, Solar- und Fusionskraftwerke
- **Forschungsbaum** — über 20 Technologien, von Antrieben bis zur Waffentechnik
- **Gebäudesystem** — Bauschleife mit mehreren parallelen Aufträgen, Abrisslogik
- **Flottenmanagement** — über 15 Schiffstypen, Missionen (Angriff, Transport, Spionage, Kolonisation …)
- **Verteidigung** — Geschütztürme, Raketen, Planetarer Schutzschild
- **Galaxiekarte** — interaktive 3D-Ansicht mit Flottenlinien und Planeteninformationen
- **Allianzen & Diplomatie** — Mitgliederverwaltung, Rundbriefe, Diplomatiestatus
- **Offiziere** — bezahlbare Boni für Produktion, Kampf, Forschung
- **Kampfberichte** — detaillierte Berichte mit Verlusten und Beute
- **Forum** — integriertes Spielerforum mit Kategorien, Threads, Likes, Moderation

### Technik & Sicherheit
- **PHP 8.3+** — `strict_types` in allen Dateien, moderne Union Types
- **PDO prepared statements** — kein SQL-Injection-Risiko
- **Twig 3 Template Engine** — vollständig migriert, kein Smarty mehr
- **Plugin-System** — erweiterbar über Plugins mit eigenem Manifest, Assets und SQL-Migrations
- **Modul-System** — Gameplay-Module (Produktion, Queue) als Hook-basierte Wrapper
- **AJAX-Infrastruktur** — Formularaktionen ohne Seitenreload, Live-Ressourcenleiste
- **XSS-Schutz** — Eingaben sanitized, Ausgaben escaped
- **Mehrsprachig** — DE, EN, ES, FR

---

## Servervoraussetzungen

| Komponente | Minimum | Empfohlen |
|---|---|---|
| **PHP** | 8.1 | 8.3+ |
| **MySQL / MariaDB** | MySQL 5.7 / MariaDB 10.4 | MariaDB 10.11+ |
| **Webserver** | Apache 2.4 / Nginx 1.18 | Apache 2.4 |
| **PHP-Erweiterungen** | `pdo_mysql`, `mbstring`, `gd`, `json`, `curl`, `zip` | + `opcache`, `intl` |
| **Composer** | 2.x | 2.x |
| **Speicherplatz** | 200 MB | 1 GB+ |

**Apache-Konfiguration:**
`mod_rewrite` muss aktiviert sein. Die enthaltene `.htaccess` setzt alle Pfade automatisch.

**PHP-Einstellungen** (empfohlen in `php.ini`):
```ini
memory_limit = 128M
upload_max_filesize = 16M
post_max_size = 16M
max_execution_time = 60
```

---

## Installation

### Schritt 1 — Repository klonen

```bash
git clone https://github.com/0wum0/2MoonsCE.git
cd 2MoonsCE
```

### Schritt 2 — Abhängigkeiten installieren

```bash
composer install --no-dev --optimize-autoloader
```

### Schritt 3 — Datenbank anlegen

Erstelle eine leere MySQL/MariaDB-Datenbank mit UTF-8 Zeichensatz:

```sql
CREATE DATABASE 2moons_ce CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '2moons'@'localhost' IDENTIFIED BY 'dein_passwort';
GRANT ALL PRIVILEGES ON 2moons_ce.* TO '2moons'@'localhost';
FLUSH PRIVILEGES;
```

### Schritt 4 — Web-Installer aufrufen

Öffne im Browser:
```
http://deine-domain.de/install/
```

Der Installer führt dich durch:
1. Systemprüfung (PHP-Version, Erweiterungen, Schreibrechte)
2. Datenbankverbindung eingeben
3. Grundkonfiguration (Spielname, Universe-Einstellungen, Admin-Account)
4. Datenbank-Schema installieren
5. Installationsverzeichnis sperren

### Schritt 5 — Installer sperren

Nach erfolgreicher Installation **muss** das Install-Verzeichnis gesperrt werden.
Entweder automatisch über den letzten Installer-Schritt oder manuell:

```bash
# Option A: Verzeichnis löschen
rm -rf install/

# Option B: .htaccess im install/-Ordner schützt bereits via Deny all
```

### Schritt 6 — Cronjob einrichten

Der Cronjob ist essenziell — er verarbeitet Flottenankünfte, Ressourcenproduktion und Kampfberichte.

```bash
# Empfohlen: jede Minute
* * * * * php /pfad/zum/spiel/cronjob.php > /dev/null 2>&1
```

### Schritt 7 — Admin-Panel

```
URL:      http://deine-domain.de/admin.php
Login:    der beim Setup gewählte Admin-Account
```

---

## Projektstruktur

```
2MoonsCE/
├── game.php              # Haupt-Game-Controller
├── index.php             # Login / Landing Page
├── admin.php             # Admin-Panel Entry
├── cronjob.php           # Cronjob-Handler (Flotten, Produktion)
├── includes/
│   ├── common.php        # Core Bootstrap (Session, DB, User)
│   ├── constants.php     # Globale Konstanten & Module-IDs
│   ├── classes/          # Kern-Klassen (Database, Config, Cache …)
│   │   └── modules/      # Gameplay-Module (Production, Queue)
│   ├── pages/
│   │   ├── game/         # Seiten-Controller (Buildings, Fleet, Forum …)
│   │   ├── login/        # Login / Register / Recover
│   │   └── adm/          # Admin-Seiten
│   └── libs/             # Drittanbieter-Bibliotheken
├── install/              # Web-Installer
├── plugins/              # Plugin-Verzeichnis
├── scripts/
│   └── game/             # Frontend-JavaScript (ajax.js, …)
├── styles/
│   └── templates/        # Twig-Templates (game/, adm/, login/)
├── language/             # Sprachdateien (de/, en/, es/, fr/)
└── cache/                # Twig-Compilat-Cache
```

---

## Changelog

> Vollständiger Changelog: [CHANGELOG.md](CHANGELOG.md)

### März 2026 — Support-System, Messages v3, Combat Engine v3, Galaxy Map

**Support-System UI**
- Komplettes UI-Overhaul: futuristisches Ticket-Center mit SVG-Icons, Status-Badges, Animationen
- Neues Ticket öffnet als Modal-Popup (ohne Game-Header)
- Chat-Bubble Thread-Ansicht für Spieler und Admin
- Admin: Ticket-Liste mit 4 Live-Stat-Karten (Offen/Beantwortet/Geschlossen/Gesamt), Kategorie-Spalte
- Fix: fehlende `ti_category_*` Sprachkeys; Kategorie-Anzeige in allen Templates
- Fix: `ShowSupportPage::show()` übergibt `categoryList` ans Template

**Messages System v3.0**
- Komplettes Messaging-UI: Kommunikationszentrale mit Sidebar, aufklappbaren Karten, Compose-Modal
- Externe `messages.css`; alle Inline-Styles entfernt
- Email-Layout: Absender oben, Betreff darunter
- Fix: Expedition-Kampfberichte öffnen als Modal (`data-fancybox`)
- Fix: Karten-Animation via `max-height`/`opacity` statt `display:none`

**Combat Engine**
- v2.0: gewichtsbasierte Schadensverteilung, korrektes Rapid Fire, `mt_rand`, Deep-Snapshots
- v3.0: taktische Formationen, kritische Treffer, Moral-System, Schiffs-Synergien
- Battle Report v3.0: Modal-UI mit SVG-Grafiken, Schadensbalken, Runden-Tabs
- Fix: MIP-Tech-Formel; SQL-Injection im Fleet-Steal behoben

**Galaxy Map**
- 5 Spektral-Sterntypen (B/A/G/K/M) mit Größe, Farbe, Korona und Puls-Animation
- 10 realistische Planeten-Typen (Terran/Wüste/Eis/Lava/Gas/Fels/Ozean/Dschungel/Saturn/Toxisch)
- Schiffs-Silhouetten je Missionstyp mit Richtungsrotation
- Mobile-First: Free-Roaming-Kamera (Pan/Orbit/Pinch-Zoom), 2D-only auf Mobilgeräten
- Fix: Fleet-Dot-Sichtbarkeit (Größe ×4, AdditiveBlending, Y-Lift über Orbitebene)
- Fix: Flottenlinie-Opacity 0.20 → 0.45/0.55

**LiteSpeed / PDO-Stabilität**
- Fix PDO-Fehler 2014 „unbuffered queries" auf LiteSpeed vollständig behoben
  (`closeCursor`, `rowCount`-Guard, Buffered-Query-Attribut, `session_write_close`, DB-Reconnect)

**Plugin-System**
- `PluginManager::getAssetUrl()` für plugin-relative Asset-URLs
- `PluginManager::getConfig()` / `setConfig()` hinzugefügt
- Fix: BOM aus allen Plugin-Dateien entfernt; `.gitattributes` für UTF-8 no-BOM + LF
- Fix: CamelCase Plugin-IDs, `dirForId()` für Verzeichnis-Auflösung
- Fix: `Cronjob::execute()` scannt `plugins/*/cron/` direkt
- Neues Plugin: **LiveFleetTracker** — Echtzeit-Flotten, Interception, NPC-Piraten, Warp-Risiko

**Installation & Migration**
- Fix: UTF-8 BOM aus allen PHP-Dateien entfernt (White Screen / „headers already sent")
- Fix: `migration_14` idempotent (try/catch pro Statement)
- Animierter Installer-Ladebildschirm (Schritt 5)
- Fix: Hostinger-Blank-Page durch explizites Buffer-Flush vor Redirect

---

### Feb 2026 — AJAX-Infrastruktur, Forum, Admin-Panel, 3D-Galaxiekarte

**AJAX-Infrastruktur v2.0**
- `SmAjax`-Objekt: `refreshPageContent()`, `applyResources()`, Ressourcen-Polling alle 10 s
- Bauschleife, Forschung, Werft, Offiziere — alles per AJAX ohne Seitenreload
- Progressive Enhancement: funktioniert weiterhin ohne JavaScript

**3D-Galaxiekarte**
- Three.js 3D-Galaxiekarte mit Orbit/Fly-Navigation
- Flottenlinien: eigen=cyan, Ally=lila, feindlich=rot-gestrichelt
- Planeten-Selektion mit Highlight-Ring; `flyTo()` ohne Snap-Back
- `navJump` (g=1..9, s=1..499) und `navHome`

**Forum**
- Vollständiges Forum mit Kategorien, Threads, Likes, BBCode, Moderation
- Forum-Benachrichtigungen, Authentifizierung, Playercard-Modal
- Fix: Fancybox durch Custom-Modal ersetzt

**Plugin-System**
- Plugin System v1.0 → v1.2 mit `ElementRegistry`
- Fix: `reslist['allow']` String/Int-Key-Korruption
- Fix: `hasNewElements()`-Gate blockierte Pricelist-Export für Plugin-Gebäude
- Fix: `runSqlFile()` bricht nicht mehr bei Per-Statement-Fehlern ab
- Plugins: GalacticEvents, RewardPoolEngine, GalaxyMarkerAPI, CoreQoLPack, Relics & Doctrines

**Modulsystem v2**
- `GameModuleInterface`, `GameContext`, `ModuleManager`
- Core-Module: `ProductionModule`, `QueueModule` (Priorität 10)
- Safe-Mode: Plugin/Modul bei Crash automatisch deaktivieren

**Admin-Panel**
- Futuristisches Aerospace-Design mit Orbitron/Exo 2 und Design-Tokens
- Admin Debug Panel: aktive Plugins/Hooks/Module
- Fix: Mobile-Navigation (Sidebar-Overlay, Backdrop-Filter, Breakpoint 1100px, Z-Index)
- Fix: `ShowAccountEditorPage` POST-Key undefined

**UI / Login**
- SmartMoons v4.0 Redesign — neues CSS-System mit mehreren Dateien
- Login/Registrierung redesigned: Honeypot-Spam-Schutz, Math-CAPTCHA, Rate-Limiting
- Dark/Light-Mode-Toggle mit localStorage-Persistenz
- Element-Tooltip-System (`data-tt-*` Attribute)
- In-Game Changelog-Seite (rendert `CHANGELOG.md` via Parsedown)

---

### v3.x – v1.x — Legacy (2024–2025)

**v3.2.0** — Vollständige Twig-Migration (180 Templates, null Smarty-Syntax verbleibend)  
**v3.1.x** — PHP 8.3/8.4 Vollkompatibilität, `strict_types` in 331 Dateien  
**v3.0.x** — PHP 8.3 Modernisierung, PDO-Migration, externe Bibliotheken aktualisiert  
**v2.0.0** _(Jekill, 2023)_ — PHP 8 Kompatibilität, Smarty 4.3.4  
**v2.0.0** _(Danter14, 2018)_ — Bootstrap 4 Redesign  
**v1.x** _(slaver7)_ — Original 2Moons Codebase

---

## Credits

| Rolle | Person / Projekt |
|---|---|
| Original 2Moons | [slaver7](https://github.com/slaver7/2Moons) |
| Bootstrap 4 Redesign | Danter14 (2018) |
| PHP 8 Kompatibilität | Jekill (2023) |
| CE-Modernisierung (v3.x) | **0wum0** (2025–2026) |

**Technologien:** PHP 8.3 · PDO · Twig 3 · jQuery · MariaDB · Composer

**Lizenz:** GPLv2 — siehe [LICENSE](LICENSE)

---

## Mitarbeiten

Pull Requests sind willkommen. Bitte einen Feature-Branch erstellen:

```bash
git checkout -b feature/mein-feature
git commit -m "feat: kurze Beschreibung"
git push origin feature/mein-feature
```

Issues und Diskussionen über GitHub.

---

<div align="center">

*2Moons CE — Community Edition · Ein Weltraum-Strategiespiel für alle, die es selbst hosten wollen.*

</div>
