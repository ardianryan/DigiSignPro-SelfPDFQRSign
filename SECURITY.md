# Security Policy

**DigiSign Pro** takes security seriously. As a self-hosted Electronic Signature & PDF QR Verification platform handling sensitive documents and signatures, we are committed to maintaining a robust security posture across all layers of our architecture.

---

## 1. Supported Versions

We provide security updates and patches for the following versions:

| Version | Supported          | Maintenance Level |
|:-------:|:------------------:|:------------------|
| 2.x (Laravel 13) | :white_check_mark: | Active Development & Security Fixes |
| 1.x (Legacy PHP) | :x:                | Deprecated (Please migrate to 2.x)  |

---

## 2. Reporting a Vulnerability (Coordinated Vulnerability Disclosure)

**Please DO NOT report security vulnerabilities through public GitHub issues, discussions, or social media.**

To report a vulnerability, please follow our Coordinated Disclosure process:

1. **GitHub Private Vulnerability Reporting (Preferred)**:
   Navigate to the [Security Tab](https://github.com/ardianryan/DigiSignPro-SelfPDFQRSign/security) of the repository and click **"Report a vulnerability"** to open a confidential advisory draft.

2. **Email Security Contact**:
   If private reporting is unavailable, email our security team at **security@ppti.me** or contact **ardianryan** directly.
   * Please include detailed steps to reproduce the issue, proof-of-concept (PoC) scripts or payloads, affected endpoints/components, and an assessment of potential impact.

### Our Response Timeline SLA
* **Initial Acknowledgment**: Within **48 hours** of receiving your report.
* **Assessment & Confirmation**: Within **5 business days**.
* **Fix & Patch Release**: Typically within **14 business days**, depending on severity.
* **Public Disclosure**: Once a fix has been tagged and released, we will credit the reporter (unless you prefer anonymity) in the release notes and advisory.

---

## 3. Multi-Layer Security Architecture (OSI Model Mapping)

DigiSign Pro enforces defense-in-depth security principles mapped across the OSI networking layers:

```
+-------------------------------------------------------------------------+
| Layer 7 - Application                                                   |
| - OWASP Security Headers (X-Frame-Options, X-Content-Type-Options)      |
| - Rate Limiting & Throttling on Auth, Sign, and Verification Endpoints  |
| - Magic-Byte Inspection (%PDF-, PK\x03\x04) & Zip Slip Traversal Guard  |
| - CSRF Token Verification & Parameter Binding (Anti-SQL Injection)      |
+-------------------------------------------------------------------------+
| Layer 6 - Presentation                                                  |
| - HSTS (Strict-Transport-Security: max-age=31536000; preload)           |
| - AES-256 S3 Storage Encryption & PDF Passphrase Password Protection    |
| - Strict UTF-8 Encoding Enforcement                                     |
+-------------------------------------------------------------------------+
| Layer 5 - Session                                                       |
| - Encrypted / Isolated Session Handlers                                 |
| - Cookie Security: HttpOnly=true, SameSite=Lax, Secure=auto             |
| - Automatic Session Regeneration on Login & Role Changes                |
+-------------------------------------------------------------------------+
| Layer 4 - Transport                                                     |
| - TrustedProxy Header Resolution (Cloudflare / Reverse Proxy IP spoofing)|
| - TCP Connection Rate Limits & Request Size Caps                        |
+-------------------------------------------------------------------------+
| Layer 3 to 1 - Infrastructure Guidance                                  |
| - Cloudflare WAF & Anti-DDoS Recommendation                             |
| - Private Database Bind (127.0.0.1) & Least-Privilege S3/R2 IAM Keys    |
+-------------------------------------------------------------------------+
```

---

## 4. Production Deployment Security Checklist

When deploying DigiSign Pro to production environments, please verify the following:

- [ ] Set `APP_ENV=production` and `APP_DEBUG=false` in `.env`.
- [ ] Generate a fresh application key with `php artisan key:generate`.
- [ ] Enforce HTTPS/TLS certificate (Let's Encrypt, Cloudflare SSL, or commercial CA).
- [ ] Ensure `storage/` and `bootstrap/cache/` have `775` permissions owned by the web server user (`www` / `nginx`).
- [ ] Disable directory browsing (`Options -Indexes` in Apache/Nginx).
- [ ] Use dedicated IAM keys with minimal S3 bucket access permissions.
- [ ] Regularly update PHP runtime, composer dependencies, and system packages.

---

Thank you for helping keep DigiSign Pro and our community secure!
