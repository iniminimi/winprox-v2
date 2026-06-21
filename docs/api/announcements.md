# Announcements API

## Overview

Announcements are location- or unit-scoped messages shown on QR portals (mededelingen). Only **active** announcements are returned by the API.

## Endpoints

### List Announcements

`GET /announcements`

**Required Ability:** `locations:read`

**Query Parameters:**
- `location_id` (optional) — Filter by location ID
- `unit_id` (optional) — Filter by unit ID
- `page` (optional) — Pagination page number (default: 1)

**Response:**
```json
{
  "data": [
    {
      "id": 12,
      "location_id": 5,
      "unit_id": null,
      "description": "Morgen onderhoud aan de lift",
      "original_language": "nl",
      "translations": {
        "en": "Elevator maintenance tomorrow",
        "fr": "Maintenance de l'ascenseur demain"
      },
      "is_active": true,
      "published_at": "2026-06-04T08:00:00Z",
      "expires_at": "2026-06-11T23:59:59Z",
      "created_at": "2026-06-04T08:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 25,
    "total": 1
  }
}
```

### Get Announcement

`GET /announcements/{id}`

**Required Ability:** `locations:read`

**Response:** Same fields as a list item (single object under `data`).

## Field reference

| Field | Description |
|-------|-------------|
| `description` | Localized text (app locale; see [Translations](./translations.md)) |
| `original_language` | Source locale (`nl`, `en`, `fr`, `de`, `es`) |
| `translations` | Completed translations by locale |
| `is_active` | Whether the announcement is active in beheer |
| `published_at` | When the announcement was published |
| `expires_at` | Optional expiry (null = no expiry) |

## Translations

When an active announcement is created or activated, WinProx creates pending translation slots for the other supported locales. Completed translations appear in the `translations` map.

Import completed translations via the [Translations API](./translations.md). Subscribe to `announcement.translation_imported` webhooks for real-time notifications (see [Webhooks](./webhooks.md)).

## Examples

### List announcements for a location

```bash
curl "https://your-domain.com/api/v1/announcements?location_id=5" \
  -H "Authorization: Bearer your-token"
```

### Get a single announcement

```bash
curl "https://your-domain.com/api/v1/announcements/12" \
  -H "Authorization: Bearer your-token"
```
