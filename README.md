# 2Moons CE — Community Edition

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

### CE-2026 — AJAX-Infrastruktur & Forum _(Feb 2026)_

**AJAX-Infrastruktur v2.0** — Alle Formular-Aktionen ohne Seitenreload

- Neues `SmAjax`-Objekt in `scripts/game/ajax.js` als zentraler AJAX-Helper
- `refreshPageContent(url)` ersetzt überall `window.location.reload()` — lädt nur den Seiteninhalt nach und tauscht `.content_page` in-place aus
- `applyResources(data)` aktualisiert die Ressourcenleiste direkt aus AJAX-Antworten
- Ressourcen-Polling alle 10 Sekunden (vorher 60 s)
- Bauschleife, Forschung, Werft, Offiziere — alle Formulare arbeiten jetzt per AJAX, kein Seitenreload mehr
- `AbstractGamePage::sendAjaxSuccess()` gibt bei Aktionen immer aktuelle Ressourcenwerte zurück
- Progressive Enhancement: ohne JavaScript funktioniert alles weiterhin per normalem Form-POST

**Forum** — Vollständige AJAX-Unterstützung

- Antworten erstellen, Thema erstellen, Beitrag bearbeiten, Beitrag löschen, Beitrag melden — alles ohne Reload
- Neue Beiträge werden direkt in die Thread-Ansicht eingefügt, Textarea wird geleert
- Neues Thema navigiert per `history.pushState` zur neuen Seite ohne Hard-Redirect
- Beitrag löschen: `fadeOut` + DOM-Entfernung

**Modulsystem v2** — Erweiterbare Gameplay-Engine

- `GameModuleInterface`, `GameContext`, `ModuleManager` als Hook-basiertes Wrapper-System
- `ProductionModule` und `QueueModule` als Core-Wrapper (Priorität 10)
- Plugins können eigene Module per `manifest.json` registrieren (Priorität 100)
- Kein Performance-Overhead wenn keine Module aktiv sind

---

### CE-2025 — Header, Flotten & UI-Fixes _(Dez 2025)_

- Planeten-Umbenennung zuverlässig stabilisiert (Frontend-Trigger + Rückgabe-Auswertung)
- Flottenbewegungen im Header werden wieder korrekt angezeigt (robuste JSON-Übergabe + Fallback)
- Bauschleifen-/Forschungs-/Hangar-Timer im Header repariert (Race Conditions beseitigt, Queue-Handling nach Kategorie getrennt)
- Toast-Benachrichtigungen für Aktionen und Status-Rückmeldungen integriert
- Galaxiekarte (3D): Flottenlinien (eigen=cyan, Ally=lila, feindlich=rot gestrichelt), Planeten-Selektion mit Highlight-Ring, `flyTo()`-Navigation ohne Snap-Back, Sprung-Hint-Anzeige

---

### v3.3.x — Twig & Datenbankfixes _(Okt 2025)_

**v3.3.4** — Admin-Panel Datenbankverbindung repariert
- `Database_BC.class.php` lädt `config.php` jetzt korrekt über `ROOT_PATH`
- Charset von `utf8` auf `utf8mb4` umgestellt
- "Access denied for user ''@'localhost'" Fehler behoben

**v3.3.3** — Twig-Vorlagen-Bugfixes
- Ungültige Twig-Klammersyntax in mehreren Templates korrigiert
- Fehlende schließende Klammern bei `isModuleAvailable()`-Aufrufen ergänzt
- PHP-Funktionen durch korrekte Twig-Syntax ersetzt (`isset` → `is defined`, `is_numeric` → `matches`)

**v3.3.2** — Doppelte Block-Definitionen entfernt
- Doppelte `{% block script %}`-Definitionen in `page.overview.default.twig` entfernt

**v3.3.1** — Ungültiger `|json`-Filter ersetzt
- Nicht existierender `|json`-Filter durch `|json_encode|raw` ersetzt (12 Vorkommen in 5 Templates)

**v3.3.0 / v3.2.9** — Vollständige Twig-Syntax-Bereinigung
- Alle ungültigen `}}}`, `} }}`, `} %}`-Muster entfernt (38 Vorkommen in 18 Templates)
- Smarty-Überreste (`$var`-Referenzen, `{round(...)}`) durch korrektes Twig ersetzt
- Alle 180 Twig-Templates validiert, null Syntaxfehler verbleibend

**v3.2.7** — Datenbankkonfiguration vereinheitlicht
- Einheitliche Config-Keys überall: `host`, `user`, `password`, `dbname`, `port`
- Alle Legacy-Keys (`dbhost`, `dbuser`, `dbpass`, `userpw`, `databasename`) entfernt
- Modernes DSN-Format mit `utf8mb4` Charset

**v3.2.6** — Ungültiger `|contains`-Filter ersetzt
- `|contains('yes')` durch `'yes' in variable` ersetzt (12 Vorkommen im Installer)

**v3.2.4** — Datenbankkonfiguration vollständig migriert
- Alle Komponenten auf einheitliche Keys umgestellt (Database, SQLDumper, Chat, Installer)

**v3.2.2** — HTML-Ausgabe-Escaping korrigiert
- `|raw`-Filter für system-kontrollierte HTML-Variablen ergänzt (Installerchecks, Fleet-Events, News)

---

### v3.2.0 — Vollständige Twig-Migration _(Okt 2025)_

- 100 % Twig-Syntax-Konformität — alle 180 Templates konvertiert und validiert
- 198+ `{$var}` Smarty-Muster nach `{{ var }}` konvertiert
- 55 `{html_options}` durch Twig `{% for %}`-Schleifen ersetzt
- 31 `smarty.const.*`-Referenzen auf `constant()` umgestellt
- 47+ Loop-Properties (`@iteration`, `@first`, `@last`) nach `loop.*` konvertiert
- Null Smarty-Syntax verbleibend

---

### v3.1.x — PHP 8.3/8.4 Vollkompatibilität _(Okt 2025)_

**v3.1.7–v3.1.4** — Template-Migration (Twig-Engine installiert, alle Install-/Login-/Game-/Admin-Templates migriert)

**v3.1.1–v3.1.0** — PHP 8.3/8.4 Abschluss
- 100 % `declare(strict_types=1)`-Abdeckung (331 PHP-Dateien + 83 Sprachdateien)
- Alle `require` auf `require_once` umgestellt
- Null `mysql_*`-Funktionen, null veraltete Funktionen verbleibend
- Twig 3 als Template-Engine installiert (`composer require twig/twig`)

---

### v3.0.x — PHP 8.3 Modernisierung _(Okt 2025)_

- **v3.0.9** — Alle externen Bibliotheken modernisiert (FTP, tdCron, Facebook SDK, OpenID, PHPMailer, TeamSpeak, Parsedown, Zip)
- **v3.0.8** — `declare(strict_types=1)` in 235 PHP-Dateien ergänzt; alle Kern-Klassen mit strikten Typen versehen
- **v3.0.5–v3.0.7** — Strenge Typisierung für `Database.class.php`, `Config.class.php`, `Session.class.php`
- **v3.0.1–v3.0.4** — Initiale PHP 8.3 Modernisierung (`common.php`, `GeneralFunctions.php`, `constants.php`, Core-Includes)

---

### v2.0.0 — Legacy-Versionen

**v2.0.0** _(2023, by Jekill)_ — PHP 8 Kompatibilität; Smarty 4.3.4 Update; fehlende Logik ergänzt

**v2.0.0** _(2018, by Danter14)_ — Redesign mit Bootstrap 4; Bugfixes für Trümmerfelder & Mondsprengung

**v1.x** _(Original 2Moons by slaver7)_ — Ursprüngliche Codebase

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