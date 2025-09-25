# Transfer Receive Evidence Capture

## Overview

This document describes the QR/token workflow introduced for the transfer receiving module. Staff can now generate a secure mobile upload link from the receive screen, capture damage photos or short videos, and attach them directly to the transfer record.

## Token Issuance

* **Endpoint:** `https://staff.vapeshed.co.nz/cisv2/api/uploads/create_token.php`
* **Method:** `POST application/json`
* **Headers:** `X-CSRF-Token`, `X-Request-ID`
* **Required payload:**
  * `transfer_id` (int)
* **Optional payload:**
  * `scope` (`transfer` | `parcel` | `discrepancy`)
  * `parcel_id` (int) — must belong to transfer when scope is `parcel`
  * `discrepancy_id` (int) — must belong to transfer when scope is `discrepancy`
  * `expires_minutes` (5–720, default 120)
  * `allowed_mime` (array of mime strings)
  * `max_bytes` (bytes per file, default 15 MiB)
  * `meta` (assorted JSON payload for auditing)

* **Response:**
  ```json
  {
    "ok": true,
    "token": "d3f...",
    "expires_at": "2025-09-25 13:42:00",
    "upload_url": "https://staff.vapeshed.co.nz/cisv2/mobile/receive-upload.php?token=...",
    "qr_url": "https://staff.vapeshed.co.nz/cisv2/api/uploads/qr.php?token=...",
    "allowed_mime": ["image/jpeg", "image/png", "video/mp4"],
    "max_bytes": 15728640
  }
  ```

The front-end opens `qr_url` in a new window; staff scan the QR with any mobile camera.

## Mobile Upload Page

* **Path:** `https://staff.vapeshed.co.nz/cisv2/mobile/receive-upload.php?token=...`
* **Features:**
  * Accepts multiple photos/videos in a single submission (capture or library)
  * Optional staff name + notes
  * Auto-captures device UA and approximate GPS (if permission granted)
  * Submits to the ingest API, then clears form on success

## Ingest API

* **Endpoint:** `https://staff.vapeshed.co.nz/cisv2/api/uploads/ingest.php`
* **Method:** `POST multipart/form-data`
* **Fields:**
  * `token` — transfer media token string
  * `media[]` — one or more image/video files (required)
  * `notes` — optional string
  * `captured_name` — optional string
  * `device_label` — optional string
  * `latitude`, `longitude` — optional decimals captured client-side

* **Response:**
  ```json
  {
    "ok": true,
    "files": [
      {"path": "https://staff.vapeshed.co.nz/...", "mime": "image/jpeg", "size": 4587210, "kind": "photo"}
    ],
    "token": "d3f..."
  }
  ```

The ingest service enforces the token scope, rate limits per IP, validates MIME/size, saves files under
`/cisv2/modules/transfers/stock/assets/media/{transfer_id}/`, and records metadata (latitude, notes, device, original
filename) inside `transfer_media`.

## Database Changes

* New table `transfer_media_tokens` stores issued tokens, expiry, scope, and meta.
* `transfer_media` gains metadata columns: `token_id`, `captured_by`, `captured_lat/lng`, `captured_meta`, `captured_at`, `device_label`, etc.

## Logging & Auditing

Each critical action logs to the CIS event bus:

* `media.token.create` — token creation (transfer, scope, expiry)
* `media.upload` — ingest completion with transfer, token, and IP summary

Tokens are automatically marked expired when accessed after `expires_at`; the ingest endpoint records `used_at` for audit.

## Security Notes

* Tokens are random 128-bit values, rate limited, and expire after two hours by default.
* Upload directory disallows script execution and directory listing.
* All generated URLs are absolute (`https://staff.vapeshed.co.nz/...`) for clarity when sharing across devices.
* GPS capture is best-effort; uploads still succeed without geolocation permission.
