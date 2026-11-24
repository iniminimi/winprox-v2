# Sense readings

Device key for one room. The admin screen does not show it. Does not create issues or tasks.

Pairing: on the room, Scherm issues a code. The device posts `POST /api/v1/screens/claim` with `{ "code", "device_hint" }` and polls `GET /api/v1/screens/claim-status/{claim_token}`. After the admin confirms, that poll returns `sense_key`, `display_id`, `display_secret` and `qr_window_seconds` (3600 for a room). The key is not shown in the admin UI. The room QR opens `/melden/{token}` and changes once an hour.

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

`data.attention` is true when temperature or humidity is outside the lower or upper mark, or when CO₂ is above its upper mark. The target mark is not an alarm. The VOC index (1–500) turns attention on only after it stays above the room limit (default 150) for 30 minutes. One high sample is not attention. 100 is the sensor’s own 24-hour average, not a health limit.

Send about every five minutes. A room with no reading for 15 minutes is shown as attention in WinProx.
