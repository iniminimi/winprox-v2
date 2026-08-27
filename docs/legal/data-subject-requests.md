# Betrokkenenrechten — operationeel kader WinProx (België / EU)

**Rechtskader (niet exhaustief):** voor Belgische klanten en gebruikers geldt in de eerste plaats de **Verordening (EU) 2016/679 (AVG/GDPR)** en de **Belgische uitvoeringswet van 30 juli 2018** betreffende de bescherming van natuurlijke personen ten aanzien van de verwerking van persoonsgegevens (en uitvoeringsbesluiten daaronder). Waar van toepassing kunnen ook andere Belgische regels meespelen (onder meer sectoraal).

Dit document beschrijft **hoe het platform ondersteunt**, geen juridisch advies. Verwerkingsverantwoordelijke voor klantdata blijft in de regel de **klant (tenant)**; WinProx treedt typisch op als **verwerker** volgens overeenkomst. WinProx-beheer/support volgt interne procedures.

Publieke weerspiegeling: `resources/views/legal/content/*/privacy.blade.php`, `terms.blade.php`, `dpa.blade.php`.

## Inzage (kopie van gegevens)

1. **Tenant-beheerder**: onder **Instellingen → Privacy & export van gegevens** staat een machineleesbare export (JSON in ZIP) van het eigen account en gerelateerde gegevens binnen de tenant. Download wordt gelogd in **Activiteit logs** (`gdpr.data_exported`).
2. **Support**: bij een gemotiveerde aanvraag kan alleen na identiteitscontrole en tenantbinding worden geholpen (export uit database/back-office volgens intern proces — buiten deze codebase vast te leggen).

- **Operationele lijstrapporten** (niet AVG-SAR): op zoekschermen (meldingen, taken, Time, unit checks, unitmetingen, ESG) kunnen medewerkers met toegang tot die lijsten een gefilterde **CSV** of **afdruk** downloaden via **Download rapport** (afdruk volgt waar mogelijk de backoffice-lijstlayout; Time = urenstaat). Dit is geen kopie van alle persoonsgegevens van een betrokkene — dat blijft de account-export hierboven.

## Rectificatie

- Gebruikers met rechten kunnen **profiel** (naam, e-mail, taal) en—als beheerder—**organisatiegegevens** aanpassen in de app.
- Andere correcties lopen via support met ticketreferentie.

## Verwijdering / beperking

- **Account deactiveren / gebruiker pauzeren**: door tenant-beheerder (login wordt geblokkeerd; sessies worden ingetrokken).
- **Volledige tenant-verwijdering (self-service)**: onder **Abonnement → Organisatiegegevens verwijderen** — alleen beheerder, met export-aanbod, wachtwoord en e-mailbevestiging naar alle admins.
  - **Trial**: na e-mailbevestiging kan de beheerder zelf uitvoeren (SQL-snapshot zonder media, daarna hard delete).
  - **Betaald / grace**: cool-down 7 dagen, T−2 herinnering, uitvoering alleen door **WinProx-superuser**; banner in de app; annuleren via Abonnement tot uitvoering.
  - Snapshot zonder media max. **30 dagen**; resultaatmail met tellingen. Zie `docs/FEATURES.md` §7.4.
- **Verlopen proef zonder abonnement** (`expired_trial`): na trial-einde blijft login beperkt tot billing/abonnement. Waarschuwing rond **T+7**, automatische purge rond **T+14** (reminder T−2). Abonnementsactivatie annuleert openstaande `expired_trial`-aanvragen. Zie `config/tenant_purge.php`.
- Overige persoonsrecords / uitzonderingen: procedure via support (litigation hold respecteren).

## Bewaartermijnen en logs

- Zie `docs/legal/data-retention-policy.md` en **scheduler**: `retention:prune-activity-logs` (alleen actief als `RETENTION_ACTIVITY_LOGS_ENABLED=true`).
- Activity logs ondersteunen **audit**; na afloop van de termijn worden ze automatisch verwijderd indien retentie aan staat.
- Tenant-purge snapshots: `winprox:tenant-purge-maintenance`.

## SLA en registratie

- Streefreactie voor rechten van betrokkenen: vastleggen in privacybeleid van de klant; platform default geen automatische SLA.

Laatste aanvulling: onderscheid AVG-account-export vs. operationele lijstrapporten (CSV/afdruk op zoekschermen) vastgelegd (2026-08-27).
