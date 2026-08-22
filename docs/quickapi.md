# DigiSign Pro — Quick API Guide (for humans & AI agents)

> Machine-friendly integration brief. Replace placeholders before calling.
>
> - **Base URL (app):** `{{BASE_URL}}`
> - **API base:** `{{API_BASE}}`
> - **API version:** `v1`
> - **Auth:** per-user API key (not shared app secrets)

---

## 1. Authentication

Every user has a unique API key (`digi_...`) shown in **Profil Saya**.

Send **one** of:

```http
Authorization: Bearer {{API_KEY}}
```

```http
X-API-Key: {{API_KEY}}
```

Optional (not recommended in browsers):

```http
GET {{API_BASE}}/me?api_key={{API_KEY}}
```

### Rules for agents
- Never hardcode another user's key.
- Prefer header auth over query string.
- On `401` with `invalid_api_key`, ask the user to regenerate the key in the profile UI.
- Rate limit: default ~60 req/min per user (throttle).

---

## 2. Conventions

| Item | Value |
|------|--------|
| Content-Type JSON | `application/json` |
| Multipart uploads | `multipart/form-data` |
| Success envelope | `{ "success": true, "message": "...", "data": ... }` |
| Error envelope | `{ "success": false, "message": "...", "error"?: "...", "errors"?: {...} }` |
| Coordinates for sign | **millimeters** from top-left of the PDF page (same as web UI) |
| QR stamp size | **25mm × 25mm** |

---

## 3. Endpoints (summary)

### Public (no API key)

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/v1/health` | Health / app name |
| `GET` | `/api/v1/verify/{code}` | Verify document by code |
| `GET` | `/api/v1/docs/quickapi.md` | This document |

### Authenticated (API key required)

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/api/v1/me` | Current user profile |
| `GET` | `/api/v1/stats` | User stats & PDF tool usage counters |
| `GET` | `/api/v1/signatures` | List signature history |
| `GET` | `/api/v1/signatures/{id}` | Signature detail |
| `DELETE` | `/api/v1/signatures/{id}` | Delete signature (+ file if any) |
| `POST` | `/api/v1/sign/single` | Sign one PDF |
| `POST` | `/api/v1/sign/qr-manual` | Create manual TTE QR record |

---

## 4. Endpoint details

### 4.1 Health
```http
GET {{API_BASE}}/health
```

**Response 200**
```json
{
  "success": true,
  "message": "API healthy",
  "data": {
    "status": "ok",
    "app": "DigiSign Pro",
    "time": "2026-08-22T10:00:00+07:00",
    "api_version": "v1"
  }
}
```

---

### 4.2 Me
```http
GET {{API_BASE}}/me
Authorization: Bearer {{API_KEY}}
```

**data fields:** `id`, `name`, `email`, `role` (`admin`|`user`), `position`, `signature_prefix`, `api_key_created_at`

---

### 4.3 Stats & Tool Usage
```http
GET {{API_BASE}}/stats
Authorization: Bearer {{API_KEY}}
```

**Response 200**
```json
{
  "success": true,
  "message": "Statistik akun dan PDF tools",
  "data": {
    "user": {
      "id": 1,
      "name": "Super Admin",
      "role": "admin",
      "signature_prefix": "DS"
    },
    "signatures": {
      "total": 42,
      "digital": 30,
      "qr_manual": 12
    },
    "pdf_tools": {
      "editor": { "name": "Visual PDF Editor", "uses": 15, "files": 15 },
      "merge": { "name": "Merge PDF", "uses": 8, "files": 24 },
      "split": { "name": "Split PDF", "uses": 5, "files": 5 },
      "organize": { "name": "Organize & Rotate", "uses": 6, "files": 6 },
      "watermark": { "name": "Watermark PDF", "uses": 4, "files": 4 },
      "page_number": { "name": "Page Numbering", "uses": 3, "files": 3 },
      "protect": { "name": "Protect & Encrypt", "uses": 2, "files": 2 },
      "image_to_pdf": { "name": "Image to PDF", "uses": 7, "files": 14 }
    }
  }
}
```

---

### 4.4 List signatures
```http
GET {{API_BASE}}/signatures?search=&signature_type=&per_page=20&page=1
Authorization: Bearer {{API_KEY}}
```

| Query | Type | Notes |
|-------|------|--------|
| `search` | string | name / number / subject / verify_code / batch_id |
| `signature_type` | `digital` \| `qr_manual` | optional filter |
| `user_id` | int | **admin only** |
| `per_page` | int | 1–100, default 20 |
| `page` | int | pagination page |

**data**
```json
{
  "items": [ { "id": 1, "verify_code": "DS-...", "file_url": "..." } ],
  "pagination": { "current_page": 1, "per_page": 20, "total": 3, "last_page": 1 }
}
```

Non-admin users only see their own records.

---

### 4.4 Signature detail
```http
GET {{API_BASE}}/signatures/{id}
Authorization: Bearer {{API_KEY}}
```

---

### 4.5 Delete signature
```http
DELETE {{API_BASE}}/signatures/{id}
Authorization: Bearer {{API_KEY}}
```

Deletes DB row and storage file when present. Owner or admin only.

---

### 4.6 Sign single PDF
```http
POST {{API_BASE}}/sign/single
Authorization: Bearer {{API_KEY}}
Content-Type: multipart/form-data
```

| Field | Required | Description |
|-------|----------|-------------|
| `pdf_file` | yes | PDF file |
| `x` | yes | QR left position in **mm** |
| `y` | yes | QR top position in **mm** |
| `page` | yes | 1-based page number |
| `pdf_password` | yes | Owner password / parafrase for PDF protection |
| `document_number` | no | Doc number |
| `document_subject` | no | Subject / perihal |
| `document_attachment` | no | Lampiran text |
| `signed_date` | no | `YYYY-MM-DD` (default today) |
| `show_qr_caption` | no | `1`/`0` or true/false (default true) |
| `qr_caption_position` | no | `bottom` \| `right` |

**cURL example**
```bash
curl -X POST "{{API_BASE}}/sign/single" \
  -H "Authorization: Bearer {{API_KEY}}" \
  -F "pdf_file=@/path/to/doc.pdf" \
  -F "x=150" \
  -F "y=220" \
  -F "page=1" \
  -F "document_number=001/SK/2026" \
  -F "document_subject=Surat Keputusan" \
  -F "pdf_password=rahasia123" \
  -F "show_qr_caption=1" \
  -F "qr_caption_position=bottom"
```

**Response 201 data**
```json
{
  "id": 12,
  "verify_code": "DS-20260724-ABC123",
  "verify_url": "{{BASE_URL}}/verify/DS-20260724-ABC123",
  "file_path": "storage/uploads/signatures/...",
  "file_url": "{{BASE_URL}}/storage/uploads/signatures/..."
}
```

**Agent tips**
- Prefer placing QR on lower-right with enough margin (≥ 5mm from edges).
- A4 width ≈ 210mm, height ≈ 297mm.
- If API returns `unsupported_pdf`, re-export PDF as PDF 1.4 / “Print to PDF”.

---

### 4.7 Create TTE QR (manual)
```http
POST {{API_BASE}}/sign/qr-manual
Authorization: Bearer {{API_KEY}}
Content-Type: application/json
```

```json
{
  "document_number": "QR-001",
  "subject": "Undangan",
  "attachment": "1 Berkas",
  "signed_at": "2026-07-24",
  "pdf_password": "rahasia123"
}
```

**Response 201 data** includes `verify_code`, `verify_url`, and `qr_image_data_uri` (PNG data URI) for embedding/download.

---

### 4.8 Public verify
```http
GET {{API_BASE}}/verify/{code}
```

No API key. Returns signer + document metadata + optional `file_url`.

---

## 5. Error codes (common)

| HTTP | `error` | Meaning |
|------|---------|---------|
| 401 | `missing_api_key` | No key provided |
| 401 | `invalid_api_key` | Unknown key |
| 403 | `forbidden` | Not owner / not admin |
| 404 | `not_found` | Resource missing |
| 422 | — | Validation / PDF issues (`errors` may be present) |
| 429 | — | Rate limited |
| 500 | `sign_failed` | Signing pipeline error |

---

## 6. Minimal agent workflow

1. `GET /api/v1/health` — confirm base URL.
2. `GET /api/v1/me` with key — confirm identity.
3. Upload & sign: `POST /api/v1/sign/single`.
4. Store `verify_code` + `file_url` for the user.
5. Optionally `GET /api/v1/verify/{code}` to confirm public verification page.

---

## 7. Security checklist for agents

- Do not log full API keys.
- Do not commit keys to git.
- Use HTTPS in production.
- Treat `pdf_password` as secret (PDF owner password).
- After key regenerate, old key is immediately invalid.

---

## 8. Related UI

- Profile → **REST API Key** → view / copy / regenerate
- Profile → **Unduh quickapi.md** (this file, with live base URL substituted)
- Web verify: `{{BASE_URL}}/verify/{code}`

---

*Generated for {{APP_NAME}}. End of quickapi.md*
