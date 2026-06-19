# Translations API

## Overview

The translation sync API exports pending issue and announcement translations and imports completed translations. This supports external translation pipelines (e.g. Ollama on a separate server).

**Access:** Superuser only (`UserPolicy::runTranslationSync`). Requires a valid Sanctum token for a superuser account with API access.

## Endpoints

### Export pending translations

`GET /translations/export`

Returns all pending translation jobs for **approved issues**, **active announcements**, and **active units** across tenants (superuser scope).

**Response:**
```json
{
  "data": {
    "exported_at": "2026-06-04T10:00:00Z",
    "count": 2,
    "items": [
      {
        "issue_id": 42,
        "tenant_id": 1,
        "source_locale": "nl",
        "source_text": "Lekkende kraan",
        "locale": "en",
        "status": "pending"
      },
      {
        "announcement_id": 12,
        "tenant_id": 1,
        "source_locale": "nl",
        "source_text": "Morgen onderhoud",
        "locale": "fr",
        "status": "pending"
      },
      {
        "unit_id": 7,
        "tenant_id": 1,
        "source_locale": "nl",
        "source_name": "Graafmachine TB210R",
        "source_description": "Magazijn zone B",
        "locale": "en",
        "status": "pending"
      }
    ]
  }
}
```

Each item contains either `issue_id`, `announcement_id`, or `unit_id` (never more than one).

### Import completed translations

`POST /translations/import`

**Idempotency:** Supported via standard idempotency middleware.

**Request body:**
```json
{
  "items": [
    {
      "issue_id": 42,
      "locale": "en",
      "description": "Leaking faucet"
    },
    {
      "announcement_id": 12,
      "locale": "fr",
      "description": "Maintenance demain"
    }
  ]
}
```

**Fields per item:**
- `issue_id`, `announcement_id`, or `unit_id` (required) — Target record
- `locale` (required) — Target locale (`nl`, `en`, `fr`, `de`)
- `description` (required for issues/announcements) — Completed translation (max 1500 characters)
- `name` (optional, units) — Translated unit name (max 255 characters)
- `description` (optional, units) — Translated unit description (max 1500 characters)

**Response:**
```json
{
  "data": {
    "imported": 2
  }
}
```

Importing dispatches webhooks when endpoints subscribe to `issue.translation_imported`, `announcement.translation_imported`, or `unit.translation_imported`.

### Translation sync status

`GET /translations/status`

Returns the current platform translation sync job status (phase, progress, errors).

**Response:**
```json
{
  "data": {
    "status": {
      "phase": "completed",
      "total": 10,
      "imported": 10,
      "finished_at": "2026-06-04T10:15:00Z"
    }
  }
}
```

## CLI equivalents

For server-side automation without HTTP:

```bash
php artisan translation:export
php artisan translation:import
php artisan winprox:translate-issues
php artisan winprox:translate-announcements
php artisan winprox:translate-units
php artisan winprox:translate-tasks
php artisan winprox:translate-documents
```

## Webhooks

Translation import triggers domain webhooks (not in-app Ollama translation):

| Event | When |
|-------|------|
| `issue.translation_imported` | After a completed issue translation is imported |
| `announcement.translation_imported` | After a completed announcement translation is imported |
| `unit.translation_imported` | After a completed unit translation is imported |
| `task.translation_imported` | After a completed task translation is imported |
| `document.translation_imported` | After a completed document translation is imported |

See [Webhooks](./webhooks.md) for payload format and signature verification.

## Examples

### Export pending translations

```bash
curl "https://your-domain.com/api/v1/translations/export" \
  -H "Authorization: Bearer superuser-token"
```

### Import completed translations

```bash
curl -X POST "https://your-domain.com/api/v1/translations/import" \
  -H "Authorization: Bearer superuser-token" \
  -H "Content-Type: application/json" \
  -d '{
    "items": [
      {
        "announcement_id": 12,
        "locale": "en",
        "description": "Maintenance tomorrow"
      }
    ]
  }'
```
