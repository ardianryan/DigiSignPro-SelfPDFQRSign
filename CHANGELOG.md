# Changelog

All notable changes to the **DigiSign Pro** project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [2.1.0] - 2026-08-20

### 🚀 Added
- **Visual PDF Editor (`/tools/editor`) - Foxit-Grade Precision & In-Place Text Editing**:
  - **Interactive Selectable Text Layer**: Renders PDF.js text layer allowing native PDF text to be clicked, highlighted, and edited directly on the document canvas.
  - **Pixel-Exact In-Place Text Replacement**: Zero vertical or horizontal displacement with true PDF baseline and ascent matching.
  - **Auto Font & Style Detection**: Automatically identifies font family (*Times New Roman*, *Helvetica/Arial*, *Courier*, *Calibri*, etc.), font weight (*Bold*), posture (*Italic*), and font size directly from the PDF font dictionary.
  - **Enriched Font Library**: 8 built-in standard typography presets (Arial/Helvetica, Times New Roman, Courier New, Georgia, Trebuchet MS, Verdana, Calibri, Roboto).
  - **Complete Annotation Suite**: Add custom text, transparent stamp/PNG images with drag & resize, clean whiteout redaction rectangles, and freehand drawing pen.
  - **Client-Side PDF Baking**: Pure in-browser rendering and baking powered by `pdf-lib` with standard PDF embedded fonts.
- **All-in-One Bento PDF Suite**:
  - **Bento Tool Hub** (`/tools`): Modern Bento Grid catalog with real-time tool search, category filters, and privacy indicators.
  - **Merge PDF** (`/tools/merge`): Multi-file drag-and-drop ordering with zero-server client-side merging.
  - **Split PDF** (`/tools/split`): Custom page range extraction (`1-3, 5`) and individual single-page batch split.
  - **Organize & Rotate** (`/tools/organize`): Visual page grid preview supporting 90°/180°/270° angle rotation, page deletion, and sequence reordering.
  - **Image to PDF** (`/tools/image-to-pdf`): Converter for JPG, PNG, and WebP images with paper size presets (A4, Letter, Fit) and orientations.
  - **Watermark PDF** (`/tools/watermark`): Diagonal text watermark stamping with customizable font size, opacity, rotation angle, and official colors.
  - **Page Numbering** (`/tools/page-number`): Automated header/footer page number insertion with customizable numbering styles (`Page X of Y`, `- X -`).
  - **Protect & Encrypt** (`/tools/protect`): In-browser passphrase encryption.
- **Universal Drag & Drop**:
  - HTML5 reactive drag-and-drop file upload zones across all PDF tools, Single Sign, Bulk Sign, and Admin System Restore.
- **Analytics & Tool Usage Tracking**:
  - `tool_usages` database table tracking per-tool and per-user execution metrics with real-time admin counters.
- **Zero-Server Storage & Privacy-First Engine**:
  - Integrated `pdf-lib` for 100% in-browser in-memory client-side execution (Zero disk writes, Zero S3 upload, Zero server latency).
- **Multi-Layer Security Hardening (OSI Model)**:
  - OWASP Security Headers Middleware (`X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `X-XSS-Protection`, `Referrer-Policy`, `Permissions-Policy`, and `HSTS`).
  - Magic-byte verification for `%PDF-` and `PK\x03\x04` to prevent malicious shell/executable uploads.
  - Zip Slip / Directory Traversal (`../`) mitigation during ZIP file extraction.
  - Reverse proxy / Cloudflare IP detection via `trustProxies(at: '*')`.
  - Tiered rate limiters for authentication, public verification, and heavy signing operations.
- **Open-Source Governance & Community Standards**:
  - `SECURITY.md`: Coordinated Vulnerability Disclosure (CVD), SLA response timelines, and production security checklist.
  - `CODE_OF_CONDUCT.md`: Contributor Covenant v2.1 standard.
  - `CONTRIBUTING.md`: Development setup, PSR-12 code style, and Conventional Commits guidelines.
  - GitHub Issue Templates (`bug_report.yml`, `feature_request.yml`, `security_report.md`) and `PULL_REQUEST_TEMPLATE.md`.
- **Automated Test Coverage**:
  - Expanded Pest PHP test suite to **66 passing tests (355 assertions)** with 100% pass rate.

---

## [2.0.0] - 2026-07-19

### 🌟 Major Upgrade (Native PHP to Laravel + React Refactor)

### 🚀 Added
- **Modern Tech Stack**: Full port from legacy native PHP to **Laravel**, **Inertia.js**, and **React** with **Tailwind CSS**.
- **Interactive PDF Signer**:
  - Live PDF canvas preview via PDF.js with real-time coordinate translation from visual pixels to PDF millimeters.
  - Mandatory passphrase protection and encryption.
- **Bulk Batch PDF Signing**:
  - Single-stamp coordinate propagation for ZIP archives containing multiple PDF files.
- **Standalone TTE QR Code Generator**:
  - Generate standalone high-resolution QR verification codes with follow-up PDF attachment options.
- **Public Verification Portal**:
  - Cryptographic signature validity check supporting both `/verify/{code}` and legacy `/verify/?token={code}` routes.
- **Multi-Driver Cloud Object Storage**:
  - Runtime switching between Local Storage, Amazon S3, Cloudflare R2, and MinIO with built-in Cloud Storage Explorer.
- **Developer REST API v1**:
  - Programmatic endpoints under `/api/v1/` authenticated via `Bearer` or `X-API-Key` headers.
  - Machine-readable documentation endpoint at `/api/v1/docs/quickapi.md`.
- **Idempotent Legacy Database Migration**:
  - Seamless cutover and schema adapter for existing DigiSign database tables without data loss.

---

## [1.3.3] - 2026-03-22

### 🚀 Added
- S3 storage transparency and UI status indicators.
- Cumulative updater and schema migration synchronization.
- Cookie consent dialog integration.
- Sidebar menu categorization into collapsible groups.

---

## [1.3.1] - 2026-03-19

### 🚀 Added
- Amazon S3 and Cloudflare R2 object storage integration.
- Storage management and configuration panel in admin area.

---

## [1.1.2] - 2026-02-10

### 📌 Initial Release
- Initial open-source release of DigiSign PHP Native.
- Basic single PDF signing with FPDF and FPDI.
- MySQL database storage for user signatures and document records.
