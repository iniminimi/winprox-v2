# Unit Checks API

Record a quick **OK / Not OK** visit check on a unit (security rounds, cleaning presence, technician walkthrough). Checks are **append-only** — there is no update or delete endpoint.

## Requirements

- API token with ability `units:update`
- Unit must belong to the token’s tenant
- **Unit checks must be enabled** on both the unit’s **category** and the **unit** (`allow_unit_checks`; both default off). Otherwise create is denied.

## Record a check (WinProx unit id)

`POST /api/v1/units/{unit}/checks`

**Required Ability:** `units:update`

### Body

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `result` | string | Yes | `ok` or `not_ok` |
| `checked_at` | string (ISO-8601) | Yes | Client timestamp |
| `latitude` | number | No | Requires `longitude` |
| `longitude` | number | No | Requires `latitude` |
| `task_id` | integer | No | Optional link to a related task |
| `issue_id` | integer | No | Optional link when a follow-up issue exists |
| `checklist_items` | string[] | No | Labels of checked checklist points (when the unit has a checklist) |
| `external_id` | string | No | Idempotency key from the caller (unique per tenant) |

### Example

```http
POST /api/v1/units/12/checks
Authorization: Bearer {token}
Content-Type: application/json

{
  "result": "ok",
  "checked_at": "2026-08-01T10:15:00+02:00",
  "latitude": 51.05,
  "longitude": 3.72,
  "checklist_items": ["Floor", "WC"]
}
```

Response `source` is `api`.

## Inbound sync (external facility software)

Push a check using the unit’s **external id** mapping (set under Locaties → unit bewerken → Externe ID). Use this when an IWMS, CMMS or ERP drives the round and WinProx stores the visit.

`POST /api/v1/units/checks`

**Required Ability:** `units:update`

Supports HTTP idempotency middleware (`Idempotency-Key` header) **and** optional body `external_id` (unique per tenant — replays return the existing check).

### Body

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `external_unit_id` | string | Yes | Must match `units.external_id` for this tenant |
| `result` | string | Yes | `ok` or `not_ok` |
| `checked_at` | string (ISO-8601) | Yes | Client timestamp |
| `external_id` | string | No | Caller’s check id (idempotent) |
| `latitude` / `longitude` | number | No | Pair required together |
| `task_id` / `issue_id` | integer | No | Optional links |
| `checklist_items` | string[] | No | Optional |

### Example

```http
POST /api/v1/units/checks
Authorization: Bearer {token}
Content-Type: application/json
Idempotency-Key: round-2026-08-01-room-42

{
  "external_unit_id": "ROOM-42",
  "external_id": "CHECK-99881",
  "result": "ok",
  "checked_at": "2026-08-01T10:15:00+02:00"
}
```

Response `source` is `external`. First create → `201`; idempotent replay of the same `external_id` → `200`.

### Unit mapping

Set `external_id` on the unit in the admin UI (Locaties → unit). It is unique per tenant and exposed on `GET /api/v1/units` as `external_id`.

## Webhook

Each successful **new** recording dispatches `unit.check.recorded` (includes `external_id` and `unit_external_id` when set). Idempotent replays do not re-dispatch. See [Webhooks](./webhooks.md).
