# Handleiding-screenshots — runbook

Statische PNG's voor de WinProx-handleiding (`/manual/*`). Geen runtime-feature: assets in git,
ingebouwd via `ManualScreenshotAssets` + `data-manual-capture` selectors.

**Voor agents:** lees dit vóór je capture aanpast of opnieuw draait.

---

## Golden path (aanbevolen)

**Lokaal op Windows** tegen een draaiende WinProx (Apache + MySQL, geïmporteerde tenant-data).
**Niet** vertrouwen op capture op Plesk shared hosting (Chromium/thread-limieten).

### 1. `.env` (lokaal)

```env
APP_URL=http://localhost
MANUAL_CAPTURE_BASE_URL=http://localhost
MANUAL_CAPTURE_EMAIL=demo_user@winprox.app
MANUAL_CAPTURE_PASSWORD=...
MANUAL_CAPTURE_LOCATION_ID=...
MANUAL_CAPTURE_ISSUE_ID=...
MANUAL_CAPTURE_TASK_ID=...
MANUAL_CAPTURE_UNIT_QR_TOKEN=...   # units.qr_token
MANUAL_CAPTURE_TEAM_QR_TOKEN=...   # internal_teams.field_qr_token — team mét workers+iconen
MANUAL_CAPTURE_WORKER_FIRST_NAME=John
MANUAL_CAPTURE_WORKER_LAST_NAME=Workman
MANUAL_CAPTURE_WORKER_ICON=star    # field_icon_slug van die worker
```

- `APP_URL` = `MANUAL_CAPTURE_BASE_URL` = exact de URL waarmee je in de browser opent (geen
  `/winprox/public` als Apache al naar `public/` wijst).
- Team-QR: kies een team **met actieve workers die een icoon hebben** — anders zie je
  registratie i.p.v. identificatie (`portal-team-identify` timeout).
- Worker-icoon moet overeenkomen met `workers.field_icon_slug` (niet raden).

**Teamleader-shots (optioneel maar aanbevolen):**

- `MANUAL_CAPTURE_TEAM_QR_TOKEN` → team van John Workman (team **met** workers + icoon).
- `MANUAL_CAPTURE_WORKER_ICON=star` (of het echte icoon van die worker).
- **`portal-teamleader-release`:** het team moet een **tweede worker** hebben met
  `field_icon_locked_at` gezet (geblokkeerd icoon). Anders wordt die ene target overgeslagen.

  Eenmalig in tinker (pas team/worker-id aan):

  ```php
  $teamId = 2; // zelfde team als MANUAL_CAPTURE_TEAM_QR_TOKEN
  $colleague = \App\Models\Worker::factory()->create([
      'tenant_id' => 1,
      'internal_team_id' => $teamId,
      'first_name' => 'Capture',
      'last_name' => 'Blocked',
      'field_icon_slug' => 'heart',
      'field_icon_locked_at' => now(),
      'is_active' => true,
  ]);
  ```

### 2. Script draaien

```powershell
cd C:\winprox
php artisan config:clear
.\scripts\capture-manual-local.ps1
```

Het script doet **altijd** alles:

1. Verwijdert bestaande `*.png` in `public/images/manual/{nl,en,fr,de}/`
2. Installeert/controleert Playwright Chromium
3. Draait `scripts/capture-manual-screenshots.mjs`
4. `git commit` + `git push` (alleen manual-mappen)

Bij mislukte capture: geen commit/push.

### 3. Output controleren

- Pad: `public/images/manual/{nl,en,fr,de}/*.png`
- ~20 bestanden per taal (80 totaal)
- Internetportaal-PNG's zijn smal (~390px); handleiding toont ze op ware grootte
  (`wp-manual-screenshot--portal` in `manual.css`)

### 4. Deploy

Het script pusht zelf naar GitHub. Op productie: `git pull`. Geen Chromium op de server nodig.

---

## Platform-UI (superuser)

`/platform/screenshots` — knop “Handleiding-screenshots bijwerken” queue't
`CaptureManualScreenshotsJob`. Werkt alleen als `.env` capture-vars + Node/Chromium op die machine
kloppen. Op huidige shared hosting: **niet gebruiken**; golden path hierboven.

---

## Architectuur (bestanden)

| Onderdeel | Pad |
|-----------|-----|
| Playwright-script | `scripts/capture-manual-screenshots.mjs` |
| Windows wrapper | `scripts/capture-manual-local.ps1` |
| Targets (URL, selector, viewport) | `scripts/manual-capture.config.json` |
| Config / .env keys | `config/manual_capture.php` |
| Actions | `app/Actions/Manual/*` |
| Job / CLI | `CaptureManualScreenshotsJob`, `winprox:manual-capture-screenshots` |
| Embed in handleiding | `ManualScreenshotAssets`, `manual-chapter.blade.php` |
| Selectors op schermen | `data-manual-capture="..."` in Blade |

Taal tijdens capture:

- **Beheer:** `/locale/{locale}` zet sessie vóór elk taal-blok
- **QR-portaal:** `?lang={locale}` op de URL

---

## Nieuw scherm toevoegen

1. `data-manual-capture="jouw-sleutel"` op het te fotograferen element in Blade.
2. Target in `scripts/manual-capture.config.json` (id, path, selector, viewport).
3. Hoofdstuksleutel in handleiding → bestandsnaam via `ManualScreenshotAssets::filenameForChapter`
   (`issues.list` → `issues-list.png`).
4. Capture opnieuw draaien; PNG's committen.

Viewport-richtlijn:

- Beheer: `1280×800`
- Internetportaal (gsm): `390×844` — element-screenshot blijft smal in de handleiding

---

## Veelvoorkomende fouten

| Symptoom | Oorzaak / fix |
|----------|----------------|
| Geen `#email` op login | Verkeerde `MANUAL_CAPTURE_BASE_URL` (404) |
| Alles Nederlands in en/fr/de | Oude scriptversie zonder `/locale/` + `?lang=` — pull + opnieuw |
| `portal-team-identify` timeout | Verkeerd team-token (leeg team) of device-cookie — script reset portaal-cookies |
| Teamleader skips | Worker naam/icoon/team-token kloppen niet — of Livewire sign-in (update script) |
| `locations-gps-history` skip | Geen unit met GPS op de capture-locatie — minstens één `unit_gps_reports`-rij nodig |
| Server `pthread_create` | Shared hosting — capture lokaal |
| “Klaar” maar 0 PNG's | PowerShell toont nu een error; check script-output |

---

## Server (.env) — optioneel / meestal leeg

Op Plesk shared hosting **geen** `MANUAL_CAPTURE_*` of `storage/playwright-browsers` onderhouden.
Alleen PNG's via git.

Zie ook `.env.example` (comment-block `MANUAL_CAPTURE_*`).
