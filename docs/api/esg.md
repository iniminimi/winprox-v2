# ESG Measurements API

## Overview

Record ESG/compliance readings linked to tasks and indicators. Measurements are **append-only** — there is no update or delete endpoint.

**Requirements:**
- Tenant must have the **ESG module** enabled (`has_esg_module`)
- API token with ability `esg:create`

## Endpoints

### Record Measurement

`POST /api/v1/esg/measurements`

**Required Ability:** `esg:create`

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `task_id` | integer | Yes | Task ID (must belong to your tenant) |
| `esg_indicator_id` | integer | Yes | Active indicator ID |
| `recorded_at` | string (ISO 8601) | Yes | When the reading was taken (client time) |
| `value_numeric` | number | Conditional | Required for numeric indicators |
| `value_boolean` | boolean | Conditional | Required for boolean indicators |
| `value_string` | string | Conditional | Required for text and **choice** indicators (choice must match a defined option) |
| `value_json` | object or array | Conditional | Required for **json** indicators (free-form object) or **multi_choice** indicators (array of option strings) |
| `worker_id` | integer | No | Worker who took the reading |
| `corrects_measurement_id` | integer | No | ID of measurement being corrected (future use) |

Exactly **one** value field must be provided, matching the indicator type.

**Example (numeric):**

```http
POST /api/v1/esg/measurements
Authorization: Bearer your-api-token
Content-Type: application/json

{
  "task_id": 42,
  "esg_indicator_id": 3,
  "recorded_at": "2026-07-08T10:15:00+02:00",
  "value_numeric": 456.78,
  "worker_id": 7
}
```

**Response (201 Created):**

```json
{
  "data": {
    "id": 101,
    "task_id": 42,
    "esg_indicator_id": 3,
    "unit_id": 12,
    "location_id": 5,
    "worker_id": 7,
    "corrects_measurement_id": null,
    "recorded_at": "2026-07-08T08:15:00.000000Z",
    "created_at": "2026-07-08T08:16:02.000000Z",
    "value_numeric": 456.78,
    "value_boolean": null,
    "value_string": null,
    "value_json": null
  }
}
```

`unit_id` and `location_id` are derived from the task/issue automatically.

## Validation Errors (422)

Common validation failures:

- Missing or wrong value type for the indicator
- Inactive or unknown indicator
- Task not found or indicator does not match the linked issue
- ESG module disabled for tenant

## Webhooks

Each successful recording dispatches `esg.measurement.recorded`. See [Webhooks](./webhooks.md#esg-measurement-recorded).

## Related

- Indicators are managed in the WinProx UI (`/esg/indicators`), not via API in phase 1.
- Workers can also submit readings when completing tasks on the **unit QR portal**.
