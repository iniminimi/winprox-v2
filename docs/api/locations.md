# Locations API

## Overview

Locations represent buildings or properties in the WinProx system.

## Endpoints

### List Locations

`GET /locations`

**Required Ability:** `locations:read`

**Query Parameters:**
- `is_active` (optional) - Filter by active status (`true`/`false`)
- `page` (optional) - Pagination page number (default: 1)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Main Building",
      "address": "123 Main Street",
      "city": "Brussels",
      "postal_code": "1000",
      "country": "BE",
      "is_active": true,
      "created_at": "2026-01-01T00:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 25,
    "total": 5
  }
}
```

## Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Unique identifier |
| `name` | string | Location name |
| `address` | string | Street address |
| `city` | string | City name |
| `postal_code` | string | Postal/ZIP code |
| `country` | string | Country code (ISO 3166-1 alpha-2) |
| `is_active` | boolean | Whether location is active |
| `created_at` | datetime | Creation timestamp |
| `updated_at` | datetime | Last update timestamp |

## Examples

### List All Active Locations
```bash
curl "https://your-domain.com/api/v1/locations?is_active=true" \
  -H "Authorization: Bearer your-token"
```

### List All Locations
```bash
curl https://your-domain.com/api/v1/locations \
  -H "Authorization: Bearer your-token"
```
