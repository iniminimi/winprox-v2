# Unit Checks API

Record a quick **OK / Not OK** visit check on a unit (security rounds, cleaning presence, technician walkthrough). Checks are **append-only** — there is no update or delete endpoint.

## Requirements

- API token with ability `units:update`
- Unit must belong to the token’s tenant

## Record a check

`POST /api/v1/units/{unit}/checks`

**Required Ability:** `units:update`

### Body

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `result` | string | Yes | `ok` or `not_ok` |
| `checked_at` | string (ISO-8601) | Yes | Client timestamp |
| `latitude` | number | No | Requires `longitude` |
| `longitude` | number | No | Requires `latitude` |
| `task_id` | integer | No | Reserved for future checklist/round linking |
| `issue_id` | integer | No | Optional link when a follow-up issue exists |
| `checklist_items` | string[] | No | Reserved for future checklists |

### Example

```http
POST /api/v1/units/12/checks
Authorization: Bearer {token}
Content-Type: application/json

{
  "result": "ok",
  "checked_at": "2026-08-01T10:15:00+02:00",
  "latitude": 51.05,
  "longitude": 3.72
}
```

### Webhook

Each successful recording dispatches `unit.check.recorded`. See [Webhooks](./webhooks.md).
