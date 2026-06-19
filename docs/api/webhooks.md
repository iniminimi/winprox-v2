# Webhooks

## Overview

Webhooks allow you to receive real-time notifications about events in WinProx. When an event occurs, WinProx sends an HTTP POST request to your configured endpoint with event data.

## Setup

1. Navigate to **Settings > API** in WinProx
2. Scroll to the **Webhooks** section
3. Enter your endpoint URL
4. Select the events you want to subscribe to
5. Click **Save**
6. Copy the **Secret** for signature verification

## Available Events

| Event | Description | Payload |
|-------|-------------|---------|
| `issue.created` | A new issue was created | Issue metadata |
| `issue.approved` | An issue was approved | Issue metadata |
| `issue.status_changed` | An issue status changed | Issue metadata |
| `issue.translation_imported` | A completed issue translation was imported | Translation metadata |
| `announcement.translation_imported` | A completed announcement translation was imported | Translation metadata |
| `task.created` | A new task was created | Task data |
| `task.started` | A task was started | Task data |
| `task.completed` | A task was completed | Task data |
| `unit.gps_reported` | A GPS report was recorded for a unit | GPS report metadata |

### Translation webhook payloads

`issue.translation_imported` and `announcement.translation_imported` fire when a translation is stored via the translation import API or sync pipeline (not when Ollama translates in-app).

```json
{
  "version": "1.0",
  "event": "announcement.translation_imported",
  "payload": {
    "announcement_id": 42,
    "locale": "en",
    "status": "completed",
    "actor_user_id": 7
  },
  "delivery_id": 123
}
```

For issues, replace `announcement_id` with `issue_id` and use event `issue.translation_imported`.

## Webhook Payload

All webhook payloads follow this structure:

```json
{
  "version": "1.0",
  "event": "issue.created",
  "payload": {
    "id": 1,
    "description": "Leaking faucet",
    "status": "new",
    "priority": "prio_2",
    "created_at": "2026-06-04T10:00:00Z"
  },
  "delivery_id": 123
}
```

## Headers

Each webhook request includes these headers:

| Header | Description |
|--------|-------------|
| `Content-Type` | `application/json` |
| `X-WinProx-Event` | Event name (e.g., `issue.created`) |
| `X-WinProx-Delivery` | Delivery ID for tracking |
| `X-WinProx-Timestamp` | Unix timestamp of request |
| `X-WinProx-Signature` | HMAC-SHA256 signature prefixed with `sha256=` |

## Signature Verification

To verify the webhook is from WinProx:

1. Extract the `X-WinProx-Timestamp` and `X-WinProx-Signature` headers
2. The signature format is: `sha256=<signature>` where `<signature>` is the HMAC-SHA256 hash
3. Concatenate timestamp and raw body: `{timestamp}.{body}`
4. Calculate HMAC-SHA256 using your secret
5. Compare with the received signature (use timing-safe comparison)

### Example (PHP)

```php
$timestamp = $request->header('X-WinProx-Timestamp');
$signature = $request->header('X-WinProx-Signature');
$payload = $request->getContent();

$expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $payload, $your_secret);

if (hash_equals($expected, $signature)) {
    // Valid webhook
} else {
    // Invalid signature
}
```

### Example (Node.js)

```javascript
const crypto = require('crypto');

const timestamp = req.headers['x-winprox-timestamp'];
const signature = req.headers['x-winprox-signature'];
const payload = req.rawBody;

const expected = 'sha256=' + crypto
  .createHmac('sha256', yourSecret)
  .update(timestamp + '.' + payload)
  .digest('hex');

if (crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(signature))) {
  // Valid webhook
} else {
  // Invalid signature
}
```

### Example (Python)

```python
import hmac
import hashlib

timestamp = request.headers.get('X-WinProx-Timestamp')
signature = request.headers.get('X-WinProx-Signature')
payload = request.get_data()

expected = 'sha256=' + hmac.new(
    your_secret.encode(),
    f'{timestamp}.{payload}'.encode(),
    hashlib.sha256
).hexdigest()

if hmac.compare_digest(expected, signature):
    # Valid webhook
else:
    # Invalid signature
```

## Retry Behavior

WinProx automatically retries failed webhook deliveries:

| Attempt | Delay |
|---------|-------|
| 1 | Immediate |
| 2 | 10 seconds |
| 3 | 60 seconds |
| 4 | 300 seconds (5 minutes) |

After 4 failed attempts, the delivery is marked as `failed` and no further retries are attempted.

## Response Handling

Your endpoint should respond with:

- **2xx status code** - Webhook delivery successful
- **4xx/5xx status code** - Webhook delivery failed (will be retried)

**Best practice:** Always respond with `200 OK` or `204 No Content` as quickly as possible, then process the event asynchronously.

## Testing Webhooks

Use the **Test** button in the webhook settings to send a test webhook:

```json
{
  "version": "1.0",
  "event": "test",
  "payload": {
    "message": "Test webhook from WinProx",
    "timestamp": "2026-06-04T10:00:00Z"
  }
}
```

## Delivery Logs

View webhook delivery logs in **Settings > API** under the **Deliveries** section. Each log shows:

- Event type
- Endpoint URL
- Status (`pending`, `success`, `failed`)
- Error message (if failed)
- Attempt count

## Security Best Practices

- **Always verify signatures** before processing webhook data
- **Use HTTPS** for your webhook endpoint
- **Implement idempotency** - handle duplicate deliveries gracefully
- **Log all webhook events** for debugging
- **Monitor failed deliveries** and investigate patterns
- **Keep your secret secure** - never expose it in logs or version control

## Example Webhook Handler (PHP/Laravel)

```php
public function handle(Request $request)
{
    $timestamp = $request->header('X-WinProx-Timestamp');
    $signature = $request->header('X-WinProx-Signature');
    $payload = $request->getContent();
    
    $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $payload, config('services.winprox.webhook_secret'));
    
    if (! hash_equals($expected, $signature)) {
        return response()->json(['error' => 'Invalid signature'], 401);
    }
    
    $data = json_decode($payload, true);
    $event = $request->header('X-WinProx-Event');
    
    switch ($event) {
        case 'issue.created':
            $this->handleIssueCreated($data['payload']);
            break;
        case 'task.completed':
            $this->handleTaskCompleted($data['payload']);
            break;
        // Handle other events...
    }
    
    return response()->json(['status' => 'ok'], 200);
}
```
