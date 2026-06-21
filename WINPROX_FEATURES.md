# WinProx V1 Facility — featureoverzicht

Referentie voor **WinProx V1 Facility** op basis van `c:\winprox_old`. Dit document beschrijft wat de Facility-sector doet, **zonder** Real Estate- en Hospitality-specifieke features.

In V1 heet de sector `facility` in de code. Locaties heten daar nog **Properties** (gebouwen/locaties); assets heten **Units**.

WinProx V2 (`c:\winprox`) mikt op **dezelfde functionaliteit** (Facility-pariteit), maar volledig schoon herschreven volgens `WINPROX_RULES.md`.

---

## Wat Facility expliciet níet heeft

Ten opzichte van Real Estate / Hospitality:

- Geen **eigenaars/owners**-module
- Geen **contractors**-module (geen uitnodigen, offertes, KBO-auto-add)
- Geen **eigenaar informeren** bij meldingen
- Geen Hospitality-leads, hotel-KBO-flows of hospitality-specifieke teams met categorieën

---

## 1. Account & organisatie

- Registratie, login, wachtwoord reset
- Multi-tenant: elke organisatie is een aparte tenant
- Gebruikersbeheer (admin + medewerkers)
- Organisatieprofiel (naam, contact, adres, logo)
- Rollen: admin vs. gewone user
- **Team managers**: een user kan manager zijn van één of meer operationele teams (ziet dan alleen “zijn” teams)
- Taalkeuze: **NL, FR, EN, DE**
- Eerste-login onboarding + **Facility setup-checklist** op dashboard (teams → locatie → assets → workers → QR-pack)

---

## 2. Locaties & assets (Properties / Units)

- **Locaties** (properties): aanmaken, bewerken, actief/inactief, zoeken
- **Assets/units** per locatie: CRUD, actief/inactief, verwijderen
- **Bulk aanmaken** van units (vloeren × kamers, batch-beheer, batch verwijderen)
- **Standaard operationeel team** per unit koppelen
- Per unit een uniek **QR-token**
- **QR-pack download** als Word-bestand (Avery 55×55 stickers) per locatie
- **Gemeenschappelijke ruimtes QR** (property-level QR naast unit-QR’s)
- **Documenten** per locatie/unit (handleidingen, bestanden)
- **Mededelingen/announcements** per locatie/unit (bv. “groot onderhoud”)

---

## 3. Operationele teams & workers

- **Internal teams** (operationele teams): label, sorteer volgorde, actief/inactief
- Team **managers** toewijzen (users die het team mogen beheren)
- **Workers** (uitvoerders **zonder login**): voornaam, achternaam, gekoppeld aan team(s)
- **Worker-icoon** per uitvoerder (visuele identificatie op de werkvloer)
- **Team-QR** (`field_qr_token`) per team → veldportaal
- Team-QR pagina tonen/printen vanuit beheer

---

## 4. Meldingen (Issues)

- Meldingenlijst met **filters** (status, locatie, team, terugkerende meldingen, …)
- Melding aanmaken via **2-staps “easy flow”** (geen aparte uitgebreide modal):
  1. Locatie + unit + omschrijving (+ optioneel **terugkerende melding**)
  2. Taak + toewijzing aan **intern team**
- Meldingdetail: status, omschrijving, foto’s, tijdlijn
- **Meerdere taken** per melding
- **Notities/updates** op melding (met optioneel foto’s)
- Handmatige statuswijziging melding
- **Geen** contractor-toewijzing of eigenaar-notificatie

---

## 5. Taken (Tasks)

- Takenlijst gegroepeerd per **Facility-status**:
  - Toegewezen (`assigned`)
  - In uitvoering (`in_progress`)
  - On hold (`on_hold`)
  - Afgehandeld (`completed`)
  - Niet uitgevoerd (`not_executed`)
- Filters op status + **operationeel team**
- Team managers zien alleen taken van **hun** teams
- Taakdetail: status wijzigen, notities, audit bij on hold/niet uitgevoerd
- Taak toevoegen vanuit meldingdetail (team + geplande datum)
- **Geen** contractor-invites, offertes of `invited`/`quoting`-flow (geblokkeerd voor Facility)

---

## 6. Kalender

- Kalenderweergave van meldingen/taken
- Facility-specifiek: filter op locatie + team
- Integratie met **ochtendbriefing**-data per team

---

## 7. Ochtendbriefing (Morning Briefing)

- **Afdrukbaar overzicht** per operationeel team
- Open taken gegroepeerd (per unit/kamer + algemene taken)
- Filter op datum en team
- Link vanaf dashboard en kalender

---

## 8. Publieke QR-schermen (mobiel-first)

### Unit-QR (`/facility/report/{token}`)

- Publieke meldpagina: omschrijving + **tot 4 foto’s**
- Automatisch **taak aanmaken** naar het default team van de unit
- Secties: home, nieuwe melding, open meldingen, **documenten**, **mededelingen**
- **Worker-modus** op dezelfde pagina (alleen voor herkende veldwerkers):
  - Aanmelden via naam + **icoon-bevestiging**
  - Open taken starten/afhandelen
  - Foto’s + notitie bij afronding
  - Device-cookie onthoudt worker

### Team-QR (`/facility/team/{token}`)

- Veldportaal voor heel team
- Worker **registreren** (naam + icoon) of **heridentificeren**
- Overzicht open taken van het team
- Taak starten / afhandelen met foto’s + notitie
- Taaknotitie toevoegen

---

## 9. Dashboard

- Tellingen: locaties, units, open meldingen, open taken
- Recente meldingen (team-scope voor managers)
- **Setup-checklist** tot alles ingericht is
- **Proefperiode/abonnement-batterij** (resterende dagen)
- Link naar ochtendbriefing

---

## 10. Abonnement & billing

- **Proefperiode** (facility krijgt standaard “pro”-limieten tijdens trial)
- Abonnementsplannen met **unit-limieten**
- Stripe checkout + klantportaal (indien geconfigureerd)
- Grace period na verlopen abonnement
- Waarschuwing bij bijna bereikte unit-limiet

---

## 11. Ondersteuning & juridisch

- **FAQ / kennisbank** (facility-variant: vragen over interne teams i.p.v. contractors)
- **Juridische documenten** (privacy, voorwaarden, DPA, subprocessors) — 4 talen
- **Contactpagina**
- **Hulp-chat** in de app (FAQ-matching + escalatie naar helpdesk bij onbeantwoorde vragen)
- **Data-export** (GDPR) voor ingelogde users

---

## 12. Platform & infra (deels gedeeld)

- Superuser kan tenants/users bekijken en **support view** (impersonatie)
- Activity logging
- E-mailnotificaties (account, taken, … — geen contractor-mails)
- **Data retention** voor facility (oude meldingen/taken/media opruimen via cron)
- Demo-flow op `/demo` voor facility
- Marketingpagina `/facility`

---

## Kernflow

```
QR-scan (unit) → Melding → Taak → intern team → worker op werkvloer → statusupdates → briefing/kalender
```

**Melding → taak(taken) → intern team → worker op werkvloer → statusupdates → briefing/kalender**

---

## V1 → V2 mapping (terminologie)

| V1 (winprox_old) | V2 (winprox) |
|------------------|--------------|
| Property         | Location     |
| Unit             | Unit         |
| InternalTeam     | InternalTeam |
| Worker           | Worker       |
| Issue            | Issue        |
| Task             | Task         |
| sector `facility`| geen sector meer (één app) |

### Belangrijke V2-wijzigingen t.o.v. V1 Facility

- **4 taakstatussen** i.p.v. 7 (`on_hold` / `not_executed` vervallen; rollup naar 4 meldingstatussen)
- **Moderatie** op QR-meldingen (blur tot goedkeuring) — nieuw in V2
- **Actions-architectuur** (business logic uit Livewire)
- Geen contractors, owners of sector-splitsing meer

---

## Bronnen in V1-codebase

| Onderdeel | Pad |
|-----------|-----|
| Sector-capabilities | `config/sectors.php` → `facility` |
| Facility teams UI | `app/Livewire/FacilityTeams.php` |
| Unit QR-portaal | `app/Livewire/FacilityUnitPortal.php` |
| Team QR-portaal | `app/Livewire/FacilityTeamFieldPortal.php` |
| QR-pack export | `app/Http/Controllers/FacilityQrPackDownloadController.php` |
| Taakstatussen Facility | `app/Support/FacilityTaskStatus.php` |
| Ochtendbriefing | `app/Support/FacilityMorningBriefing.php` |
| Setup-checklist | `app/Support/FacilitySetupProgress.php` |
| Team-toegang / scope | `app/Support/FacilityTeamAccess.php` |
| QR-intake (auto-taak) | `app/Support/FacilityQrIntake.php` |
| Worker-sessie | `app/Support/FacilityWorkerSession.php` |
