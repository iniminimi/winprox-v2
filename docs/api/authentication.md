# Authentication

## Overview

WinProx API uses Laravel Sanctum for authentication. API tokens are created in the WinProx application under Settings > API.

Desktop Microsoft Entra SSO (admin/employee login) is **not** API authentication. Integrations (Ultimo, IWMS, CMMS) keep using Sanctum tokens. See `docs/FEATURES.md` §6.4.

## Creating an API Token

1. Navigate to **Settings > API** in WinProx
2. Enter a token name (e.g., "Integration X")
3. Select the required abilities for the token
4. Click **Create Token**
5. **Important:** Copy the token immediately - it will only be shown once

## Using the Token

Include the token in the `Authorization` header:

```bash
curl -H "Authorization: Bearer your-token-here" \
  https://your-domain.com/api/v1/issues
```

## Token Abilities

Tokens can be created with specific abilities to limit access:

### Full Access Token
```php
// Token with all abilities
$token = $user->createToken('Full Access', ['*'])->plainTextToken;
```

### Limited Access Token
```php
// Token with specific abilities
$token = $user->createToken('Read Only', [
    'issues:read',
    'tasks:read',
    'locations:read',
])->plainTextToken;
```

### Available Abilities

| Ability | Endpoints |
|---------|-----------|
| `issues:read` | `GET /api/v1/issues`, `GET /api/v1/issues/{id}` |
| `issues:create` | `POST /api/v1/issues` |
| `issues:update` | `POST /api/v1/issues/{id}/approve` |
| `tasks:read` | `GET /api/v1/tasks`, `GET /api/v1/tasks/{id}` |
| `tasks:create` | `POST /api/v1/tasks` |
| `tasks:update` | `POST /api/v1/tasks/{id}/start`, `POST /api/v1/tasks/{id}/complete`, `POST /api/v1/tasks/{id}/status` |
| `locations:read` | `GET /api/v1/locations` |
| `units:read` | `GET /api/v1/units` |
| `teams:read` | `GET /api/v1/teams` |
| `workers:read` | `GET /api/v1/workers` |
| `time:read` | `GET /api/v1/time/work-shifts` |
| `time:write` | `POST /api/v1/time/clock-in`, `POST /api/v1/time/clock-out`, `POST /api/v1/time/workers/{id}/release-clock-device` |
| `esg:create` | `POST /api/v1/esg/measurements` |
| `webhooks:manage` | Manage webhook endpoints via UI |
| `*` | All endpoints |

## Revoking a Token

To revoke an API token:
1. Navigate to **Settings > API**
2. Find the token in the list
3. Click **Revoke**

## Security Best Practices

- **Never commit API tokens to version control**
- **Use environment variables** to store tokens in your applications
- **Create separate tokens** for different integrations
- **Use the principle of least privilege** - only grant required abilities
- **Rotate tokens regularly** for production integrations
- **Monitor API usage** in the WinProx audit logs

## Example Integration

### PHP (Guzzle)
```php
$client = new GuzzleHttp\Client([
    'base_uri' => 'https://your-domain.com/api/v1',
    'headers' => [
        'Authorization' => 'Bearer ' . getenv('WINPROX_API_TOKEN'),
        'Accept' => 'application/json',
    ],
]);

$response = $client->get('issues');
$issues = json_decode($response->getBody(), true);
```

### JavaScript (Fetch)
```javascript
const response = await fetch('https://your-domain.com/api/v1/issues', {
  headers: {
    'Authorization': `Bearer ${process.env.WINPROX_API_TOKEN}`,
    'Accept': 'application/json',
  },
});

const data = await response.json();
```

### Python (Requests)
```python
import requests

headers = {
    'Authorization': f'Bearer {os.getenv("WINPROX_API_TOKEN")}',
    'Accept': 'application/json',
}

response = requests.get('https://your-domain.com/api/v1/issues', headers=headers)
issues = response.json()
```

## Error Handling

### 401 Unauthorized
Your token is invalid or expired. Create a new token in WinProx.

### 403 Forbidden
Your token lacks the required ability for this endpoint. Check the endpoint documentation and update your token abilities.

### 429 Too Many Requests
You've exceeded the rate limit (60 requests/minute). Implement exponential backoff in your integration.
