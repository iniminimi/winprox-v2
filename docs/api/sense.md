# Sense readings

Device key for one room. The admin screen does not show it. Does not create issues or tasks.

Pairing: on the room, Scherm issues a code. A D1S posts that code to `POST /api/v1/time/clock-displays/claim` (the same call as a clock) and polls `GET /api/v1/time/clock-displays/claim-status/{claim_token}`. A room code is accepted there. `POST /api/v1/screens/claim` remains for the same code. After the admin confirms, the clock poll returns `device_token`, `display_id`, `display_secret` and `rotation_seconds` (3600). The screen poll returns `sense_key` only when Sense is on. The key is not shown in the admin UI. The room QR opens `/melden/{token}` and changes once an hour. The linked D1S then pings and posts Sense on the clock-display endpoints with `X-WinProx-Clock-Key`.

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

Send about every five minutes. A value outside its band is attention in WinProx, also when that reading is older than 15 minutes. A room whose last reading is inside the band and older than 15 minutes is shown as no measurement.

Stored readings are listed in the admin, on the screen’s Sense card (Metingen). This endpoint does not return them.
