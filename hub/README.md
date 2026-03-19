# 2MoonsCE Admin Network — Hub Server

Dieser Ordner enthält den **zentralen Hub-Server** für das Admin-Netzwerk.  
Er verbindet alle 2MoonsCE-Instanzen miteinander, sodass Admins weltweit miteinander chatten können.

## Deployment

1. Diesen `hub/`-Ordner auf einen **eigenen Webspace** hochladen (z.B. `https://deine-domain.de/hub/`)
2. `config.php` anpassen:
   - `HUB_MASTER_KEY` auf einen langen, zufälligen String setzen
   - `HUB_DB_PATH` anpassen falls nötig
3. `data/`-Verzeichnis muss durch den Webserver **schreibbar** sein (`chmod 750 data/`)
4. PHP 8.0+ mit `pdo_sqlite`-Extension erforderlich

## Instanz registrieren

```bash
curl -X POST https://deine-domain.de/hub/ \
  -H "Content-Type: application/json" \
  -d '{"action":"register","api_key":"DEIN_MASTER_KEY","instance_name":"Mein Server","instance_url":"https://meinserver.de"}'
```

**Response:**
```json
{"ok":true,"instance_key":"abc123...","message":"Instance registered. Store this key safely."}
```

Den `instance_key` im Plugin unter **Admin → Admin-Netzwerk** eintragen.

## API Endpoints

| Action     | Auth          | Beschreibung                        |
|------------|---------------|-------------------------------------|
| `register` | Master-Key    | Neue Instanz registrieren           |
| `send`     | Instance-Key  | Nachricht senden                    |
| `poll`     | Instance-Key  | Neue Nachrichten abrufen            |
| `ping`     | Instance-Key  | Online-Status aktualisieren         |
| `online`   | Instance-Key  | Online-Instanzen auflisten          |
| `status`   | Keine         | Hub-Statistiken abrufen             |
| `delete`   | Instance-Key  | Eigene Nachricht löschen            |
