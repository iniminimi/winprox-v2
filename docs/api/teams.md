# Teams API

## Overview

Teams represent internal work groups (e.g., plumbing, electrical, cleaning) that can be assigned tasks.

## Endpoints

### List Teams

`GET /teams`

**Required Ability:** `teams:read`

**Query Parameters:**
- `is_active` (optional) - Filter by active status (`true`/`false`)
- `page` (optional) - Pagination page number (default: 1)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Plumbing Team",
      "is_active": true,
      "created_at": "2026-01-01T00:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 25,
    "total": 3
  }
}
```

## Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Unique identifier |
| `name` | string | Team name |
| `is_active` | boolean | Whether team is active |
| `created_at` | datetime | Creation timestamp |
| `updated_at` | datetime | Last update timestamp |

## Examples

### List All Active Teams
```bash
curl "https://your-domain.com/api/v1/teams?is_active=true" \
  -H "Authorization: Bearer your-token"
```

### List All Teams
```bash
curl https://your-domain.com/api/v1/teams \
  -H "Authorization: Bearer your-token"
```
