# BIMI — WinProx logo in de inbox

BIMI (Brand Indicators for Message Identification) toont het WinProx-logo naast geauthenticeerde mails in ondersteunde clients (Gmail, Yahoo, Apple Mail, …).

**Dit is DNS + assets, geen Laravel-code.** Promo-campagnes en supportmails hebben geen aparte BIMI-toggle: zodra `winprox.app` klaar is, geldt het voor alle From-adressen op dat domein (`info@`, `dominique.schaepdrijver@`, …).

Zie ook: [gemeente-promo.md](gemeente-promo.md) (e-mailcampagnes).

---

## Wat al klaarstaat (repo + DNS)

| Item | Status |
|------|--------|
| SVG Tiny PS logo | `public/brand/bimi/winprox-logo.svg` → `https://winprox.app/brand/bimi/winprox-logo.svg` |
| VMC/CMC PEM | Nog niet — plaats na aankoop op `public/brand/bimi/vmc.pem` |
| DMARC | Live: `p=quarantine` op `_dmarc.winprox.app` (`adkim=s; aspf=s`) — voldoet aan BIMI |
| SPF | Live: `v=spf1 +a +mx +ip4:45.82.189.243 -all` |
| DKIM | Live: selector `cloud86` → `cloud86._domainkey.winprox.app` |
| BIMI TXT | **Nog toe te voegen** bij Cloud86 (zie hieronder) |

---

## Checklist vóór je het DNS-record zet

1. **Deploy** deze branch zodat het SVG op productie bereikbaar is (`https://winprox.app/brand/bimi/winprox-logo.svg` → HTTP 200, `Content-Type: image/svg+xml`).
2. **DMARC** blijft `p=quarantine` of `p=reject` (nu al quarantine). Geen `p=none`. Effectief `pct=100` (default als `pct` ontbreekt).
3. **DKIM** blijft actief via Cloud86-selector `cloud86` voor uitgaande SMTP (`MAIL_HOST=mail.cloud86.com`). Alle From-adressen blijven `@winprox.app` (geen spoofed From).
4. **Certificaat (Gmail / Apple / Yahoo):** koop een **VMC** (geregistreerd merk) of **CMC** (zonder merk, bestaand logo-gebruik) bij DigiCert e.a. Exporteer de PEM-keten en sla op als:
   - `public/brand/bimi/vmc.pem` (of `cmc.pem` — pas de `a=`-URL in DNS aan)
5. Zonder `a=`-certificaat: BIMI-record mag bestaan, maar **Gmail toont geen logo**.

---

## DNS bij Cloud86

Voeg een **TXT**-record toe:

| Veld | Waarde |
|------|--------|
| Host / naam | `default._bimi` (volledig: `default._bimi.winprox.app`) |
| Type | `TXT` |
| Waarde | zie hieronder |

**Met certificaat (aanbevolen voor Gmail):**

```text
v=BIMI1; l=https://winprox.app/brand/bimi/winprox-logo.svg; a=https://winprox.app/brand/bimi/vmc.pem
```

**Zonder certificaat (self-asserted — beperkt nut):**

```text
v=BIMI1; l=https://winprox.app/brand/bimi/winprox-logo.svg; a=
```

TTL: 3600 of provider-default is prima. Propagatie tot ~48 uur.

---

## Controleren

```bash
# BIMI-record
nslookup -type=TXT default._bimi.winprox.app

# DMARC (moet quarantine of reject blijven)
nslookup -type=TXT _dmarc.winprox.app

# DKIM (Cloud86)
nslookup -type=TXT cloud86._domainkey.winprox.app
```

Online:

- [BIMI Group SVG / Inspector](https://bimigroup.org/bimi-generator/)
- Google: [Set up BIMI](https://support.google.com/a/answer/10911320)

Stuur daarna een testmail van `info@winprox.app` of de promo-afzender naar een Gmail-adres en controleer of het logo verschijnt (na DNS + VMC).

---

## Wat we bewust niet doen

- Geen BIMI-header in Laravel Mailables (selector `default` volstaat).
- Geen per-campagne toggle — BIMI is domeinbreed.
- Geen HTML-logo in de mailbody als “BIMI-vervanger” (dat is geen inbox-avatar).
