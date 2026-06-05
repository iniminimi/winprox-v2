# Betrokkenenrechten — operationeel kader WinProx (België / EU)

**Rechtskader (niet exhaustief):** voor Belgische klanten en gebruikers geldt in de eerste plaats de **Verordening (EU) 2016/679 (AVG/GDPR)** en de **Belgische uitvoeringswet van 30 juli 2018** betreffende de bescherming van natuurlijke personen ten aanzien van de verwerking van persoonsgegevens (en uitvoeringsbesluiten daaronder). Waar van toepassing kunnen ook andere Belgische regels meespelen (onder meer sectoraal).

Dit document beschrijft **hoe het platform ondersteunt**, geen juridisch advies. Verwerkingsverantwoordelijke voor klantdata blijft in de regel de **klant (tenant)**; WinProx treedt typisch op als **verwerker** volgens overeenkomst. WinProx-beheer/support volgt interne procedures.

## Inzage (kopie van gegevens)

1. **Tenant-beheerder**: onderaan **Gebruikers** staat **JSON-export** (machineleesbaar overzicht van het eigen account en gerelateerde gegevens binnen de tenant). Download wordt gelogd in **Activiteit logs**.
2. **Support**: bij een gemotiveerde aanvraag kan alleen na identiteitscontrole en tenantbinding worden geholpen (export uit database/back-office volgens intern proces — buiten deze codebase vast te leggen).

## Rectificatie

- Gebruikers met rechten kunnen **profiel** (naam, e-mail, taal) en—als beheerder—**organisatiegegevens** aanpassen in de app.
- Andere correcties lopen via support met ticketreferentie.

## Verwijdering / beperking

- **Account deactiveren / gebruiker pauzeren**: door tenant-beheerder (login wordt geblokkeerd; sessies worden ingetrokken).
- **Volledige verwijdering** tenant of persoonsrecord: procedure via support en database-actie na machtiging (litigation hold respecteren).

## Bewaartermijnen en logs

- Zie `docs/legal/data-retention-policy.md` en **scheduler**: `retention:prune-activity-logs` (alleen actief als `RETENTION_ACTIVITY_LOGS_ENABLED=true`).
- Activity logs ondersteunen **audit**; na afloop van de termijn worden ze automatisch verwijderd indien retentie aan staat.

## SLA en registratie

- Streefreactie voor rechten van betrokkenen: vastleggen in privacybeleid van de klant; platform default geen automatische SLA.

Laatste aanvulling: engineering-werk aan export, auditlogging gebruikersbeheer en optionele log-retentie.
