# Bot System – Changelog & Feature Overview
> 2MoonsCE / SmartMoons | Branch: `main`

---

## v1.3 – Alliance & ACS Configuration (März 2026)

### Neue Features

**Bot-Allianzen (vollständig konfigurierbar)**
- Bots können automatisch Allianzen gründen oder beitreten
- **Kein `BOT_` Prefix** mehr – Allianz-Namen werden aus einem natürlich klingenden Pool gewählt (`Iron Vanguard`, `Silent Storm`, `Nova Corps` etc.)
- Eigene Namen/Tags über `alliance_name_pool` / `alliance_tag_pool` als JSON-Array in der `bot_setting` Tabelle konfigurierbar
- Bots werden intern via `ally_events` Feld markiert (unsichtbar für Spieler, niemals im UI angezeigt)
- `alliance_max_count = 1` verhindert dass hunderte Bot-Allianzen entstehen (Standard: max. 1 pro Server)
- `alliance_max_members` steuert die maximale Mitgliederzahl pro Bot-Allianz

**Koordinierte ACS-Raids**
- Bots in derselben Allianz können gemeinsam koordinierte Angriffe (Mission 2) starten
- Nur Persönlichkeiten `raider` und `balanced` nehmen an ACS-Raids teil
- Konfigurierbare Parameter: `acs_max_size`, `acs_chance_percent`, `acs_min_loot`

**Neue `bot_setting` Spalten (Migration 16)**

| Spalte | Standard | Beschreibung |
|---|---|---|
| `can_alliance` | 1 | Allianz-Feature an/aus |
| `alliance_max_count` | 1 | Max. Anzahl Bot-Allianzen pro Server |
| `alliance_max_members` | 50 | Slots pro Bot-Allianz |
| `alliance_name_pool` | NULL | JSON-Array eigener Allianz-Namen |
| `alliance_tag_pool` | NULL | JSON-Array eigener Allianz-Tags |
| `alliance_internal_tag` | `bot_managed` | Internes Erkennungsmerkmal (nicht öffentlich) |
| `can_acs` | 1 | Koordinierte ACS-Angriffe an/aus |
| `acs_max_size` | 3 | Max. Bots pro ACS-Gruppe |
| `acs_chance_percent` | 30 | Wahrscheinlichkeit (%) pro Tick |
| `acs_min_loot` | 50000 | Mindest-Ressourcen beim Ziel |
| `can_defense` | 1 | Bots bauen Verteidigungsanlagen an/aus |
| `can_save_fleet` | 1 | Fleet-Save bei eingehendem Angriff an/aus |
| `bot_min_fleet_slots` | 5 | Mindest-Fleet-Slots (kompensiert `computer_tech=0`) |
| `spy_probes` | 3 | Anzahl Sonden pro Spionage-Mission |
| `raid_min_loot` | 10000 | Mindest-Loot für Solo-Raid |

---

## v1.2 – Bug Fixes: Fleet Dispatch & Unsigned Underflow (März 2026)

### Bug Fixes

**`Out of range value for column 'small_ship_cargo'` (und weitere Schiff-Spalten)**
- **Ursache:** `$PLANET`-Array wurde einmal pro Tick aus der DB geladen. Nach dem ersten `sendFleet`-Aufruf subtrahierte die DB die Schiffe korrekt – aber das In-Memory-Array zeigte noch die alten (zu hohen) Werte. Nachfolgende Fleet-Sends im selben Tick versuchten erneut zu subtrahieren → `unsigned underflow` → MySQL-Fehler.
- **Fix:** Neue Hilfsmethode `subtractFleetFromPlanet(array &$PLANET, array $fleetArray)` die nach **jedem** erfolgreichen `sendFleet` das In-Memory-Array sofort aktualisiert. Betrifft alle Fleet-Methoden: `sendExpedition`, `sendSpy`, `sendRaid`, `sendRecycleOnOwnDebris`, `saveFleet`, `doAcsRaid`.
- Alle betroffenen Methoden auf `array &$PLANET` (by-reference) umgestellt.

**`EXPEDITION skip: no fleet slots (1/1)`**
- **Ursache:** `sendExpedition` ignorierte `bot_min_fleet_slots` – nur `doFleetActions` hatte den Override.
- **Fix:** `sendExpedition` nutzt jetzt ebenfalls `max(GetMaxFleetSlots($USER), bot_min_fleet_slots ?? 5)`.

**`SPY sendFleet failed` mit Unsigned-Fehler**
- Gleiche Ursache wie `small_ship_cargo` – durch denselben Fix (`subtractFleetFromPlanet` in `sendSpy`) behoben.

---

## v1.1 – Bot Alliance System (März 2026)

### Neue Features

- **`doAllianceActions()`:** Bots treten bestehenden Bot-Allianzen bei oder gründen neue
- **`doAcsRaid()`:** Koordinierte ACS-Raid-Logik für Allianzmitglieder mit gemeinsamen Zielen
- **`runBot()` Erweiterung:** Alliance-Management und ACS-Raid in den Tick-Flow integriert
- Allianz-Gründung/Beitritt durch `fleet_group`-Mechanismus der bestehenden Fleet-Engine

---

## v1.0 – Bot Engine Grundsystem

### Features

- **BotEngine** (`BotEngine.class.php`): Zentraler Tick-Processor für alle Bots
- **BotActions** (`BotActions.class.php`): Konfigurierbare Settings mit DB-Cache
- **BotPersonality** (`BotPersonality.class.php`): Persönlichkeits-gesteuerte Aktionen

**Persönlichkeiten:**

| Name | Raids | Expeditionen | Fokus |
|---|---|---|---|
| `balanced` | gelegentlich | ja | ausgewogen |
| `farmer` | nein | ja | Ressourcen |
| `raider` | aggressiv | nein | PVP |
| `researcher` | nein | nein | Forschung |
| `miner` | nein | nein | Minen |
| `turtle` | nein | nein | Verteidigung |

**Fleet-Aktionen:**
- Expedition (Mission 15)
- Spionage + Raid (Mission 1) mit Ziel-Selektion nach Ressourcen
- Recycle eigener Trümmerfelder (Mission 8)
- Fleet-Save bei eingehenden Angriffen

**Economy-Aktionen:**
- Gebäude-Queue automatisch befüllen
- Forschungs-Queue automatisch befüllen
- Werften-Queue automatisch befüllen
- Notfall-Energie-Check (Solar/Fusionskraftwerk bevorzugt)

**Cronjob:**
- Fair-Queue Rotation: Bots mit längstem Warte-Zeit kommen zuerst
- Konfigurierbares `bots_per_tick` via `config/bot_config.json`
- Crash-Safety: Bei Exception → Delay + Log, kein Server-Absturz

---

## Dateien

```
includes/classes/bot/
  BotEngine.class.php       – Tick-Processor, Fleet/Economy/Alliance/ACS
  BotActions.class.php      – Settings-Loader mit DB-Cache + Defaults
  BotPersonality.class.php  – Persönlichkeits-Definitionen

includes/classes/cronjob/
  botActionsCronjob.class.php – Fair-Queue Cron-Runner

install/migrations/
  migration_15.sql          – bot_personalities Tabelle + bots.personality Spalte
  migration_16.sql          – bot_setting: Alliance/ACS/Defense/Save-Spalten

includes/dbtables.php       – DB_VERSION_REQUIRED = 16
```
