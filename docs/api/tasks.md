# Tasks API

## Overview

Tasks represent specific work items within an issue. Each issue can have multiple tasks assigned to different teams.

## Endpoints

### List Tasks

`GET /tasks`

**Required Ability:** `tasks:read`

**Query Parameters:**
- `status` (optional) - Filter by status (`new`, `in_progress`, `completed`)
- `issue_id` (optional) - Filter by issue ID
- `internal_team_id` (optional) - Filter by team ID
- `page` (optional) - Pagination page number (default: 1)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "description": "Fix faucet",
      "status": "new",
      "priority": "prio_2",
      "issue_id": 5,
      "internal_team_id": 3,
      "scheduled_for": "2026-06-05",
      "due_at": "2026-06-05T18:00:00Z",
      "created_at": "2026-06-04T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 25,
    "total": 75
  }
}
```

### Get Task

`GET /tasks/{id}`

**Required Ability:** `tasks:read`

**Response:**
```json
{
  "data": {
    "id": 1,
    "description": "Fix faucet",
    "status": "new",
    "priority": "prio_2",
    "issue_id": 5,
    "internal_team_id": 3,
    "scheduled_for": "2026-06-05",
    "due_at": "2026-06-05T18:00:00Z",
    "created_at": "2026-06-04T10:00:00Z",
    "issue": {
      "id": 5,
      "description": "Leaking faucet in bathroom"
    },
    "team": {
      "id": 3,
      "name": "Plumbing Team"
    }
  }
}
```

### Create Task

`POST /api/v1/tasks`

**Required Ability:** `tasks:create`

**Request Body:**
```json
{
  "description": "Fix faucet",
  "issue_id": 5,
  "internal_team_id": 3,
  "priority": "prio_2",
  "scheduled_for": "2026-06-05",
  "due_at": "2026-06-05T18:00:00Z"
}
```

**Fields:**
- `description` (required) - Task description
- `issue_id` (required) - Issue ID
- `internal_team_id` (required) - Team ID
- `priority` (optional) - Priority level: `prio_1`, `prio_2`, `prio_3`, `prio_4` (default: `prio_3`)
- `scheduled_for` (optional) - Date for scheduling (YYYY-MM-DD)
- `due_at` (optional) - Due date/time (ISO 8601)

**Response:** `201 Created`
```json
{
  "data": {
    "id": 1,
    "description": "Fix faucet",
    "status": "new",
    "priority": "prio_2",
    "issue_id": 5,
    "internal_team_id": 3,
    "created_at": "2026-06-04T10:00:00Z"
  }
}
```

### Start Task

`POST /api/v1/tasks/{id}/start`

**Required Ability:** `tasks:update`

**Response:** `200 OK`
```json
{
  "data": {
    "id": 1,
    "status": "in_progress",
    "started_at": "2026-06-05T09:00:00Z"
  }
}
```

### Complete Task

`POST /api/v1/tasks/{id}/complete`

**Required Ability:** `tasks:update`

**Response:** `200 OK`
```json
{
  "data": {
    "id": 1,
    "status": "completed",
    "completed_at": "2026-06-05T14:30:00Z"
  }
}
```

### Update Task Status

`POST /tasks/{id}/status`

**Required Ability:** `tasks:update`

**Request Body:**
```json
{
  "status": "in_progress"
}
```

**Valid Status Values:**
- `new` - Task created, not started
- `in_progress` - Task in progress
- `completed` - Task completed

**Response:** `200 OK`
```json
{
  "data": {
    "id": 1,
    "status": "in_progress"
  }
}
```

## Priority Levels

| Priority | Description | Badge Color |
|----------|-------------|-------------|
| `prio_1` | Critical | Red (with pulse animation) |
| `prio_2` | High | Orange |
| `prio_3` | Medium | Green |
| `prio_4` | Low | Light Gray |

## Status Values

- `new` - Task created, awaiting start
- `in_progress` - Task being worked on
- `completed` - Task finished

## Examples

### Create a High Priority Task
```bash
curl -X POST https://your-domain.com/api/v1/tasks \
  -H "Authorization: Bearer your-token" \
  -H "Content-Type: application/json" \
  -d '{
    "description": "Fix faucet",
    "issue_id": 5,
    "internal_team_id": 3,
    "priority": "prio_2",
    "due_at": "2026-06-05T18:00:00Z"
  }'
```

### Start a Task
```bash
curl -X POST https://your-domain.com/api/v1/tasks/1/start \
  -H "Authorization: Bearer your-token"
```

### Complete a Task
```bash
curl -X POST https://your-domain.com/api/v1/tasks/1/complete \
  -H "Authorization: Bearer your-token"
```

### List Tasks for a Team
```bash
curl "https://your-domain.com/api/v1/tasks?internal_team_id=3" \
  -H "Authorization: Bearer your-token"
```
