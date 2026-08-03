# BIMI — WinProx logo in de inbox

BIMI (Brand Indicators for Message Identification) toont het WinProx-logo naast geauthenticeerde mails in ondersteunde clients (Gmail, Yahoo, Apple Mail, …).

**Besluit (2026-08):** we kopen **geen VMC/CMC** (~€1250/jaar). Zonder dat certificaat toont Gmail (en de meeste grote providers) **geen** logo. BIMI is daarom **niet actief** — geen `default._bimi`-DNS-record zetten.

Het SVG en deze checklist blijven in de repo voor als de kosten ooit dalen of er een gratis/goedkoper pad komt. Tot die tijd: niets doen bij Cloud86.

**Dit is DNS + assets, geen Laravel-code.** Promo-campagnes hebben geen BIMI-toggle nodig.

Zie ook: [gemeente-promo.md](gemeente-promo.md) (e-mailcampagnes).

---

## Waarom VMC/CMC nodig is

| Zonder certificaat (`a=`) | Met VMC of CMC |
|---------------------------|----------------|
| Gmail / Apple Mail: **geen** logo | Logo in inbox |
| Beperkte/self-asserted clients | Breed nut |

VMC = geregistreerd merk. CMC = zonder merk, op bestaand logo-gebruik. Beide zijn betaalde CA-certificaten (DigiCert e.a.).

---

## Wat klaarstaat (repo + DNS) — geparkeerd

| Item | Status |
|------|--------|
| SVG Tiny PS logo | `public/brand/bimi/winprox-logo.svg` → na deploy `https://winprox.app/brand/bimi/winprox-logo.svg` |
| VMC/CMC PEM | **Niet van toepassing** — bewust niet gekocht |
| DMARC | Live: `p=quarantine` op `_dmarc.winprox.app` (`adkim=s; aspf=s`) — al OK voor BIMI |
| SPF | Live: `v=spf1 +a +mx +ip4:45.82.189.243 -all` |
| DKIM | Live: selector `cloud86` → `cloud86._domainkey.winprox.app` |
| BIMI TXT | **Niet zetten** zolang er geen VMC/CMC is |

---

## Als je later wél een certificaat koopt

1. Deploy zodat het SVG bereikbaar is.
2. PEM op `public/brand/bimi/vmc.pem` (of `cmc.pem`).
3. TXT bij Cloud86:

```text
default._bimi.winprox.app
v=BIMI1; l=https://winprox.app/brand/bimi/winprox-logo.svg; a=https://winprox.app/brand/bimi/vmc.pem
```

4. Controleren: `nslookup -type=TXT default._bimi.winprox.app` + [BIMI Inspector](https://bimigroup.org/bimi-generator/).
5. ~48u wachten; testmail naar Gmail.

---

## Wat we bewust niet doen

- Geen VMC/CMC aanschaffen (kosten).
- Geen self-asserted BIMI-DNS zonder certificaat (geen nut voor Gmail).
- Geen BIMI-header in Laravel Mailables.
- Geen per-campagne toggle.
- Geen HTML-logo in de mailbody als “BIMI-vervanger” (dat is geen inbox-avatar).
