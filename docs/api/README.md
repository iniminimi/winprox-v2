# WinProx API Documentation

**Version:** 1.0  
**Base URL:** `https://your-domain.com/api/v1`

## Overview

The WinProx API provides RESTful endpoints for managing issues, tasks, locations, units, teams, and workers. All endpoints require authentication via Sanctum API tokens.

## Authentication

API requests require a Bearer token in the `Authorization` header:

```http
Authorization: Bearer your-api-token
```

### Subscription Requirements

API access and webhooks are available for:
- **Corporate** plan (API access and webhooks)
- Trial accounts with explicit API access enabled (contact support)

**IoT Connect ingest** (`POST /api/v1/iot/events`) uses a **gateway token** and is available on
**Facility** and **Corporate** without full Sanctum API access. See [iot.md](iot.md).

Starter, Micro, and Pro plans do not include API access. Trial accounts without explicit API access will receive a `403 Forbidden` response when attempting to use API endpoints or configure webhooks.

### Token Abilities

API tokens can be created with specific abilities to follow the principle of least privilege:

| Ability | Description |
|---------|-------------|
| `issues:read` | Read issues |
| `issues:create` | Create issues |
| `issues:update` | Update/approve issues |
| `tasks:read` | Read tasks |
| `tasks:create` | Create tasks |
| `tasks:update` | Update/start/complete tasks |
| `locations:read` | Read locations |
| `units:read` | Read units |
| `teams:read` | Read teams |
| `workers:read` | Read workers |
| `esg:create` | Record ESG measurements |
| `webhooks:manage` | Manage webhook endpoints |
| `*` | Full access (all abilities) |

### Rate Limiting

All API endpoints are rate-limited to **60 requests per minute** per token.

## Response Format

All responses follow a consistent JSON format:

**Success Response:**
```json
{
  "data": { ... }
}
```

**Paginated Response:**
```json
{
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 25,
    "total": 125
  }
}
```

**Error Response:**
```json
{
  "message": "Error description"
}
```

HTTP Status Codes:
- `200` - OK
- `201` - Created
- `401` - Unauthorized (invalid or missing token)
- `403` - Forbidden (insufficient abilities or subscription requirement not met)
- `422` - Validation Error
- `429` - Too Many Requests (rate limit exceeded)
- `500` - Internal Server Error

## REST Conventions

The API follows RESTful conventions with some specific action endpoints:

- **GET** - Retrieve resources
- **POST** - Create resources or trigger specific actions (e.g., `/approve`, `/start`)
- **PUT/PATCH** - Not used; specific actions use POST with explicit endpoints
- **DELETE** - Not currently exposed

## Endpoints

- [Authentication](./authentication.md)
- [Issues](./issues.md)
- [Tasks](./tasks.md)
- [Locations](./locations.md)
- [Announcements](./announcements.md)
- [Translations](./translations.md)
- [Units](./units.md)
- [Teams](./teams.md)
- [Workers](./workers.md)
- [ESG Measurements](./esg.md)
- [IoT Connect](./iot.md)
- [Webhooks](./webhooks.md)

## Multi-Tenancy

The API automatically scopes all data to the tenant associated with the API token. You cannot access data from other tenants.

## Webhooks

Webhooks allow you to receive real-time notifications about events in WinProx. See the [Webhooks documentation](./webhooks.md) for details on events, signatures, and retry behavior.
