# Sense readings

Device key for one room (shown once in **Plaatsen → Sense**). Does not create issues or tasks.

## Auth

```http
X-WinProx-Sense-Key: wpsense_…
```

Or `Authorization: Bearer wpsense_…`.

The tenant must be active. Checkmate tenants are refused.

## `POST /api/v1/sense/readings`

```json
{
  "temperature": 21.5,
  "humidity": 52,
  "co2": 980,
  "voc": 120,
  "recorded_at": "2026-11-24T11:42:00+01:00"
}
```

All four values are required. The same `recorded_at` for the same room is stored once.

`data.attention` is true when the reading is outside the lower or upper band, or when CO₂ or the VOC index is above its upper mark. The target mark is not an alarm.

Send about every five minutes. A room with no reading for 15 minutes is shown as attention in WinProx.
