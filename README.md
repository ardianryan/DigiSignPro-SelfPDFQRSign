<div align="center">

# 🔏 DigiSign Pro

**Enterprise-Grade Self-Hosted Electronic Signature (TTE) & PDF QR Verification Platform**

[![Laravel 13](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![React 19](https://img.shields.io/badge/React-19.x-61DAFB?style=for-the-badge&logo=react&logoColor=black)](https://react.dev)
[![Inertia.js](https://img.shields.io/badge/Inertia.js-2.x-9553E9?style=for-the-badge&logo=inertia&logoColor=white)](https://inertiajs.com)
[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-3.x-06B6D4?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com)
[![Tests Passing](https://img.shields.io/badge/Pest%20Tests-66%20Passed-22C55E?style=for-the-badge&logo=pest&logoColor=white)](tests)
[![License MIT](https://img.shields.io/badge/License-MIT-blue?style=for-the-badge)](LICENSE)
[![Security Hardened](https://img.shields.io/badge/Security-OWASP%20Hardened-F59E0B?style=for-the-badge&logo=security&logoColor=white)](SECURITY.md)

<p align="center">
  <a href="#-key-features">Key Features</a> •
  <a href="#-all-in-one-pdf-suite--editor">PDF Suite</a> •
  <a href="#-architecture--tech-stack">Tech Stack</a> •
  <a href="#-installation--setup">Quick Start</a> •
  <a href="#-rest-api-reference">REST API</a> •
  <a href="#-security-hardening-osi-layers">Security & OSI</a> •
  <a href="#-contributing">Contributing</a>
</p>

</div>

---

## 📌 Overview

**DigiSign Pro** is an open-source, production-ready Electronic Signature (*Tanda Tangan Elektronik / TTE*) & **All-in-One PDF Suite & Editor** designed for organizations, enterprises, and developers who need complete ownership over their documents, cryptographic verification trails, and zero-server-load PDF manipulation.

Built with **Laravel 13** and **Inertia.js + React 19**, DigiSign Pro provides seamless drag-and-drop QR placement, bulk document processing, password-protected PDF encryption, cloud object storage synchronization, client-side PDF editing tools (Bento Grid Hub), and a developer-friendly REST API.

---

## 🛠️ All-in-One PDF Suite & Editor (Zero-Server Storage)

DigiSign Pro features a modular **Bento Grid Tool Hub** powered by an in-browser in-memory client-side engine (`pdf-lib`), guaranteeing **100% privacy** with zero disk storage and zero S3 bandwidth costs:

* 📑 **Merge PDF** (`/tools/merge`): Combine multiple PDF documents with drag-and-drop file ordering.
* ✂️ **Split PDF** (`/tools/split`): Extract custom page ranges (`1-3, 5`) or split all pages into separate files.
* 🔄 **Organize & Rotate** (`/tools/organize`): Visual page grid preview with 90°/180°/270° rotation, page deletion, and reordering.
* 🖼️ **Image to PDF** (`/tools/image-to-pdf`): Convert JPG, PNG, and WebP images to standardized PDF with custom paper sizes (A4, Letter, Fit) and orientations.
* 💧 **Watermark PDF** (`/tools/watermark`): Stamp diagonal/horizontal text or logos with opacity, rotation angle, and color controls.
* 🔢 **Page Numbering** (`/tools/page-number`): Automatically insert header/footer page numbering in various formats (`Page X of Y`, `- X -`).
* 🔒 **Protect & Encrypt** (`/tools/protect`): Encrypt PDFs with custom security passphrases.

---

## ✨ Key Features

* **📄 Interactive Single PDF Signer**:
  * Real-time PDF preview powered by PDF.js with visual drag-and-drop QR stamp positioning.
  * Automated coordinate translation from CSS visual pixels to PDF points and millimeters (mm).
  * Customizable stamp captions (Signer Name, Position, Verification ID, Timestamp).
  * AES PDF encryption with mandatory passphrase password protection.

* **📦 Bulk Batch PDF Signing**:
  * Upload ZIP archives containing multiple PDF files.
  * Single-placement coordinate propagation across all documents in the batch.
  * Instant batch packaging with signed ZIP download.

* **🏷️ Standalone TTE QR (Manual Signatures)**:
  * Generate high-resolution QR verification codes for physical or external documents without uploading source PDFs.
  * Optional follow-up encrypted PDF uploads.

* **🔍 Public QR Verification Portal**:
  * Dual-mode URL resolution: supports modern path routes (`/verify/{code}`) and legacy query tokens (`/verify/?token={code}`).
  * Instant validity check, document metadata lookup, and tamper detection.

* **☁️ Multi-Driver Object Storage**:
  * Hybrid storage support: Local Server Storage, Amazon S3, Cloudflare R2, MinIO, or Dual Storage (*Local + Cloud*).
  * Integrated Cloud Storage Explorer with real-time bucket statistics and file management.

* **⚡ Developer REST API (v1)**:
  * Per-user API key authentication (`Bearer` or `X-API-Key` header).
  * Complete programmatic signing, verification, and history management endpoints.
  * Machine-readable documentation endpoint at `/api/v1/docs/quickapi.md`.

* **👥 Comprehensive Admin Panel**:
  * Role-Based Access Control (RBAC) with User and Administrator tiers.
  * User management, custom TTE initials prefix (`signature_prefix`), and profile customization.
  * System branding, logo uploads, file upload size quotas, and database/media backup & restore console.

---

## 🏛️ Architecture & Tech Stack

```
+-------------------------------------------------------------------------------+
|                             Client / Frontend                                 |
|          React 19 • Inertia.js 2.0 • Tailwind CSS • SweetAlert2 • PDF.js      |
+---------------------------------------+---------------------------------------+
                                        | (Inertia Wire Protocol / JSON API)
+---------------------------------------v---------------------------------------+
|                             Laravel 13 Backend                                |
|  - SecurityHeadersMiddleware (HSTS, CSP, X-Frame-Options, Nosniff)            |
|  - Multi-tier Rate Limiters (Auth, Verify, Heavy Signing, REST API)           |
|  - FPDI Protection / FPDF / TCPDF / Chillerlan QR Code Engine                 |
|  - Dynamic Flysystem S3 & Cloudflare R2 Runtime Adapter                       |
+---------------------------------------+---------------------------------------+
                                        |
                 +----------------------+----------------------+
                 |                                             |
+----------------v------------------+         +----------------v----------------+
|     MySQL / MariaDB Storage       |         |   Cloud Object Storage (S3/R2)  |
|  Users, Signatures, App Settings  |         | Encrypted PDFs, ZIPs, Signatures|
+-----------------------------------+         +---------------------------------+
```

### Core Technologies:
* **Backend Framework**: [Laravel 13](https://laravel.com)
* **Frontend UI**: [React 19](https://react.dev) with [Inertia.js v2](https://inertiajs.com) & [Tailwind CSS v3](https://tailwindcss.com)
* **PDF Manipulation**: [FPDI](https://www.setasign.com/products/fpdi/about/) with [FPDI Protection](https://www.setasign.com/products/fpdi-protection/about/) & [TCPDF](https://tcpdf.org/)
* **QR Engine**: [Chillerlan PHP QRCode](https://github.com/chillerlan/php-qrcode)
* **Storage Abstraction**: [League Flysystem AWS S3 v3](https://flysystem.thephpleague.com/docs/adapter/aws-s3-v3/)
* **Testing & QA**: [Pest PHP v4](https://pestphp.com/) & [Laravel Pint](https://laravel.com/docs/pint)

---

## 🚀 Installation & Setup

### System Prerequisites
* **PHP**: `>= 8.3` (Extensions: `pdo_mysql`, `gd`, `zip`, `fileinfo`, `curl`, `mbstring`, `openssl`)
* **Composer**: `>= 2.2`
* **Node.js**: `>= 18.0` & **npm**
* **Database**: MySQL `>= 8.0` or MariaDB `>= 10.4`

---

### Step-by-Step Local Installation

1. **Clone Repository**:
   ```bash
   git clone https://github.com/ardianryan/DigiSignPro-SelfPDFQRSign.git
   cd DigiSignPro-SelfPDFQRSign
   ```

2. **Install PHP Dependencies**:
   ```bash
   composer install
   ```

3. **Install Frontend Dependencies**:
   ```bash
   npm install --legacy-peer-deps
   ```

4. **Configure Environment File**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *Edit `.env` and provide your database credentials (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).*

5. **Run Migrations & Seeders**:
   ```bash
   php artisan migrate --seed
   ```

   **Default Seeded Credentials**:
   | Role | Email | Password |
   |:---|:---|:---|
   | **Administrator** | `admin@example.com` | `password` |
   | **User Staff** | `user@example.com` | `password` |

6. **Create Public Storage Symlink**:
   ```bash
   php artisan storage:link
   ```

7. **Start Development Servers**:
   ```bash
   # Terminal 1: Backend PHP Server
   php artisan serve

   # Terminal 2: Frontend Vite HMR
   npm run dev
   ```
   Access application at: **`http://127.0.0.1:8000`**

---

### 🌐 Production Deployment Guide

1. **Build Production Assets**:
   ```bash
   npm ci
   npm run build
   ```

2. **Set Correct Server Permissions**:
   ```bash
   chmod -R 775 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   ```

3. **Optimize Laravel for Production**:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

---

## 🔒 Security Hardening (OSI Layer Model)

DigiSign Pro adheres to defense-in-depth principles across the OSI model:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ Layer 7 (Application)                                                       │
│ • OWASP Security Headers (X-Frame-Options: SAMEORIGIN, Nosniff, Reflected) │
│ • Magic-Byte Inspection (%PDF-, PK\x03\x04) & Zip Slip Traversal Guard     │
│ • Tiered Rate Limiting (Auth: 5/min, Verify: 30/min, Sign: 15/min)          │
│ • CSRF Protection & PDO Parameterized Prepared Statements                   │
├─────────────────────────────────────────────────────────────────────────────┤
│ Layer 6 (Presentation)                                                      │
│ • HSTS (Strict-Transport-Security: max-age=31536000; preload)              │
│ • AES-256 Cloud Object Encryption & PDF Passphrase Key Encapsulation        │
├─────────────────────────────────────────────────────────────────────────────┤
│ Layer 5 (Session)                                                           │
│ • Cookie Flags: HttpOnly=true, SameSite=Lax, Secure=auto                    │
│ • Automatic Session Regeneration on Login & Role Elevation                  │
├─────────────────────────────────────────────────────────────────────────────┤
│ Layer 4 (Transport)                                                         │
│ • TrustedProxy Header Resolution (Cloudflare / Reverse Proxy IP verification│
│ • Request Payload Caps & Timeout Boundaries                                 │
└─────────────────────────────────────────────────────────────────────────────┘
```

For vulnerability reporting procedures and response SLAs, please read our [**Security Policy (SECURITY.md)**](SECURITY.md).

---

## 🔌 REST API Reference

All REST endpoints reside under the `/api/v1` prefix.

### Authentication
Authenticate requests using your personal API key (generated in **Profil Saya**):
```http
Authorization: Bearer YOUR_API_KEY
# OR
X-API-Key: YOUR_API_KEY
```

### Key Endpoints:
| Method | Endpoint | Access | Description |
|:---|:---|:---|:---|
| `GET` | `/api/v1/health` | Public | Service health & database connectivity check |
| `GET` | `/api/v1/verify/{code}` | Public | Public cryptographic signature verification |
| `GET` | `/api/v1/docs/quickapi.md` | Public | Quick API cheatsheet for developers & AI agents |
| `GET` | `/api/v1/me` | Authenticated | Current user profile & signature prefix info |
| `GET` | `/api/v1/signatures` | Authenticated | Paginated signature history |
| `GET` | `/api/v1/signatures/{id}` | Authenticated | Specific signature details |
| `DELETE` | `/api/v1/signatures/{id}` | Authenticated | Delete signature record & physical files |
| `POST` | `/api/v1/sign/single` | Authenticated | Sign single PDF document (`multipart/form-data`) |
| `POST` | `/api/v1/sign/qr-manual` | Authenticated | Generate manual TTE QR code record |

---

## 🧪 Testing & Code Quality

DigiSign Pro comes with a comprehensive automated test suite powered by [Pest PHP](https://pestphp.com/):

```bash
# Execute full automated test suite
php artisan test

# Verify PSR-12 code style compliance
./vendor/bin/pint --test
```

### Test Suite Metrics:
* **63 Automated Tests** (Feature, Unit, Security, & Integration Tests)
* **342 Assertions**
* **100% Pass Rate**

---

## 🤝 Contributing & Community

We warmly welcome contributions from the open-source community!

* **Code of Conduct**: Please read our [Code of Conduct (CODE_OF_CONDUCT.md)](CODE_OF_CONDUCT.md).
* **Contribution Guidelines**: Review [CONTRIBUTING.md](CONTRIBUTING.md) for branch workflows, conventional commits, and coding standards.
* **Bug Reports & Features**: Use our structured [Issue Templates](.github/ISSUE_TEMPLATE/).

---

## 📄 License

DigiSign Pro is open-source software licensed under the [**MIT License**](LICENSE).

---

<div align="center">
  <sub>Developed & Maintained with ❤️ by <b>Ardian Ryan</b> and the open-source community.</sub>
</div>
