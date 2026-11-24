# Camera photos

A camera belongs to one room. It is separate from the screen. Photos stay on the card in the device. WinProx stores a copy only when an administrator turns forwarding on.

The admin screen never shows the device key.

## Pairing

`POST /api/v1/cameras/claim`

```json
{ "code": "ABCD-EFGH", "device_hint": "XIAO-hal" }
```

`code` is the one-time code from the camera page. `device_hint` is a short name of the device, at most 64 characters.

The response is `{ "data": { "claim_token", "status": "pending" } }`.

Poll `GET /api/v1/cameras/claim-status/{claim_token}`.

After the administrator confirms, and while that claim is still within its window, the poll returns:

```json
{
  "data": {
    "status": "confirmed",
    "camera_key": "wpcam_…",
    "forwarding": false
  }
}
```

`forwarding` is false until the administrator turns it on. Store the key on the device. It is not shown in WinProx.

## Auth

```http
X-WinProx-Camera-Key: wpcam_…
```

Or `Authorization: Bearer wpcam_…`.

The organisation must be active. Checkmate organisations are refused.

## `GET /api/v1/cameras/status`

Returns `{ "data": { "forwarding": true } }` or `false`. Call this before sending a photo. A successful call marks the camera as seen.

## `POST /api/v1/cameras/photos`

Multipart field name: `photo`. JPEG, PNG or WebP. When forwarding is off, the photo is refused and nothing is stored.

A stored copy is kept for a limited time and then deleted. The card in the device is not changed.
