# Unit Measurements API

Record a **measurement value** on a unit (odometer, temperature, status, …). Measurements are **append-only** — there is no update or delete endpoint.

## Requirements

- API token with ability `units:update`
- Unit must belong to the token’s tenant
- **Unit measurements must be enabled** on both the unit’s **category** and the **unit** (`allow_unit_measurements`; both default off)
- The measure field must be **active** and **linked** to the unit

## Record a measurement

`POST /api/v1/units/{unit}/measurements`

**Required Ability:** `units:update`

### Body

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `unit_measure_field_id` | integer | Yes | Field linked to this unit |
| `recorded_at` | string (ISO-8601) | Yes | Client timestamp |
| `value_numeric` | number | Conditional | For `numeric` fields |
| `value_boolean` | boolean | Conditional | For `boolean` fields |
| `value_string` | string | Conditional | For `string` / `choice` fields (max 500) |

Exactly one value column matching the field type is required.

### Example

```http
POST /api/v1/units/12/measurements
Authorization: Bearer {token}
Content-Type: application/json

{
  "unit_measure_field_id": 3,
  "recorded_at": "2026-08-27T10:15:00+02:00",
  "value_numeric": 125430.5
}
```

Response `source` is `api`.

## Webhook

`unit.measurement.recorded` — fired for every new measurement (portal, API, or admin).
