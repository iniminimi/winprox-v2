# Units API

## Overview

Units represent individual apartments, offices, or spaces within a location.

## Endpoints

### List Units

`GET /units`

**Required Ability:** `units:read`

**Query Parameters:**
- `location_id` (optional) - Filter by location ID
- `is_active` (optional) - Filter by active status (`true`/`false`)
- `page` (optional) - Pagination page number (default: 1)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Unit 101",
      "location_id": 5,
      "floor": 1,
      "is_active": true,
      "created_at": "2026-01-01T00:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 4,
    "per_page": 25,
    "total": 100
  }
}
```

## Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Unique identifier |
| `name` | string | Unit name/number |
| `location_id` | integer | Parent location ID |
| `floor` | integer | Floor number |
| `is_active` | boolean | Whether unit is active |
| `created_at` | datetime | Creation timestamp |
| `updated_at` | datetime | Last update timestamp |

## Examples

### List Units for a Location
```bash
curl "https://your-domain.com/api/v1/units?location_id=5" \
  -H "Authorization: Bearer your-token"
```

### List All Active Units
```bash
curl "https://your-domain.com/api/v1/units?is_active=true" \
  -H "Authorization: Bearer your-token"
```
