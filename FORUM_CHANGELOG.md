# Forum-Changelog – Header, Flotten, Bauschleifen & Planeten-Umbenennung

## Was wurde behoben?

### 1) Planeten-Umbenennung funktioniert jetzt zuverlässig
**Problem:**
Die Umbenennung war serverseitig grundsätzlich vorhanden, wurde im Frontend aber nicht in allen Fällen sauber übernommen bzw. sichtbar aktualisiert.

**Ursache:**
- Der Rename-Flow war nicht durchgehend robust (Trigger/Request/Anzeige-Update).
- Das UI-Update nach erfolgreicher Umbenennung war inkonsistent.

**Fix:**
- Rename-Flow stabilisiert (Frontend-Trigger + Rückgabeauswertung).
- Konsistenter sichtbarer Update-Pfad für den neuen Namen eingebaut.
- Fehlerfälle klarer abgefangen, damit kein „stilles Scheitern“ mehr passiert.

---

### 2) Flotten werden im Header wieder korrekt angezeigt
**Problem:**
Trotz aktiver Flüge stand im Header oft weiterhin „Keine Flottenbewegungen“.

**Ursache:**
- Fragile Übergabe/Verarbeitung der Fleet-Daten (JSON/Parsing/Fallback).
- In bestimmten Fällen konnte ein Parse-Problem die Anzeige komplett blockieren.

**Fix:**
- Fleet-Datenübergabe robuster gemacht (sichere JSON-Erzeugung + Fallback-Daten).
- Header-Rendering mit defensivem Fallback ausgestattet.
- Empty-State wird nur noch gezeigt, wenn wirklich keine Flotte aktiv ist.

---

### 3) Benachrichtigungen im Header (Bauen/Forschung/Hangar/Flotten) repariert
**Problem:**
Progressbars waren sichtbar, liefen aber nicht sauber durch; Timer standen teilweise auf `--:--:--`.

**Ursache:**
- Queue-Logik war nicht sauber pro Bereich getrennt.
- Teilweise wurde eine globale DOM-Auswahl genutzt, die nur das erste passende Element traf.
- Timer-/Intervall-Verwaltung war fehleranfällig (Race Conditions / Mehrfach-Start).

**Fix:**
- Queue-Handling je Kategorie getrennt (Gebäude, Forschung, Hangar).
- Timer- und Progressbar-Update pro Queue stabilisiert.
- Intervall-Management bereinigt, damit Updates kontinuierlich und korrekt laufen.

---

### 4) Toast-Benachrichtigungen integriert
**Neu:**
Wichtige Aktionen und Status-Rückmeldungen werden jetzt als Toasts angezeigt.

**Vorteil:**
- Schnellere und klarere Rückmeldung für Spieler.
- Bessere UX bei Aktionen wie Umbenennen, Queue-Aktionen und Header-Interaktionen.

---

## Kurzfazit
Mit diesem Update wurden die zentralen UX-Probleme im Header behoben:
- Planetenname lässt sich wieder zuverlässig umbenennen.
- Flottenbewegungen erscheinen korrekt inkl. laufender Zeit.
- Bauschleifen/Forschung/Hangar zeigen wieder stabile Timer + Progress.
- Benachrichtigungen sind insgesamt robuster und durch Toasts nutzerfreundlicher.
