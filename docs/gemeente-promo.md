# Gemeente-promocampagne — werkwijze

Handleiding voor het genereren van gepersonaliseerde promo-brieven per Vlaamse gemeente, inclusief unieke QR-codes en bezoekstatistieken.

Zie ook `SETUP.md` (sectie *Gemeente-promobrieven*) voor de korte productie-commando's.

---

## Wat het doet

Per gemeente stuur je een **persoonlijke Word-brief** met een **unieke QR-code**. Die QR linkt naar:

`https://winprox.app/promo?ref=prm_…`

WinProx weet zo **welke gemeente** de bezoeker heeft gescand, logt het bezoek, en toont bovenaan de promo-pagina bijvoorbeeld:

*Welkom bezoeker van Aalter, bekijk hier enkele video's.*

---

## De keten in het kort

```text
Excel (adressen)
    → artisan-commando op productieserver
    → promo_recipients (database) + DOCX per gemeente
    → QR op brief → scan → /promo → statistieken in beheer
```

---

## Stap voor stap

### 1. Voorbereiding

| Item | Locatie / opmerking |
|------|---------------------|
| Excel met gemeente-adressen | `storage/app/Vlaanderen_lokale_besturen.xlsx` op de server (**niet in git**) |
| Flow-diagram in brief | `public/images/promo/flow.jpg` (in repo) |
| Code up-to-date | `git pull` op productie na deploy |

### 2. Brieven genereren — altijd op productie

**Waarom productie?** De QR moet naar `APP_URL` (winprox.app) wijzen en `promo_recipients` moet in de **productie-database** komen. Lokaal draaien wordt standaard geweigerd (localhost-QR).

```bash
# Eerst testen (2 gemeenten)
php artisan marketing:generate-municipal-letters storage/app/Vlaanderen_lokale_besturen.xlsx --limit=2 --force --zip

# Volledige run (~300 gemeenten)
php artisan marketing:generate-municipal-letters storage/app/Vlaanderen_lokale_besturen.xlsx --force --zip
```

**Wat het commando per gemeente doet:**

| Actie | Detail |
|-------|--------|
| Leest Excel | Naam, adres, provincie, type, … |
| Zoekt of maakt `promo_recipient` | `label` = gemeentenaam (bv. *Aalter*) |
| Bouwt promo-URL | `https://winprox.app/promo?ref=prm_…` |
| Genereert DOCX | `storage/app/municipal-promo-letters/9880_aalter.docx` |
| Optioneel ZIP | `storage/app/municipal-promo-letters.zip` |

**Bestaande bestemmelingen** met dezelfde `label` worden **hergebruikt** (zelfde QR/token). Alleen nieuwe gemeenten krijgen een nieuw token.

### 3. Brieven ophalen en versturen

1. Download `storage/app/municipal-promo-letters.zip` via SFTP/SCP.
2. Open DOCX lokaal, controleer layout, print of verstuur per post.
3. Output staat **niet in git** — alleen op de server tijdens generatie.

### 4. Wat gebeurt bij een scan?

1. QR opent `/promo?ref=prm_…`
2. `ref` wordt in de sessie bewaard (ook na taalwissel).
3. **Eén bezoek** per scan (dedupe binnen 2 minuten tegen dubbele requests).
4. **Mailscanners** (Safe Links, Proofpoint, …) en bekende HTTP-bots worden **niet** geteld (`PromoVisitScannerDetector`).
5. **Bevestigd bezoek** (JS na 8s of scroll) en **doorklik** (registreren/contact/productpagina) tellen apart van ruwe link-hits.
6. Promo-pagina toont welkomst met gemeentenaam + video's.
7. Afspelen van video's kan apart getrackt worden per bestemmeling.

### 5. Resultaten bekijken

Als superuser: **Platform → Promo-bestemmelingen**

- Per gemeente: aantal bezoeken, tijdstippen, taal.
- Anonieme bezoeken (zonder `ref`) apart vermeld.

---

## Belangrijke bestanden

| Onderdeel | Bestand |
|-----------|---------|
| Artisan-commando | `app/Console/Commands/GenerateMunicipalPromoLettersCommand.php` |
| Hoofdlogica | `app/Actions/Marketing/GenerateMunicipalPromoLettersAction.php` |
| Brief-layout (tekst, QR, flow.jpg) | `app/Support/Marketing/MunicipalPromoLetterDocxBuilder.php` |
| Excel inlezen | `app/Support/Marketing/FlemishMunicipalitiesSpreadsheetReader.php` |
| Adresregels / bestandsnaam | `app/Data/Marketing/MunicipalPromoLetterData.php` |
| Promo-pagina + welkomstkader | `resources/views/promo.blade.php` |
| Bezoek-logging | `app/Http/Controllers/PromoController.php` |
| Mailscanner-filter | `app/Support/Marketing/PromoVisitScannerDetector.php` |
| Dedupe bij scan | `app/Actions/Marketing/RecordPromoVisitAction.php` |
| E-mail verzenden | `app/Console/Commands/SendMunicipalPromoLettersEmailCommand.php` |

---

## E-mail naar gemeentebesturen

Na het genereren van de DOCX-brieven kun je per gemeente een e-mail versturen met **dezelfde brief als bijlage** en een **unieke promo-link** in de mailtekst (`Klik hier` → `/promo?ref=…`).

**Afzender:** `dominique.schaepdrijver@winprox.app` (configureerbaar via `WINPROX_MUNICIPAL_PROMO_EMAIL_FROM`). Replies komen op dat Cloud86-postvak binnen.

Promo-campagnes (en gemeentemails) gaan via **Amazon SES** (`WINPROX_PROMO_MAILER=ses`). Transactionele app-mail (wachtwoordreset, meldingen) blijft Cloud86 SMTP (`MAIL_USERNAME=info@winprox.app`). Fallback naar de oude SMTP-mailer: `WINPROX_PROMO_MAILER=municipal_promo`.

### Amazon SES (productie)

1. IAM-user met `ses:SendEmail` / `ses:SendRawEmail` (regio **eu-west-1**).
2. Domain identity `winprox.app` verifiëren (DNS).
3. SES sandbox verlaten (productie-access aanvragen).
4. Configuration set + SNS-topic → HTTPS `https://winprox.app/api/v1/hooks/ses-promo?token=…` (`WINPROX_SES_SNS_TOKEN`).
5. `.env`: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION=eu-west-1`, `WINPROX_PROMO_MAILER=ses`, `WINPROX_PROMO_EMAIL_MIN_INTERVAL_SECONDS=1`.

Bounces/complaints via SES SNS markeren het adres als onbestelbaar (zelfde actie als de IMAP-bouncescanner). IMAP blijft bruikbaar als extra vangnet.

### Werkwijze (veilig)

```bash
# 1. Audit — hoeveel verzendbaar, wat ontbreekt?
php artisan marketing:send-municipal-promo-emails storage/app/Vlaanderen_lokale_besturen.xlsx --audit

# 2. Dry-run — lijst gemeente, e-mail, DOCX, promo-URL
php artisan marketing:send-municipal-promo-emails storage/app/Vlaanderen_lokale_besturen.xlsx --dry-run --limit=10

# 3. Test naar jezelf (sync, 1 gemeente)
php artisan marketing:send-municipal-promo-emails storage/app/Vlaanderen_lokale_besturen.xlsx \
  --send --sync --confirm --limit=1 --override-to=jouw@adres.be --municipality=Aalter

# 4. Gedoseerde verzending via queue (productie)
php artisan marketing:send-municipal-promo-emails storage/app/Vlaanderen_lokale_besturen.xlsx \
  --send --confirm --delay-seconds=90
```

Zorg dat **`php artisan queue:work`** draait bij queue-verzending (standaard, zonder `--sync`).

Om te **stoppen** (spam / Cloud86): `php artisan marketing:pause-promo-emails` of Platform → Promo-campagnes → **Onderbreek alle verzending**. Zie `docs/deploy-queue-shared-hosting.md`.

Verzonden mails worden gelogd in `municipal_promo_email_sends` (campagne `wave-1` standaard). Reeds verzonden gemeenten worden overgeslagen tenzij `--force`.

| Optie | Gebruik |
|-------|---------|
| `--audit` | Alleen statistieken |
| `--dry-run` | Tabel zonder verzenden |
| `--send --confirm` | Echt versturen |
| `--sync` | Direct versturen (kleine tests) |
| `--override-to=` | Alle mails naar testadres |
| `--delay-seconds=90` | Pauze tussen queue-jobs |
| `--campaign=wave-1` | Campagne-id in log |
| `--force` | Opnieuw versturen |

---

## Promo-campagnes (platform)

Superuser: **Platform → Promo-campagnes**

Herbruikbare campagnes met Quill-editor, Excel-import (ontvangers in DB), optionele DOCX-generatie voor print en e-mailqueue **zonder bijlagen**. Op het **overzicht** en bovenaan de campagnepagina kun je een campagne **verwijderen** (bevestigingsmodal): ontvangers van díé campagne, verzendlogs, wachtende queue-jobs en gegenereerde brieven gaan mee weg. Gedeelde `promo_recipients` (QR) blijven staan.

Tabellen: `promo_campaigns`, `promo_campaign_imports`, `promo_campaign_targets`, `promo_campaign_email_sends`.

**Import:** MX/DNS-checks zitten niet op de server (dat gebeurt lokaal vóór upload). Bij import weigert WinProx alleen ongeldige syntax en adressen die eerder gebounced of uitgeschreven zijn. Die e-mails worden **niet** opgeslagen; de rij (naam/adres) blijft wel staan voor brieven.

De bestaande artisan-flow voor Vlaanderen blijft beschikbaar.


De **infrastructuur blijft hetzelfde**: zelfde `promo_recipients`, zelfde QR per gemeente, doorlopende statistieken.

Typische aanpak:

1. **Nieuwe brieftekst** — nieuwe builder of variant van `MunicipalPromoLetterDocxBuilder` (andere body/CTA).
2. **Zelfde Excel** — adressen uit `Vlaanderen_lokale_besturen.xlsx`.
3. **Nieuw commando of optie** — bv. aparte outputmap `storage/app/municipal-followup-letters/`.
4. **Zelfde QR** — `resolvePromoRecipient` zoekt op `label`; bestaande gemeente behoudt hetzelfde `ref`.

Optioneel later: campagne (brief 1 vs. 2) vastleggen in `note` of een extra veld op `promo_recipients`.

---

## Commando-opties

| Optie | Gebruik |
|-------|---------|
| `--limit=N` | Alleen eerste N gemeenten |
| `--force` | Bestaande DOCX overschrijven |
| `--zip` | ZIP naast losse DOCX-bestanden |
| `--output=…` | Andere outputmap |
| `--promo-base-url=https://…` | Alleen als `APP_URL` afwijkt |
| `--user=id\|email` | Superuser voor audit (default: eerste superuser) |
| `--allow-localhost-promo-url` | Alleen lokaal testen (niet voor echte campagne) |

---

## Onthouden

- **Productie = bron van waarheid** voor QR-URL's en statistieken.
- **Gemeentenaam = `label`** in promo-bestemmelingen (koppeling scan ↔ gemeente).
- **Brieven ≠ code** — DOCX/ZIP downloaden en lokaal verwerken; niet committen.
- **Eén scan = één bezoek** — dubbele EN/NL-entries door meerdere HTTP-requests worden gefilterd.

---

## Server: document lokaal beschikbaar

Na `git pull` staat dit bestand op de server in:

`docs/gemeente-promo.md`
