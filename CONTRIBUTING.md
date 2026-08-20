# Contributing to DigiSign Pro

Thank you for your interest in contributing to **DigiSign Pro**! We welcome all contributions from bug reports and documentation enhancements to new features and security improvements.

Please take a moment to review this document to ensure a smooth contribution process.

---

## 1. Code of Conduct

By participating in this project, you agree to abide by our [Code of Conduct](CODE_OF_CONDUCT.md). Please report any unacceptable behavior to **security@ppti.me**.

---

## 2. Getting Started & Development Setup

### Prerequisites
* PHP 8.3 or higher with extensions: `pdo_mysql`, `gd`, `zip`, `fileinfo`, `curl`, `mbstring`
* Composer 2.x
* Node.js 18+ and npm
* MySQL / MariaDB 10.4+

### Local Setup
1. **Fork and clone the repository**:
   ```bash
   git clone https://github.com/YOUR_USERNAME/DigiSignPro-SelfPDFQRSign.git
   cd DigiSignPro-SelfPDFQRSign
   ```

2. **Install PHP and Node dependencies**:
   ```bash
   composer install
   npm install
   ```

3. **Environment Setup**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *Configure your `DB_*` database credentials inside `.env`.*

4. **Run Database Migrations & Seeders**:
   ```bash
   php artisan migrate --seed
   ```

5. **Start Development Servers**:
   ```bash
   # Terminal 1: Laravel Backend
   php artisan serve

   # Terminal 2: Vite Frontend HMR
   npm run dev
   ```

---

## 3. Development Standards & Conventions

### Coding Standards
* **PHP**: Follow **PSR-12** formatting and Laravel best practices.
  * You can format your code using Laravel Pint:
    ```bash
    ./vendor/bin/pint
    ```
* **JavaScript / React**: Follow standard JSX formatting (functional components with React hooks, Tailwind CSS for styling).

### Commit Messages (Conventional Commits)
We use the [Conventional Commits](https://www.conventionalcommits.org/) specification:
* `feat:` A new feature
* `fix:` A bug fix
* `docs:` Documentation only changes
* `style:` Code style/formatting changes that do not affect logic
* `refactor:` Code refactoring that neither fixes a bug nor adds a feature
* `perf:` Performance improvements
* `test:` Adding or updating tests
* `chore:` Build process, tooling, or dependency updates

*Example:* `feat(sign): add custom watermark placement to PDF signer`

---

## 4. Testing Your Changes

Before submitting a Pull Request, make sure all tests pass without errors:

```bash
# Run Pest test suite
php artisan test

# Run Pint linter
./vendor/bin/pint --test
```

When introducing new features or bug fixes, please write corresponding tests in `tests/Feature` or `tests/Unit`.

---

## 5. Pull Request (PR) Workflow

1. **Create a topic branch**:
   ```bash
   git checkout -b feat/your-feature-name
   ```
2. **Make and commit your changes** with descriptive commit messages.
3. **Push to your fork**:
   ```bash
   git push origin feat/your-feature-name
   ```
4. **Open a Pull Request** against the `main` branch of the official repository.
5. Fill out the PR template completely. Maintainers will review your PR and provide feedback.

---

## 6. Security Disclosures

If you discover a security vulnerability, please **DO NOT** open a public issue. Follow the instructions in our [Security Policy (SECURITY.md)](SECURITY.md).

---

Thank you for helping make DigiSign Pro better for everyone!
