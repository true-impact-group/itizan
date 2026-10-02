# اتزان — Itizan

Medically supervised weight-management platform: free consultation → medical assessment → plan → follow-up → medication orders and delivery → subscription. Arabic-first (RTL) with English.

**Stack:** Laravel 13 · PHP 8.3+ (tested on 8.5) · PostgreSQL · Blade · Tailwind CSS 4 (Vite) · self-hosted IBM Plex Sans Arabic · no Redis (PostgreSQL runs sessions, cache and queue).

Docs: [features and acceptance criteria](docs/FEATURES.md) · [deployment, operations, backups, rollback](docs/DEPLOYMENT.md) · [design direction](docs/DESIGN.md)

## Quick start (local)
```powershell
composer install ; npm install
copy .env.example .env ; php artisan key:generate
powershell -ExecutionPolicy Bypass -File scripts/setup-database.ps1   # role "itizan" + DBs itizan / itizan_test, writes DB_* into .env
php artisan migrate --seed                  # packages, policy pages, FAQ, supplements
php artisan db:seed --class=DemoSeeder      # optional, local only: one account per role
npm run build                               # or: npm run dev
php artisan serve                           # http://localhost:8000
php artisan queue:work                      # notifications (or QUEUE_CONNECTION=sync while developing)
```
Demo accounts (password `password1`): admin `0500000001`, support `…02`, care team `…03`, pharmacy `…04`, delivery `…05`, dietitian `…06`, doctors `…07` / `…08`, client `…10` (prefix `+966`).
Production: create the first administrator with `php artisan itizan:create-admin "Name" 0551234567` (asks for a password).

## Tests
```powershell
php artisan test                      # 500+ tests on PostgreSQL (database itizan_test)
composer lint                         # Pint
```
Coverage needs a driver (Xdebug or PCOV): `PAO_DISABLE=1 php -d extension=php_pcov.dll -d pcov.enabled=1 -d pcov.directory=app vendor/phpunit/phpunit/phpunit --coverage-text`
(`PAO_DISABLE=1` turns off the JSON output of `laravel/pao`.) Current line coverage ≈ 95 %. In CI set `DB_HOST/DB_USERNAME/DB_PASSWORD`; `phpunit.xml` forces the `itizan_test` database.

## Architecture in one page
```
app/Actions/<Domain>/     business rules, one class per use case (BookAppointment, LogDose, QuoteOrder, StartCheckout …)
app/Services/             read-side logic (SlotFinder, ClientJourney, ReportService, payment gateway contract)
app/Policies/             object-level authorisation (every protected action)
app/Http/Controllers/     thin: validate (Form Requests) → authorise → call an Action → respond
app/Http/Middleware/      locale, request id, role (runs before model binding), active account, security headers
app/Enums/                statuses/roles with Arabic labels (English via lang/en.json)
app/Console/Commands/     scheduled reminders and subscription processing, create-admin
config/itizan.php         business constants: WhatsApp, VAT, booking, delivery, safety questions, payments
```
- **Roles:** client, doctor, dietitian, care_team, pharmacy, delivery, support, admin. Medical content is reachable only by doctor / assigned dietitian / care team; admin and support manage accounts and money, not clinical records.
- **Money** is stored in integer halalas; subscriptions snapshot price and follow-up quota at purchase.
- **Database safety:** partial unique indexes (one free consultation, no double-booking, one open assessment, one active plan), CHECK constraints, row locks in transactions, soft deletes (append-only ledgers — audit log, consents, order events — are intentionally not deletable).
- **Strict models** outside production: lazy loading, mass-assignment and missing attributes throw, so N+1 and unsafe writes fail tests.
- **Translations:** write Arabic in `__('…')`; add the English to `lang/en.json`. A test fails if any Arabic string lacks a translation.

## What is intentionally not included
See “Known limitations” in the delivery notes: real payment provider, automatic card renewal charging, SMS/OTP, ZATCA e-invoice, FTMS API integration (links only), discount codes, real photography.
