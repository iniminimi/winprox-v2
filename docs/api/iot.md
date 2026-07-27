# IoT Connect API

Gateway-authenticated ingest for sensor events. Available on **Facility** (alarms → issues)
and **Corporate** (alarms + ESG measurements). Does **not** require full Sanctum API access.

## Auth

Send the gateway token (shown once when creating a gateway in **IoT Connect**):

```http
X-WinProx-Iot-Key: wpiot_…
```

Or:

```http
Authorization: Bearer wpiot_…
```

Tenant must have `has_iot_module` and an active subscription (or legacy/trial access rules).

## `POST /api/v1/iot/events`

```json
{
  "external_sensor_id": "leak-01",
  "kind": "alarm",
  "value": 1,
  "occurred_at": "2026-07-27T10:00:00+02:00",
  "idempotency_key": "optional-unique-key"
}
```

| Field | Required | Notes |
|-------|----------|--------|
| `external_sensor_id` | yes | Must match an active sensor on this gateway |
| `kind` | yes | `alarm` or `measurement` |
| `value` | no | Numeric; null allowed for binary alarms |
| `occurred_at` | yes | ISO-8601 |
| `idempotency_key` | no | Per gateway; repeats return the same event |

### Alarm (`kind=alarm`)

Evaluates active rules on the sensor. On match: creates an **approved** Issue (`source=iot`)
+ Task (team/priority from rule). Open issues for the same rule are **deduped**.

Facility and Corporate.

### Measurement (`kind=measurement`)

Requires Corporate **ESG** module, sensor linked to unit + numeric ESG indicator.
Writes `esg_measurements` with `task_id = null`, then may still fire matching alarm rules.

Facility without ESG → event status `ignored`.

## Response

```json
{
  "data": {
    "id": 1,
    "kind": "alarm",
    "status": "processed",
    "external_sensor_id": "leak-01",
    "value": 1,
    "iot_sensor_id": 3,
    "iot_rule_id": 2,
    "issue_id": 10,
    "esg_measurement_id": null,
    "occurred_at": "…",
    "received_at": "…"
  }
}
```

Statuses: `processed` · `ignored` · `deduped` · `failed`
