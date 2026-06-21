# Issues API

## Overview

Issues represent maintenance requests or problems reported in properties.

## Endpoints

### List Issues

`GET /issues`

**Required Ability:** `issues:read`

**Query Parameters:**
- `status` (optional) - Filter by status (`new`, `in_progress`, `completed`, `closed`)
- `location_id` (optional) - Filter by location ID
- `unit_id` (optional) - Filter by unit ID
- `page` (optional) - Pagination page number (default: 1)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "description": "Leaking faucet in bathroom",
      "original_language": "nl",
      "translations": {
        "en": "Leaking faucet in bathroom",
        "fr": "Robinet qui fuit dans la salle de bain"
      },
      "status": "new",
      "location_id": 5,
      "unit_id": 12,
      "created_at": "2026-06-04T10:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 25,
    "total": 125
  }
}
```

### Get Issue

`GET /api/v1/issues/{id}`

**Required Ability:** `issues:read`

**Response:**
```json
{
  "data": {
    "id": 1,
    "description": "Leaking faucet in bathroom",
    "original_language": "nl",
    "translations": {
      "en": "Leaking faucet in bathroom"
    },
    "status": "new",
    "location_id": 5,
    "unit_id": 12,
    "approved_at": null,
    "is_recurring": false,
    "created_at": "2026-06-04T10:00:00Z",
    "tasks": [
      {
        "id": 1,
        "description": "Fix faucet",
        "status": "new",
        "priority": "prio_2",
        "internal_team_id": 3
      }
    ]
  }
}
```

### Create Issue

`POST /api/v1/issues`

**Required Ability:** `issues:create`

**Request Body:**
```json
{
  "description": "Leaking faucet in bathroom",
  "location_id": 5,
  "unit_id": 12,
  "priority": "prio_2",
  "team_ids": [3, 7]
}
```

**Fields:**
- `description` (required) - Issue description
- `location_id` (required) - Location ID
- `unit_id` (optional) - Unit ID
- `priority` (optional) - Priority level: `prio_1`, `prio_2`, `prio_3`, `prio_4` (default: `prio_3`)
- `team_ids` (optional) - Array of team IDs to assign

**Response:** `201 Created`
```json
{
  "data": {
    "id": 1,
    "description": "Leaking faucet in bathroom",
    "status": "new",
    "priority": "prio_2",
    "location_id": 5,
    "unit_id": 12,
    "created_at": "2026-06-04T10:00:00Z"
  }
}
```

### Approve Issue

`POST /issues/{id}/approve`

**Required Ability:** `issues:update`

**Response:** `200 OK`
```json
{
  "data": {
    "id": 1,
    "status": "in_progress",
    "approved_at": "2026-06-04T10:05:00Z"
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

- `new` - Issue created, awaiting approval
- `in_progress` - Issue approved and being worked on
- `completed` - All tasks completed
- `closed` - Issue resolved and closed

## Translations

Issues support automatic multi-language descriptions:

- `description` — localized text for the API consumer's app locale (falls back to source text when no translation exists).
- `original_language` — source locale (`nl`, `en`, `fr`, `de`, `es`).
- `translations` — map of completed translations by locale (only present when the `translations` relation is loaded).

Pending or failed translations are omitted from the API response. Use the [Translations API](./translations.md) to export pending items and import completed translations.

## Examples

### Create a Critical Issue
```bash
curl -X POST https://your-domain.com/api/v1/issues \
  -H "Authorization: Bearer your-token" \
  -H "Content-Type: application/json" \
  -d '{
    "description": "Water pipe burst - emergency",
    "location_id": 5,
    "unit_id": 12,
    "priority": "prio_1",
    "team_ids": [3]
  }'
```

### List Issues for a Location
```bash
curl "https://your-domain.com/api/v1/issues?location_id=5" \
  -H "Authorization: Bearer your-token"
```

### Approve an Issue
```bash
curl -X POST https://your-domain.com/api/v1/issues/1/approve \
  -H "Authorization: Bearer your-token"
```
