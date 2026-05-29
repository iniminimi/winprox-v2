# WinProx — Functionele specificatie (Facility-pariteit)

Levende specificatie, opgebouwd door het menu **top‑down** te doorlopen met de gebruiker.
Doel: dezelfde functionaliteit als de oude WinProx Facility, **schoon herschreven**, in de
minimale `standard`-stijl (kleuren NIET uit de oude app overnemen — zie `WINPROX_RULES.md`).

Per scherm: doel · weergave · acties · data · rollen · device · bijzonderheden.

> **Moderatie/blur (hard):** Het bluren van foto's én tekst geldt **uitsluitend op de publieke
> QR-pagina's** (na scan), om compromitterende inhoud vóór goedkeuring te verbergen. De
> **beheerschermen** (dashboard, meldingen, taken, …) zijn alleen voor **beheerders en
> medewerkers** en tonen alles **onverkort** — zij moeten de inhoud juist kunnen beoordelen en
> goedkeuren. Dus: **geen blur op desktop/beheer**.

Menu-volgorde (sidebar): Dashboard · Locaties/units · Meldingen · Taken · Kalender ·
Team · Abonnement · FAQ & kennisbank · Juridische documenten · Contact.

---

## 1. Dashboard

**Doel:** eerste scherm na login; overzicht van locaties, units, meldingen en taken.

**Header**
- Titel "Dashboard" + subtitel "Overzicht van je locaties, units, meldingen en taken".
- Knop **"Briefing afdrukken"** → printbare briefing met **alle taken die de teams vandaag
  moeten afhandelen** (dagoverzicht per team).
- **Proefperiode-capsule**: "Proefperiode nog X dagen." (zie §Abonnement).

**KPI-kaarten** (neutraal/minimaal, emerald enkel als subtiel accent):
- Locaties/units — totaal
- Units — totaal
- Nieuwe meldingen — aantal nieuw/open
- Open taken — "In uitvoering"

**Recente meldingen** (kaart met lijst)
- "Laatste activiteit door melders of team", met nadruk op **nieuwe** meldingen.
- Rij: omschrijving · locatie — unit · adres · datum/tijd + "gemeld door {naam}" · status-pill.
- Knop **"Meldingen openen"** → meldingenlijst.

**Zwevende elementen**
- **WinProx-logo** zweeft rechtsboven.
- **WinProx-assistent** zweeft rechtsonder (chat):
  - Gebruiker stelt een vraag.
  - Staat het antwoord in de DB → toon antwoord.
  - Zo niet → **vraag opslaan** in een tabel + **e-mail naar de superuser**.
  - Assistent is **gekoppeld aan de FAQ & kennisbank**.

**Device:** desktop-first (laptop).

**Nieuw t.o.v. huidige bouw (backlog):** Briefing-print (taken van vandaag per team),
WinProx-assistent (DB-Q&A + onbeantwoorde-vragen-tabel + superuser-mail + FAQ-koppeling).

---

## 2. Locaties/units

**Doel:** beheer van **locaties** en de **units** daaronder (machines, installaties, zones).
Twee niveaus: **locatie-lijst** → klik op een locatie → **locatie-/unit-detailscherm**.

### 2.1 Locatie-lijst
- Header: titel "Locaties/units" + subtitel "Beheer je locaties en units (machines,
  installaties, zones)".
- Knop **"+ Nieuwe locatie toevoegen"** → modal (§2.2).
- **Zoek-kaart**: zoekveld "Zoek op locatie, plaats, postcode of straat" (zoekt op naam,
  plaats, postcode én straat). Hint "Klik op een locatie om te beheren". Checkbox
  **"Toon ook inactieve locaties"** (standaard verbergt inactieve).
- **Lijst-rij** per locatie: naam · badge **"{n} units"** · adres (straat nr, postcode plaats).
  Klik op de rij → locatie-detail (§2.3). Rechts knop **"Deactiveren"** (soft-delete/inactief
  zetten, niet hard verwijderen).

### 2.2 Modal "Nieuwe locatie toevoegen" / "Locatie bewerken"
Velden:
- **Locatienaam** (optioneel)
- **Straat** + **Huisnummer**
- **Postcode** + **Plaats**
- **ISO-landcode** (geldige tweeletterige landcode, bv. BE)
- **Interne notities** (textarea)

Acties: **Annuleren** / **Locatie opslaan**. Validatie via Form Request; logica in een Action.

### 2.3 Locatie-/unit-detailscherm
- Header: locatienaam + **"Briefing afdrukken"** + **vorige/volgende**-navigatie (‹ ›) om snel
  tussen locaties te bladeren. Subtitel "Overzicht van locatie en units".
- Kaart **"Gegevens van de locatie"**: adres + knoppen **"Locatie bewerken"**,
  **"Deactiveren"**, **"QR-code locatie (algemeen)"** (algemene locatie-QR, los van unit-QR's).
- Kaart **"Units"** met badge **"{n} Totaal"** + "Beheer units gekoppeld aan deze locatie."
  Knoppen:
  - **"QR-stickerblad downloaden"** → printbaar vel met QR-codes van de units (QR-pack).
  - **"+ Unit toevoegen"** → unit per stuk.
  - **"Bulk units toevoegen"** → modal (§2.4).
- **"Recente bulk-aanmaak"**: lijst van bulk-batches (datum/tijd · aantal · unitnaam-bereik),
  met per batch **"Bulk verwijderen ({n})"**. Regel: *"Verwijder per bulk alleen units zonder
  melding of taak. Oudere bulks blijven apart staan."* Units in een bulk die al een melding/taak
  hebben **blijven staan** (melding per batch: "{n} unit(s) in deze bulk hebben al een melding of
  taak en blijven staan").
- **Unit-rij**: unitnaam · gekoppeld **team** · status-badge (bv. "Open melding") + knoppen
  **Bewerken** · **Deactiveren** · **Verwijderen** (uitgeschakeld als de unit een melding/taak
  heeft) · **Unit-QR** (individuele QR-code = publieke meld-link `/melden/{token}`).

### 2.4 Modal "Bulk units toevoegen"
Genereert in één keer meerdere units volgens een patroon.
- **Aantal reeksen** · **Units per reeks**.
- **Nummering** (dropdown), bv. "01, 11, 21… (twee cijfers: bv. Kolomboormachine 11 en 21 —
  max. 9 per reeks)".
- **Optionele prefix** (bv. "Kolomboormachine" → "Kolomboormachine 11", "… 21" …), met
  voorbeeld-hint.
- **Team** (dropdown): "Geen team (later per unit koppelen)" of een vast team voor álle nieuwe
  units (handig bij meerdere machines van hetzelfde team).
- **VOORBEELD**-blok: live preview van de eerste paar gegenereerde unitnamen.
- Acties: **Annuleren** / **Alle units aanmaken**.

**Data (te bevestigen tegen schema):** `locations` (naam, straat, huisnummer, postcode, plaats,
iso_landcode, interne_notities, actief/inactief) en `units` (naam, location_id, internal_team_id,
qr_token, bulk-batch-referentie t.b.v. "recente bulk-aanmaak", actief/inactief).

**Device:** desktop-first (beheer).

**Nieuw t.o.v. huidige bouw (backlog):** locatie-CRUD + (de)activeren, zoek/inactief-filter,
locatie-detail met vorige/volgende, algemene locatie-QR, QR-stickerblad (QR-pack) downloaden,
unit-CRUD per stuk, **bulk-aanmaak met patroon + preview**, recente-bulk-beheer met veilige
verwijderregels (niet verwijderen wat een melding/taak heeft), unit-QR → publieke meld-link.
