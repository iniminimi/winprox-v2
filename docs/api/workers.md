# Workers API

## Overview

Workers represent individual team members who can be assigned to tasks.

## Endpoints

### List Workers

`GET /workers`

**Required Ability:** `workers:read`

**Query Parameters:**
- `internal_team_id` (optional) - Filter by team ID
- `is_active` (optional) - Filter by active status (`true`/`false`)
- `page` (optional) - Pagination page number (default: 1)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "first_name": "John",
      "last_name": "Doe",
      "internal_team_id": 3,
      "is_active": true,
      "is_teamleader": false,
      "created_at": "2026-01-01T00:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 2,
    "per_page": 25,
    "total": 50
  }
}
```

## Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Unique identifier |
| `first_name` | string | First name |
| `last_name` | string | Last name |
| `internal_team_id` | integer | Team ID |
| `is_active` | boolean | Whether worker is active |
| `is_teamleader` | boolean | Whether worker is a team leader |
| `created_at` | datetime | Creation timestamp |
| `updated_at` | datetime | Last update timestamp |

## Examples

### List Workers for a Team
```bash
curl "https://your-domain.com/api/v1/workers?internal_team_id=3" \
  -H "Authorization: Bearer your-token"
```

### List All Active Workers
```bash
curl "https://your-domain.com/api/v1/workers?is_active=true" \
  -H "Authorization: Bearer your-token"
```
