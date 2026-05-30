# WinProx — richting & architectuur (V2 → toekomst)

Dit document beschrijft **waar WinProx naartoe wil** (commercieel + technisch) en **welke keuzes we nú al moeten maken** tijdens de V2-herbouw, zodat latere fases (API-integraties, offline veld, PLG, AI, enterprise) **niet opnieuw het fundament** hoeven te vervangen.

Lees dit **samen met**:

| Document | Rol |
|----------|-----|
| `WINPROX_RULES.md` | Harde bouwregels (dagelijks) — **§12 verwijst hierheen** |
| `docs/FEATURES.md` | Scherm-voor-scherm Facility-pariteit (levend) |
| `WINPROX_FEATURES.md` | V1 Facility-overzicht (referentie) |
| **Dit document** | Roadmap & toekomst — **achtergrond**, niet proactief bouwen |

> **Voor agents:** dit is geen tweede todo-lijst. Gebruik het om te voorkomen dat je keuzes maakt
> die later offline, API of enterprise integraties dwingen tot herbouw. Wat **nu** af moet, staat in
> `docs/FEATURES.md`.

---

## 1. Noordster

**WinProx is de uitvoeringslaag voor facilitair werk op de werkvloer.**

- **QR first** — elke locatie, unit en werkomgeving is bereikbaar via QR.
- **Worker first** — de werkvloer gaat vóór administratieve complexiteit.
- **Mobile first** — kernflows moeten op smartphone werken (veld + publiek).
- **Simplicity wins** — eenvoudiger dan klassieke FMIS/IWMS/EAM; geen sector- of contractor-rommel.

**Paradigma (later, enterprise):** *Enterprise software beheert de data. WinProx beheert de uitvoering.*

Dat is **visie**, geen excuus om V2 uit te stellen. Eerst **Facility-pariteit** (`WINPROX_FEATURES.md`), dan gericht uitbreiden.

---

## 2. Fasering — wat wanneer

De lange-termijn roadmap heeft zes “product”-fasen. **De echte volgorde voor WinProx:**

```
Fase 0 — Facility-pariteit V2     ← NU (must-have)
Fase A — PLG & onboarding         ← vroeg, commercieel laag risico
Fase B — Veld online perfect      ← QR/worker flows stabiel, geen offline yet
Fase C — API & integraties (smal) ← uitbreiden wat al in regels staat
Fase D — Offline PWA veld         ← aparte client; bewuste investering
Fase E — AI (light)               ← pas bij volume & stabiele data
Fase F — Enterprise pakketten     ← klant-gedreven, één connector tegelijk
```

| Roadmap-fase | Inhoud | Wanneer | Herbouw-risico als te vroeg |
|--------------|--------|---------|------------------------------|
| **0 Pariteit** | Alles uit `WINPROX_FEATURES.md` | **Nu** | — |
| **3 PLG** | Gratis QR-pack, demo, scan-to-demo, wizard | Parallel aan 0, marketing | Laag |
| **1 Integration** | REST, keys, webhooks, events, idempotency | **Smal na 0**; fundament ligt al in regels | Hoog als domein nog instabiel |
| **2 Offline** | PWA, sync, conflict engine | **Na B** | **Zeer hoog** — tweede frontend |
| **4 AI** | Speech, routing, trends | **Na E-volume** | Medium (privacy, false positives) |
| **5–6 Marketplace / Enterprise** | Zapier, SAP, ServiceNow, … | **Jaar+ horizon** | Hoog per connector |

**Regel voor elke agent/taak:** bouw nooit Phase D/E/F-features ten koste van Fase 0, tenzij expliciet gevraagd.

---

## 3. Twee oppervlakken — nu al scheiden

WinProx heeft **twee soorten UI** met verschillende toekomst:

| Oppervlak | Nu | Later | Stack |
|-----------|-----|-------|--------|
| **Beheer** | Dashboard, locaties, meldingen, teams, billing | Uitbreiden | Livewire 4 + Blade |
| **Veld / publiek** | Unit-QR, team-QR, melden, worker-acties | Offline PWA | Nu Livewire; **later eigen field-client** |

**Beslissing (hard):**

- Alle veldlogica blijft in **Actions** (+ DTO’s, Policies, Events) — nooit “alleen in Livewire”.
- Veld-Livewire is **vandaag** de UI; **morgen** kan dezelfde Action via REST worden aangeroepen door een PWA.
- Bouw **geen** business rules in JavaScript die niet via Actions reproduceerbaar zijn.

**Anti-pattern:** offline sync in Livewire “er even bij”. Dat wordt herbouwd. Offline = Phase D met expliciet sync-protocol.

---

## 4. Architectuur — nu al goed zetten (uit `WINPROX_RULES.md`)

De regels beschrijven al het juiste fundament. Dit document legt uit **waarom** — zodat agents het niet “optimaliseren” weg.

### 4.1 Integration First (Actions als enige waarheid)

Elke mutatie: **ingang → Form Request / validatie → DTO → Action → Event → (optioneel) webhook job**.

- Livewire, API-controller, webhook-incoming, CLI, scheduler: **dezelfde Action**.
- Actions: geen `auth()`, `session()`, redirects — **actor + tenant expliciet**.
- **Waarom later:** REST (Phase 1), Zapier (Phase 5), enterprise middleware (Phase 6) hangen hier direct aan.

### 4.2 Domein-events — stabiele namen

Gebruik **vaste event-klassen** en payload-vorm; wijzig niet ad hoc.

Huidige richting (uitbreidbaar, niet hernoemen zonder migratieplan):

| Event (voorbeeld) | Webhook-topic (toekomst) | Integratie-gebruik |
|-------------------|--------------------------|--------------------|
| `IssueCreated` | `issue.created` | CMMS, ticketing |
| `IssueApproved` | `issue.approved` | Publicatie workflows |
| `IssueStatusChanged` | `issue.status_changed` | Dashboards extern |
| `TaskCreated` | `task.created` | Werkorders |
| `TaskStarted` | `task.started` | Tijdregistratie |
| `TaskCompleted` | `task.completed` | Afsluiting ERP |

**Nu:** elke nieuwe workflow krijgt een event als de mutatie “extern relevant” kan zijn (default: ja bij create/status).

**Later:** webhook delivery log, retries, HMAC — geen wijziging aan Action-signatures nodig.

### 4.3 API-first, maar smal shippen

Regels vereisen API + webhooks **vanaf het begin**. Praktisch:

- **Nu:** endpoints voor entiteiten die integraties nodig hebben (issues, tasks, locations, units, teams).
- **Niet nu:** volledige developer portal, rate-limit tiers, public API marketplace.
- **JSON Resources** (`IssueResource`, …) zijn het contract — wijzig voorzichtig (versioning `/api/v1`).

### 4.4 Idempotency — voorbereiden, niet overal bouwen

Phase 1 roadmap: `Idempotency-Key` op muterende POST/PUT.

**Nu al:**

- Actions **idempotent** ontwerpen waar retries logisch zijn (statusovergang “al in die status” = success/no-op).
- Overweeg een **`idempotency_keys`-tabel** (tenant, key, action, response snapshot) wanneer de eerste externe integratie live gaat — **niet** voor elke interne Livewire-klik.

### 4.5 Audit & conflicts (Phase 2 offline)

`audit_logs` (regels §3.3d) is niet alleen compliance — het is de basis voor **conflict resolution**:

- Elke statuswijziging, worker-actie, goedkeuring: audit + event.
- **Issue updates** append-only (tijdlijn), geen stille overschrijving van historiek.

**Later offline:** “field reality first” = worker-actie altijd bewaren; conflict = extra audit-entry + optionele admin flag — geen stille merge.

### 4.6 RBAC via Policies

Rollen nu: **superuser, admin, user, team manager (scope), worker (geen login)**.

- **Geen** verspreide `isAdmin()` in Blade/Livewire — Policies (`authorize()`).
- **Waarom later:** API tokens en Phase 1 “granular keys” mappen op dezelfde Policies/abilities.

Worker-autorisatie: expliciete **Worker + device/session** context in Action-argumenten, niet “ingelogde user”.

---

## 5. Datamodel — extensiepunten zonder V1-rommel

Bouw **Facility-pariteit** met **V2-statussen (4)** — geen terugkeer naar V1’s 7 facility-statussen in de DB.

### 5.1 Nu al in schema/logica opnemen (goedkoop)

| Extensie | Doel (latere fase) | Richtlijn |
|----------|-------------------|-----------|
| `updated_at` / consistent timestamps | Offline sync, conflict detectie | Overal op mutable models |
| Opaque QR-tokens (UUID) | PLG, print, geen sequential IDs | Nooit integer in URL |
| `IssueSource` enum | QR / manager / api / import | Al aanwezig — uitbreiden, niet string |
| Media als eigen rijen (`issue_photos`) | Sync per bestand, retry | Geen blob in JSON |
| `tenant_id` + global scope | Multi-tenant + API | Nooit vergeten in queries |
| Nullable `external_ref` op Issue/Task (optioneel) | SAP/ServiceNow koppeling | **Eén** kolom paar, geen per-vendor tabellen yet |

### 5.2 Bewust **niet** nu invoeren

| Feature | Waarom wachten |
|---------|----------------|
| Sector/contractor/owner tabellen | Scope creep; expliciet buiten V2 |
| `on_hold` / `not_executed` statussen | V2 simplificatie; map in UI/audit indien nodig |
| AI priority scores | Geen kolom tot regels duidelijk zijn |
| Sync queue tables | Pas bij Phase D (offline outbox) |
| Per-connector mapping tabellen | Pas bij eerste enterprise-klant |

### 5.3 Unit “criticality” (Phase 4 AI)

Als AI-prioriteit gewenst is: **één enum/veld** `units.criticality` (normal / elevated / critical) — handmatig beheerbaar, later AI-invulling. Nu optioneel nullable; geen ML-pipeline.

---

## 6. Veld & QR — voorbereiden op offline en PLG

### 6.1 Nu (Phase 0 / B)

- Zelfde **foto-golden-path** overal (compressie client, queue upload, `issue_photos`).
- **Worker device session** (cookie/token) gescheiden van staff login — hergebruik V1-gedrag, maar via Actions.
- Publieke routes: **statisch**, geen session-auth voor burgers; worker via device/worker-context.
- QR-pack generatie: **isolated** in `app/Support/Qr/` — herbruikbaar voor PLG “gratis pack” zonder tenant.

### 6.2 PLG (Phase A) — architectuur

| Feature | Voorbereiding nu |
|---------|------------------|
| Gratis QR-pack zonder account | QR-builder service + rate limit + anonieme “lead” opslag (eigen tabel) |
| Avery / Herma | Template-strategie: één interface `StickerSheetTemplate` (Avery55x55, HermaXyz) |
| Scan-to-demo | Vaste demo-tenant + demo-tokens; geen productie-data |
| Guided onboarding | Checklist driven by **tenant state** (teams/locations/units/workers/qr_downloaded) — zoals V1 `FacilitySetupProgress` |

PLG mag **marketing routes** zijn; domeinmutaties blijven Actions.

### 6.3 Offline (Phase D) — wat nu al moet kloppen

Voordat offline gebouwd wordt, moet online **exact** dit model hebben:

1. **Task list API** per team/worker (filter, paginatie).
2. **Mutations:** start, complete, add note — elk met server-side validation + idempotency.
3. **Media:** upload URL of multipart endpoint; client kan retry met zelfde idempotency key.
4. **Read models** stabiel (Resources), geen Livewire-specifieke JSON.

Conflict policy (vastleggen, niet implementeren):

- Worker offline acties: **nooit verwerpen**.
- Admin wijziging + worker wijziging: beide in audit; default **worker fysieke uitvoering** wint op status “completed”, admin op “cancel/close”.

---

## 7. Integraties & enterprise (Phase 1, 5, 6)

### 7.1 Nu

- Sanctum tokens, webhook endpoints, HMAC delivery — volgens regels.
- **Stripe** apart (billing webhook); geen generiek hook-framework daarvoor mengen.
- Inkomende hooks: signature → DTO → Action (geen raw payload in Action).

### 7.2 Later — één connector tegelijk

Enterprise pakketten (SAP, Planon, ServiceNow, Dynamics) zijn **geen core product**:

- Mappinglaag **buiten** core Actions (adapter/job) die **intern** gewone Actions aanroept.
- Externe ID in `external_ref` / `external_source` — geen vendor-logica in `CreateIssueAction`.

**Positionering behouden:** WinProx = execution frontend; ERP/IWMS = system of record.

---

## 8. AI (Phase 4) — guardrails

Pas starten als Phase 0+B stabiel en er **echte data** is.

| Capability | Veilige first step |
|------------|-------------------|
| Speech-to-text | Optionele veld-notitie; expliciete consent; geen verplicht |
| Classification / routing | Suggestie tonen aan admin; **niet** auto-assign zonder confirm |
| Morning briefing summary | Text template + optional LLM op bestaande briefing query |
| Trend detection | Report/job op `issues`/`tasks`; geen realtime ML |

**GDPR:** geen voice/image naar third party zonder tenant-beleid en DPA.

---

## 9. Checklist voor elke nieuwe feature (agent)

Voordat code merged wordt:

1. **Pariteit** — staat het in `docs/FEATURES.md` (of expliciet gevraagde “future phase”)?
2. **Action** — alle mutaties via Action + DTO; geen DB in Livewire.
3. **Policy** — autorisatie gecentraliseerd.
4. **Event** — relevante domein-event + test dispatch.
5. **API** — als extern consumeerbaar: endpoint + Resource + Pest.
6. **Audit** — write gelogd.
7. **Tenant** — scope overal.
8. **Locales** — 4 talen parity als UI strings.
9. **Veld vs beheer** — juiste oppervlak; veld = mobiel-first.
10. **Toekomst** — blokkeert dit offline/API/PLG? Zo ja, aanpassen volgens §4–6.

---

## 10. Veelgemaakte herbouwfouten (vermijden)

| Fout | Gevolg | Alternatief |
|------|--------|-------------|
| Business logic in Livewire/Blade | API + offline onmogelijk zonder copy | Actions |
| V1 facility-statussen terug | Rollup, UI, tests breken | 4 statussen + audit voor nuance |
| Contractor/owner “even snel” | Scope explosion | Nee |
| Offline in Livewire hooks | Phase D throwaway | REST + PWA later |
| Per-integratie kolommen in issues | Schema chaos | `external_ref` + adapter layer |
| Developer portal vóór stabiele API | Documenteert verkeerd contract | OpenAPI pas na v1 stabiliteit |
| AI auto-routing zonder human | Verkeerde team assignments | Suggest-only |
| Tweede CSS/design systeem veld | Design drift | Zelfde tokens/`wp-*` |

---

## 11. Documentatie-hiërarchie voor agents

```
1. WINPROX_RULES.md      → hoe bouwen (hard)
2. docs/FEATURES.md      → scherm-voor-scherm pariteit (levend)
3. WINPROX_FEATURES.md   → V1 Facility-overzicht (referentie)
4. WINPROX_DIRECTION.md  → roadmap & toekomst (achtergrond — dit bestand)
5. winprox_old/          → referentie gedrag, port via Actions
```

Bij conflict: **RULES > FEATURES (docs) > DIRECTION > V1-code**.

Direction mag **geen** features verplichten die `docs/FEATURES.md` niet noemt, tenzij RULES het al verplicht (bv. API/events).

---

## 12. Samenvatting in één alinea

**Bouw V2 eerst af als simpel, Action-gedreven Facility-product met Livewire-beheer en mobiele QR-portals; houd domeinlogica UI-agnostisch, events en audit consistent, Policies als RBAC, en API smal maar correct. Bereid PLG en offline voor door QR/media/worker-sessie generiek te houden — bouw offline en enterprise als aparte lagen boven dezelfde Actions, niet als vervanging van het hart.**

---

*Laatste update: mei 2026 — align met V2-regels (Integration First, API/webhooks, Policies, audit).*
