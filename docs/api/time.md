# Time API

## Overview

Time endpoints clock workers in and out and manage the optional punch-clock module. Clock Point QR binds **one phone per worker**. API clock-in uses source `api` and **does not** bind a phone.

## Endpoints

### List work shifts

`GET /time/work-shifts`

**Required Ability:** `time:read`

### Clock in

`POST /time/clock-in`

**Required Ability:** `time:write`

Does **not** bind `workers.clock_device_id`. Use this for integrations, not as a substitute for Clock Point QR identity.

**Body:**

```json
{
  "worker_id": 1,
  "clock_point_id": 2
}
```

### Clock out

`POST /time/clock-out`

**Required Ability:** `time:write`

Same body as clock-in.

### Release bound clock device

`POST /time/workers/{worker}/release-clock-device`

**Required Ability:** `time:write`

Clears the phone bound to a worker so they can clock in on a new device via Clock Point QR. Same Action as Personen → Teams and the team-leader portal control.

**Example:**

```bash
curl -X POST "https://your-domain.com/api/v1/time/workers/1/release-clock-device" \
  -H "Authorization: Bearer your-token"
```

### Roster (planned shifts)

Planned time is separate from punched `WorkShift`. Publishing does not send CIAO/RSZ events.

`GET /time/schedule?week_start=2026-09-14&team_id=`

**Required Ability:** `time:read`

`PUT /time/schedule`

**Required Ability:** `time:write`

Body: `week_start`, optional `period` (`week` default, or `month`), `worker_ids`, `cells` (`worker_id`, `date`, `raw` code or `07:00-15:00`). Full visible week or month snapshot.

`POST /time/schedule/copy` — all-or-nothing copy to another week; refused if the target week already has shifts.

`POST /time/schedule/publish` — marks the visible week (or month if `period=month`) as published.

Shift types: `GET/POST /time/shift-types`, `PATCH /time/shift-types/{id}`, `POST /time/shift-types/{id}/active`.

Webhooks: `time.schedule.saved`, `time.schedule.copied`, `time.schedule.published`, `time.shift_type.saved`.
