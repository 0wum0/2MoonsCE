# 2MoonsCE – Changelog

All changes by **0wum0** unless otherwise noted.  
Project: [github.com/0wum0/2MoonsCE](https://github.com/0wum0/2MoonsCE)

---

## [Unreleased] – März 2026

### Admin Network Chat Plugin (März 2026)
- Added Plugin: **AdminNetwork** — cross-instance admin chat connecting all 2MoonsCE game servers via a central hub — by 0wum0
- Added standalone hub server (`hub/`) in pure PHP + SQLite (no external dependencies): REST JSON API with actions `register`, `send`, `poll`, `ping`, `online`, `status`, `delete` — by 0wum0
- Hub features: per-IP rate limiting (60 req/min), automatic message pruning (configurable max age + max count), WAL-mode SQLite for concurrent writes — by 0wum0
- Hub authentication: master key for instance registration, per-instance API keys for all other actions — by 0wum0
- Plugin features: futuristic admin chat UI, real-time polling (configurable interval, min. 5s), online-instance sidebar, delete own messages — by 0wum0
- Plugin config: Hub-URL, instance API key, instance display name, poll interval — all editable in Admin → Admin-Netzwerk — by 0wum0
- JS chat client: auto-scroll, Enter-to-send, instant poll after own message, connection status indicator (green pulse / red) — by 0wum0
- Hub data directory protected via `.htaccess` (Deny from all) — by 0wum0

### Build Queue Timers & AJAX-Refresh (März 2026)
- Fixed build queue timers not updating after AJAX build action on all pages (buildings, research, shipyard) — by 0wum0
- Root cause: `$.parseHTML(html, document, false)` in `refreshPageContent()` stripped all `<script>` tags from parsed HTML before insertion, so inline timer scripts were never re-executed after AJAX content swap — by 0wum0
- Fixed `ajax.js` `refreshPageContent()`: inline script bodies now extracted from raw HTML string via regex **before** `$.parseHTML` call, then `eval()`-ed after `replaceWith()` so DOM elements exist — by 0wum0
- Fixed shipyard queue: moved timer script from `{% block script %}` (in `<head>`, not re-executed on AJAX) into `{% block content %}` inside `.content_page` so it is part of the swapped content — by 0wum0
- Fixed shipyard queue: replaced missing `DecimalNumber` (bcmath.js, never loaded) with plain `parseInt` counter — by 0wum0
- Fixed shipyard cancel: `<select name="auftr[]">` was outside `<form>` → selections never submitted; moved inside form — by 0wum0
- Fixed shipyard cancel: `$form.serializeArray().forEach(f => payload[f.name] = f.value)` overwrote multi-select values; replaced with `$form.serialize()` which correctly handles `auftr[]` arrays — by 0wum0
- Fixed buildings queue: cancel/remove forms lacked `data-ajax="1"` → triggered full page reload instead of AJAX refresh; added attribute — by 0wum0
- Fixed buildings queue: progress bar initial value was `width:100%` (hardcoded); changed to `width:0%` and added `data-totaltime="{{ List.time }}"` on track element — by 0wum0
- Fixed buildings queue: progress bar calculation used `resttime` as total duration (wrong); now reads `data-totaltime` for 100% baseline and calculates correct initial fill `(totalTime - restLeft) / totalTime * 100` — by 0wum0
- Added buildings queue timer: auto-refresh via `SmAjax.refreshPageContent()` when active build timer reaches zero — by 0wum0
- Added research queue panel (was entirely missing): queue list with countdown timers, progress bar, and AJAX cancel/remove forms (`data-ajax="1"`) — by 0wum0
- Fixed research queue progress bar: same `data-totaltime` + correct percentage calculation as buildings — by 0wum0
- Added research queue timer: auto-refresh via `SmAjax.refreshPageContent()` on completion — by 0wum0

### Shipyard (März 2026)
- Fixed `shipyard.js`: replaced full `window.location.href` reload on queue completion with `SmAjax.refreshPageContent()` — by 0wum0

### Support-System UI (März 2026)
- Complete support system UI overhaul: futuristic ticket center with SVG icons, status badges, animations, thread view — by 0wum0
- Added `support.css` as dedicated external stylesheet with CSS variables, futuristic design tokens, and responsive layout — by 0wum0
- Rewrote `page.ticket.default.twig`: modern ticket list with per-status row colours, answer count badges, category pills — by 0wum0
- Rewrote `page.ticket.create.twig`: new ticket form with styled `<select>` category dropdown, BBCode toolbar, char counter — by 0wum0
- Rewrote `page.ticket.view.twig`: chat-bubble thread view, reply form with BBCode toolbar, status badge in topbar — by 0wum0
- New ticket creation now opens as a modal popup (`setWindow('popup')` + `data-fancybox`) without the game header — by 0wum0
- Fixed `page.ticket.create.twig` to extend `layout.popup.twig` instead of `layout.full.twig` to prevent header bleeding into modal — by 0wum0
- Added `target="_top"` to ticket create form so post-submit redirect breaks out of the iframe — by 0wum0
- Fixed `|max` Twig filter → `max()` function call in ticket list (Twig 3 compatibility) — by 0wum0
- Fixed category display: `ti_category_error/bug/feature/other` LNG keys were missing from all language files — added to `de/INGAME.php`; added `!empty()` fallbacks in `getCategoryList()` — by 0wum0
- Admin support UI overhaul: futuristic ticket list with 4 live stat cards (open/answered/closed/total), pulsing open-badge, category column — by 0wum0
- Admin ticket view: chat-bubble design matching game side, Admin/User role badges, status badge + back button in topbar, BBCode toolbar — by 0wum0
- Fixed `ShowSupportPage::show()`: `categoryList` was not passed to the template → category column was empty — by 0wum0
- Added category display to admin ticket view topbar subtitle — by 0wum0

### Messages System v3.0 (März 2026)
- Complete messaging UI overhaul: communication center with sidebar, expandable cards, compose modal, themed scrollbar — by 0wum0
- Moved all messages CSS to dedicated `messages.css`; stripped all inline styles from Twig templates — by 0wum0
- Fixed scanline grid artifact appearing in compose modal (removed `position:fixed` from `::before` pseudo-element) — by 0wum0
- Fixed `mm-popup-body` background and padding in modal iframe context — by 0wum0
- Email-style card layout: sender + time on top row, subject below; better readability — by 0wum0
- Fixed expedition combat report links: now open as modal via `data-fancybox` instead of `target="_blank"` — by 0wum0
- Fixed `|max` / `|min` Twig filter → `max()` / `min()` function calls in message pagination — by 0wum0
- Fixed card expand animation: use `max-height` + `opacity` transition instead of `display:none` toggle — by 0wum0

### Combat Engine (März 2026)
- Combat engine v2.0: weight-based damage distribution, correct Rapid Fire application, `mt_rand` for all rolls, deep fleet snapshots — by 0wum0
- Combat engine v3.0: tactical formations, critical hits, morale system, ship synergies, extended round metadata — by 0wum0
- Battle report v3.0: professional modal UI with SVG graphics, damage bars, round tabs, space aesthetic — by 0wum0
- Fixed battle report links: open as modal via `data-fancybox` in battlehall, messages, and destruction reports — by 0wum0
- Fixed popup layout: remove old wrapper chrome, add themed dark scrollbar, clean modal iframe body — by 0wum0
- Fixed MIP tech formula in combat engine — by 0wum0
- Fixed fleet steal SQL injection vulnerability — by 0wum0
- Fixed v3.0 unit destruction formula: was `floor(amount * breachProb * variance)` with avg variance 0.5 → only half expected ships died per round → fights ended as draws → near-zero debris → moon chance always 0; replaced with `floor(hullDamage / hpPerUnit)` with ±10% HP variance for correct lethality — by 0wum0
- Fixed v3.0 formation + synergy multipliers: `form['att'] + syn['att']` was additive (wrong) → now `form['att'] * (1.0 + syn['att'])` (multiplicative) — by 0wum0

### Galaxy Map (März 2026)
- Added 5 spectral star types (B/A/G/K/M) with per-type size, colour, corona spikes and pulse animation — by 0wum0
- Added 10 realistic planet types (terran/desert/ice/lava/gas/rock/ocean/jungle/saturn/toxic) with canvas rendering — by 0wum0
- Added ship silhouettes per mission type (fighter/transport/colony/spy/recycler) with direction rotation — by 0wum0
- Mobile-first UI redesign with free-roaming camera (pan/orbit/pinch-zoom) — by 0wum0
- HUD overhaul: 3D/2D toggle in HUD, FAB above dock, desktop panel fixes — by 0wum0
- Added round star particles, coloured nebula clouds, 2-finger tilt, prevented horizontal scroll — by 0wum0
- Fixed fleet dot visibility: increased size 3–7 → 12–20 units × 4, AdditiveBlending for glow, Y-lift above orbit plane — by 0wum0
- Fixed fleet line opacity: path opacity 0.20 → 0.45/0.55, trail opacity corrected — by 0wum0
- Fixed sun scale: `baseSunScale=28` stored on creation; animate loop uses stored value instead of hardcoded `12` — by 0wum0
- Mobile 2D-only mode: hides `#vb-3d` on `max-width:768px`, sets `orb.r=4000` with `syncCamera()` on init — by 0wum0
- Fixed galaxy map JSON APIs: `while(ob_get_level())` clears all output buffers before JSON output — by 0wum0
- Added window focus event to re-fetch fleets; reduced no-fleet polling interval 15 s → 3 s — by 0wum0
- Fixed galaxy map blank on PC: `2moonsce.base.css` sets `position:relative; z-index:1; animation:pageIn(transform)` on `#page-content` and `.content-wrapper`, creating stacking contexts that trapped `position:fixed` on `#gm-root`; overridden with `z-index:auto; animation:none; isolation:auto` via inline `<style>` as first child of `#page-content` — by 0wum0
- Redesigned side panel: moved from left-floating panel to fixed collapsible right rail with slide toggle tab (chevron), localStorage-persisted open/collapsed state; mobile still uses bottom-sheet FAB — by 0wum0
- Fixed WebGL renderer: was skipped when `is3D=false` (from saved 2D view preference), leaving canvas hidden permanently; renderer is now always created — `is3D` only controls camera angle — by 0wum0
- Suppressed `THREE.WebGLRenderer: Error creating WebGL context` console error on both galaxy map and overview starfield by temporarily overriding `console.error` around the constructor — by 0wum0

### LiteSpeed / PDO Stability (März 2026)
- Fixed PDO error 2014 "Cannot execute queries while other unbuffered queries are active" on LiteSpeed — by 0wum0
  - `Database::closeCursor()` after `fetchAll()` in `select()` — by 0wum0
  - `rowCount()` guard: only called for non-SELECT statements — by 0wum0
  - `setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true)` after connect — by 0wum0
  - `session_write_close()` in constructor for JSON API modes — by 0wum0
  - `Session::save()` idempotent flag + DB disconnect after save — by 0wum0
  - `Database::reconnect()` + `Session::save()` reconnects before queries — by 0wum0
  - `reconnect()` uses `SELECT 1` flush instead of `new self()` to avoid `config.php` path issues — by 0wum0

### Plugin System (März 2026)
- Added `PluginManager::getAssetUrl(pluginId, relativePath)` for plugin-relative web asset URLs — by 0wum0
- Added `PluginManager::registerBuildingImage()` API replacing manual `@copy` hack — by 0wum0
- Fixed `PluginManager::lang()`: on-demand loads language file if cache is missing — by 0wum0
- Fixed `PluginManager::getConfig()` / `setConfig()`: were missing, caused fatal error in GalacticEvents — by 0wum0
- Fixed duplicate `getConfig()`/`setConfig()` methods after merge — by 0wum0
- Fixed GalacticEvents plugin ID mismatch (`galactic_events` → `GalacticEvents`) — by 0wum0
- Fixed `GalacticEventsModule` Twig namespace (`@galactic_events` → `@GalacticEvents`) — by 0wum0
- Fixed `GalaxyMarkerDb` / `RewardPoolDb`: replaced non-existent `query/fetch_array/sql_escape` calls with correct `select/selectSingle/insert/delete` API — by 0wum0
- Fixed `RelicsTick.php` parse error (stray quote in comment) — by 0wum0
- Fixed `RelicsPage` lang loading: reads JSON directly via `dirForId()` instead of relying on PluginManager cache — by 0wum0
- Fixed `Cronjob::execute()`: wraps `require_once` + `run()` in single `try/catch` so lock is always released on error — by 0wum0
- Fixed plugin cronjobs: `Cronjob::execute()` scans `plugins/*/cron/` directly, no longer depends on `loadActivePlugins()` for path resolution — by 0wum0
- Fixed CronjobTask interface: load via `__DIR__` relative path before requiring plugin cronjob file — by 0wum0
- Fixed `PluginManager::registerCronjob()`: auto-inserts DB row if missing (idempotent) — by 0wum0
- Fixed `activate()`: bootstraps `plugin.php` so `registerCronjob()` creates DB row; `deactivate()` disables only that plugin's cronjobs — by 0wum0
- Fixed CamelCase plugin IDs: `dirForId()` resolves directory by manifest scan regardless of folder name casing — by 0wum0
- Fixed plugin ID sanitizer: preserve CamelCase in admin page (removed `strtolower`) — by 0wum0
- Added `Database::isConnected()`: check PDO handle validity without relying on `get() !== null` — by 0wum0
- Fixed `ShowOverviewPage` / `GalacticEventsModule`: `null`-check `Database::get()` before DB queries — by 0wum0
- Fixed `GE_CFG_*` constants: added `defined()` guards to prevent redefinition error — by 0wum0
- Removed BOM from all plugin files; added `.gitattributes` to enforce UTF-8 no-BOM + LF line endings — by 0wum0
- Added Plugin: **LiveFleetTracker** — real-time fleet tracking, interception, NPC pirates, warp risk — by 0wum0
  - Full combat via `CombatFramework`, resource transfer, fleet losses on intercept — by 0wum0
  - Fixed `LiveFleetCronjob`: delete `FLEETS`+`FLEETS_EVENT` together, update `LOG_FLEETS`, fix fleet array format — by 0wum0

### Installation & Migration (März 2026)
- Fixed `runSqlFile()`: per-statement `try/catch` so `ALTER TABLE ADD COLUMN` errors on re-install are non-fatal — by 0wum0
- Fixed `migration_14`: removed `PREPARE` blocks, use simple `ALTER TABLE` (try/catch handles duplicates) — by 0wum0
- Added all forum tables + `lockTime` column to `migration_14` for catch-up on existing installs — by 0wum0
- Fixed graceful error when forum tables are missing: show upgrade link instead of crash — by 0wum0
- Fixed `allow upgrade/doupgrade` without `ENABLE_INSTALL_TOOL` on existing installs — by 0wum0
- Added animated Windows-installer-style loader screen (step 5) — by 0wum0
- Fixed UTF-8 BOM: removed from all PHP files including `common.php` (caused "headers already sent" / white screen) — by 0wum0
- Added `.editorconfig` to prevent UTF-8 BOM in future commits — by 0wum0
- Fixed `config.sample.php`: added `%d` placeholder for port in `sprintf` mapping — by 0wum0
- Fixed `ob_start` in `index.php`: add explicit 302 + flush buffers before redirect to fix blank page on Hostinger — by 0wum0
- Added `vendor/` to repo for correct dependency bundling on fresh install — by 0wum0

### Admin Panel – Bugfixes (März 2026)
- Fixed `ShowAccountEditorPage`: "Undefined array key 'delete'" on line 329 — replaced direct `$_POST['add']`/`$_POST['delete']` access with `!empty()` checks in buildings section — by 0wum0
- Fixed `ShowAccountEditorPage`: all `$template->show('*.tpl')` calls replaced with `.twig` — white page after saving buildings/ships/defenses/research/personal/officers/planets/alliances — by 0wum0
- Fixed `ShowAccountEditorPage`: duplicate `break;` after defenses case removed — by 0wum0
- Fixed all remaining `.tpl` → `.twig` template calls across 55 files in `includes/pages/adm/`, `includes/pages/game/`, and `includes/pages/login/` — white/blank page on any page that had not yet been individually migrated — by 0wum0
- Fixed `AccountEditorPageBuilds.twig` and `AccountEditorPageDefenses.twig`: `<button>` submit buttons had no `value` attribute → `$_POST['add']`/`$_POST['delete']` were empty strings → `!empty()` check failed → neither add nor delete branch ran → `$after` undefined → fatal error on `$LOG->new = $after` — added `value="1"` to both buttons in both templates — by 0wum0
- Fixed `ShowAccountEditorPage` buildings POST handler: `!isset($PlanetData)` never true when `getFirstRow()` returns `false` → replaced with `empty()` check + added missing `exit` so code does not fall through to `foreach()` on invalid planet ID — by 0wum0
- Fixed `ShowAccountEditorPage` buildings add branch: `$QryUpdate` was used before initialisation inside the `add` block — added `$QryUpdate = []` before the loop — by 0wum0
- Fixed `PluginManager::selectSingle()`: "Trying to access array offset on false" when `$res` is `false` and a `$field` is specified — added `is_array()` guard — by 0wum0
- Fixed Admin mobile navigation: `sidebar-overlay` div lacked CSS definition and was rendered `display:block` by external CSS override — added `style="display:none"` inline on element; JS now controls `display` directly via `overlay.style.display` instead of CSS class toggling — by 0wum0
- Fixed Admin mobile sidebar: `backdrop-filter:blur()` on `position:fixed` `.admin-sidebar` and `.admin-topbar` created stacking contexts that blocked pointer events on content behind them — removed `backdrop-filter` from both elements — by 0wum0
- Fixed Admin mobile breakpoint: raised from `768px` to `1100px` so sidebar hides correctly on tablets and larger mobile devices — by 0wum0
- Fixed Admin sidebar z-index: raised sidebar to `z-index:1001` (overlay `1000`) so nav links are clickable when sidebar is open — by 0wum0
- Fixed Dashboard Bot-Aktivität box: `white-space:nowrap` without `flex-wrap` caused layout to break mobile view — replaced with `flex-wrap:wrap`, `word-break:break-word`, `overflow-wrap:break-word` — by 0wum0
- Added cronjob reset-all action with admin UI confirmation dialog — by 0wum0
- Fixed `registerCronjob` selectSingle false-return crash — by 0wum0

### Overview Page (März 2026)
- Show all planets including current in overview; always show referral link card — by 0wum0
- Added GalacticEvents block to overview — by 0wum0
- Added inline planet rename: click-to-edit UI with live title/selector updates via AJAX — by 0wum0
- Fixed planet rename endpoint: clears output buffers, returns explicit JSON headers — by 0wum0
- Added universe filter and duplicate parameter fix to `FlyingFleetsTable` query — by 0wum0
- Fixed online player count: `$db->rowCount()` unreliable after SELECT on PDO → replaced with `count($onlineUserResult)` — by 0wum0

### Localisation (März 2026)
- Fixed `modul_43` (Bots) and `modul_44` (Forum) names not visible in Admin module list — added missing language keys to `language/de/ADMIN.php` — by 0wum0

### Tech Tree (März 2026)
- Fixed tech tree page blank/error for all categories: `ShowTechtreePage` called `display('page.techTree.default.tpl')` (Smarty) instead of `.twig` — by 0wum0

### Defensive Programming & Stability
- Added `Database::selectSingleSafe()` — returns `null` instead of `false`, no breaking change — by 0wum0
- Fixed `ShowPlayerCardPage`: guard against null result for invalid player ID — by 0wum0
- Fixed `ShowRaportPage`: moved null check before array access to prevent fatal error — by 0wum0
- Fixed `ShowBuddyListPage`: guard against null userData for invalid friend ID — by 0wum0
- Fixed `ShowChangelogPage`: guard against missing file before `file_get_contents()` — by 0wum0
- Added `class_exists()` guard in `game.php` and `index.php` routers after `require_once` — by 0wum0
- Added sidebar collapse toggle with `localStorage` persistence — by 0wum0
- Added Galaxy Map + 2D/3D toggle to sidebar navigation — by 0wum0

### Login / Registration
- Added math CAPTCHA `ensureSession()` — calls `Session::init()` before `session_start()` — by 0wum0
- Fixed `Session::init()`: skip `ini_set()` calls if session is already active — by 0wum0
- Fixed HTML escaping in login template: applied `|raw` filter to `loginInfo` and `descText` — by 0wum0
- Added "Remember me" checkbox to login form with language key `loginRemember` — by 0wum0
- Redesigned login and registration forms with improved readability, contrast, and typography — by 0wum0
- Added honeypot anti-spam field to registration form (CSS-hidden, `tabindex="-1"`) — by 0wum0
- Added math CAPTCHA to registration form (server-side, session-based) — by 0wum0
- Added registration rate limiting per IP (max 3/hour) via `RegistrationRateLimit` — by 0wum0
- Scoped `main.css` body/input rules with `:not(.auth-body)` to prevent conflicts with `auth.css` — by 0wum0
- Fixed registration rules checkbox: `{{ registerRulesDesc }}` was Twig-escaped → `<a>` tag rendered as literal text — added `|raw` filter — by 0wum0
- Lightened login page colours: `--c-bg` `#04050d` → `#0d1120`, surfaces/borders/text raised for better readability on devices that render too dark (`auth.css`) — by 0wum0
- Lightened ingame dark theme: `--bg0`–`--bg3` raised from near-black to dark navy, border opacities and text brightness increased (`2moonsce.base.css`) — by 0wum0

### Changelog Page
- Added in-game changelog page (`game.php?page=changelog`) — renders `CHANGELOG.md` via Parsedown — by 0wum0
- Added changelog link in game footer next to game name — by 0wum0
- Added `menu_changelog` language key to all languages (de/en/es/fr) — by 0wum0

---

## [v4.1] – 2025 – 2026

### Admin Panel
- Migrated admin dashboard CSS to aerospace space theme with Orbitron/Exo 2 fonts and unified design tokens — by 0wum0
- Added legacy-content wrapper and aerospace theme styling to admin forms, tables, and tab navigation — by 0wum0
- Added Admin Debug Panel: active plugins/hooks/modules — by 0wum0
- Added quick-edit popup function to admin header — by 0wum0
- Fixed admin template syntax errors and improved Twig attribute access — by 0wum0
- Fixed `ShowAccountDataPage` and `ShowAlliancePage` — by 0wum0
- Fixed `ShowRightsPage`: removed redundant session validation, added null-coalescing guards — by 0wum0
- Fixed `ShowResetPage`: null-coalescing operator on `sid` parameter — by 0wum0

### Chat System (SmartChat)
- Replaced iframe-based chat with comprehensive SmartChat: BBCode support, admin moderation, ban management, real-time polling — by 0wum0
- Moved chat FAB and panel from bottom-left to bottom-right — by 0wum0
- Added UTF-8 encoding parameter to message input handling — by 0wum0
- Only show toast notifications for messages from other users; limit to one toast at a time — by 0wum0
- Added localStorage persistence for chat message tracking — by 0wum0
- Added close button to toast notifications — by 0wum0
- Bumped DB version to 12; added `IF NOT EXISTS` to `ALTER TABLE` statements for idempotent migrations — by 0wum0
- Fixed PHP 8.3 `version_compare` type error in chat entity decode — by 0wum0

### Galaxy Map (3D)
- Implemented 3D Galaxy Map with Three.js — by 0wum0
- Fixed flyTo snap-back: orbit target syncs after landing, recalculates theta/phi — by 0wum0
- Fixed planet selection ring (RingGeometry, dispose on rebuild) — by 0wum0
- Fixed fleet lines: own=cyan, ally=purple, hostile=dashed-red (blinking), foreign=dashed-gray — by 0wum0
- Fixed JSON parse errors with console logging of first 200 chars — by 0wum0
- Fixed loader: 8s failsafe `setTimeout` always hides loader regardless of fetch outcome — by 0wum0
- Added navJump (clamp g=1..9, s=1..499) and navHome with feedback hints — by 0wum0

### Overview Page
- Complete overview page redesign: three-column layout, news carousel, queue cards — by 0wum0
- Added news carousel with BBCode editor and navigation controls — by 0wum0
- Added cinematic hero section for planet view: parallax effects, star field canvas, interactive hover — by 0wum0
- Moved colonies and debris cards to left column; relocated quick action buttons below planet scene — by 0wum0
- Moved server info to right overlay panel; replaced with quick actions column — by 0wum0

### Fleet & Header
- Fixed fleet-movement and navigation headers (multiple iterations) — by 0wum0
- Re-init header queue timers after AJAX page refresh — by 0wum0
- Fixed timer data attributes to use `resttime` instead of `endtime` — by 0wum0
- Added header notification badge auto-refresh on AJAX content updates — by 0wum0
- Redesigned resource bar with full-width image backgrounds and mobile responsiveness — by 0wum0
- Fixed fleet line positioning with sun fallback and deferred planet resolution — by 0wum0
- Added mobile-friendly ship selection cards with stepper controls — by 0wum0

### Forum
- Added in-game forum with categories, posts, BBCode — by 0wum0
- Added forum notifications and authentication — by 0wum0
- Added playercard modal to forum pages — by 0wum0
- Fixed forum admin: categories, page layout, BBCode toolbar — by 0wum0
- Added BBCode support to alliance pages — by 0wum0
- Fixed modal system: replaced Fancybox with custom modal — by 0wum0

### Plugin System
- Implemented Plugin System v1.0 — by 0wum0
- Upgraded Plugin System v1.1: refactored core, fixed language loading — by 0wum0
- Upgraded Plugin System v1.2: `ElementRegistry` + double-include guards — by 0wum0
  - Fixed `reslist['allow']` string vs int key corruption — by 0wum0
  - Fixed `exportLegacyPricelist()` to be merge-additive — by 0wum0
  - Fixed `hasNewElements()` gate blocking pricelist export for plugin buildings — by 0wum0
  - Fixed `runSqlFile()` to continue past per-statement errors — by 0wum0
- Added Plugin: **GalacticEvents** — by 0wum0
- Added Plugin: **RewardPoolEngine** — by 0wum0
- Added Plugin: **GalaxyMarkerAPI** — by 0wum0
- Added Plugin: **CoreQoLPack** — by 0wum0
- Added Plugin: **Relics & Doctrines** — by 0wum0
- Added Safe-Mode: auto-deactivate plugin/module on crash — by 0wum0

### Module System
- Implemented Full Modular Gameplay Engine v2 — by 0wum0
  - `GameModuleInterface`, `GameContext`, `ModuleManager` — by 0wum0
  - Core modules: `ProductionModule`, `QueueModule` — by 0wum0
  - Plugins register modules via manifest `"modules"` key — by 0wum0

### PHP 8.3 Compatibility & Bug Fixes
- Fixed `MissionCaseAttack`: array strictness for loot/debris field (PHP 8.3) — by 0wum0
- Fixed `MissionCaseAttack`: uninitialized string offset 901 — by 0wum0
- Fixed various `array offset on false` warnings across game pages — by 0wum0
- Fixed null-coalescing operators on `action`, `get`, `sid` parameters throughout admin — by 0wum0
- Fixed `Rights` and `UserList` unserialization empty-checks — by 0wum0
- Fixed timezone selector: flattened array for dropdown compatibility — by 0wum0
- Fixed `shortly_number()`: added string type support and explicit float casting — by 0wum0

### UI / CSS
- Complete SmartMoons v4.0 redesign — by 0wum0
- Split CSS into multiple files, fixed CSS errors — by 0wum0
- Integrated notification styles into `smartmoons.css`; removed `smartmoons-fix.css` — by 0wum0
- Fixed sidebar on mobile: teleport to body — by 0wum0
- Fixed sidebar overlay and positioning — by 0wum0
- Fixed `.no-js { display:none }` white-page bug — by 0wum0
- Reduced header element sizes; hide logo on mobile — by 0wum0
- Added responsive message view — by 0wum0
- Added compact number formatting — by 0wum0

### AJAX & Cronjobs
- Added AJAX no-page-reload for build/research/fleet actions — by 0wum0
- Fixed cronjobs after file edits — by 0wum0
- Fixed statistic cronjob logging — by 0wum0
- Fixed officer timer and buy button — by 0wum0

### Misc
- Added user online count and last registered player to topbar — by 0wum0
- Fixed notification sync to DB — by 0wum0
- Fixed FAQ in `PluginAdminPage` — by 0wum0
- Redesigned login page (multiple iterations) — by 0wum0
- Fixed disclaimer page — by 0wum0
- Fixed alliance: add member, missing tech-tree link — by 0wum0
- Fixed shipyard build error — by 0wum0
- Fixed `BuildFunctions` — by 0wum0
- Fixed `BBCode` in alliance and forum — by 0wum0

---

## [Initial] – 2024

- Initial project setup based on 2Moons / SmartMoons — by 0wum0
- Big initial update: PHP 8.3 compatibility pass, PDO migration, strict types — by 0wum0
