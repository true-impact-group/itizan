# Deployment, operations and rollback

## Requirements
PHP ≥ 8.3 (tested on 8.5) with `pdo_pgsql, mbstring, openssl, intl, fileinfo, gd/zip`; PostgreSQL ≥ 14 (tested on 18); Node 20+ (build only); a web server pointing at `public/` (nginx/Apache/IIS) with HTTPS.
No Redis is needed: sessions, cache and queue run on PostgreSQL.

## First deployment
```bash
git clone … && cd itizan
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
#  edit .env → see "Production settings" below
powershell scripts/setup-database.ps1        # Windows: creates role + databases and writes DB_* (or create them with psql)
php artisan migrate --force
php artisan db:seed --force                  # packages, policy pages, FAQ, supplements (idempotent, never overwrites edits)
php artisan itizan:create-admin "Full Name" 0551234567 --email=admin@example.com   # asks for the password
npm ci && npm run build
php artisan storage:link --no-interaction    # not needed for uploads (private), only if you add public files
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
```
Never run `DemoSeeder` in production (it refuses to).

### Production settings (`.env`)
```
APP_ENV=production   APP_DEBUG=false   APP_URL=https://your-domain
TRUSTED_PROXIES=<load balancer ip or *>
LOG_STACK=daily,stderr   LOG_LEVEL=info   LOG_STDERR_FORMATTER=Monolog\Formatter\JsonFormatter
SESSION_SECURE_COOKIE=true   SESSION_ENCRYPT=true
DB_SSLMODE=require            (when PostgreSQL is remote)
MAIL_MAILER=smtp …            PAYMENT_DRIVER=<your real driver>   PAYMENT_WEBHOOK_SECRET=<long random>
PAYMENT_TABBY_ENABLED / PAYMENT_TAMARA_ENABLED=true only after merchant approval
FTMS_JOIN_URL=https://<ftms>/join/{reference}    COMPANY_NAME / COMPANY_VAT_NUMBER
```
`APP_KEY` encrypts health data at rest: **back it up separately and never rotate it without a re-encryption plan** — losing it makes encrypted fields unreadable.

## Queue worker and scheduler
Notifications (mail + in-app) are queued; reminders are produced by the scheduler.

**Scheduler** — one cron entry (or Windows Task Scheduler every minute):
```
* * * * * cd /var/www/itizan && php artisan schedule:run >> /dev/null 2>&1
```
It runs `itizan:dose-reminders` and `itizan:appointment-reminders` (every 5 min), `itizan:process-subscriptions`, `itizan:lapsed-prescriptions` and `itizan:stale-appointments` (hourly), and prunes failed jobs.

**Worker** — keep it alive with systemd (Linux):
```ini
# /etc/systemd/system/itizan-worker.service
[Unit]
Description=Itizan queue worker
After=network.target postgresql.service
[Service]
User=www-data
WorkingDirectory=/var/www/itizan
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --backoff=30 --max-time=3600
Restart=always
RestartSec=5
[Install]
WantedBy=multi-user.target
```
`systemctl enable --now itizan-worker`. On Windows use NSSM or a scheduled task running the same command. After each deploy run `php artisan queue:restart` so workers load the new code. Inspect failures with `php artisan queue:failed`; retry with `queue:retry all`.

## Health checks and logging
- `GET /up` (liveness) and `GET /health` (readiness: database + cache; 503 when degraded, no internals in the body).
- Every log line and response carries `request_id` (header `X-Request-Id`); the 500 page shows it so support can find the trace. With the JSON formatter each line is one structured event for your log collector. Failed logins, payment/webhook problems and health failures are logged without PII.

## Backups
- Database: `scripts/backup-database.ps1` (Windows) or `scripts/backup-database.sh` (Linux) — `pg_dump -Fc` + uploads archive, 14-day retention. Schedule daily and **copy off-machine**. Test a restore monthly.
- Restore: `createdb itizan_restore && pg_restore -d itizan_restore --no-owner itizan-<stamp>.dump`, inspect, then swap or `pg_restore --clean --if-exists -d itizan …` in a maintenance window (`php artisan down`).
- Also back up: `.env` (secrets, `APP_KEY`) and `storage/app/private` (lab results, symptom photos).

## Release and rollback
1. `php artisan down --retry=60` (optional for schema changes)
2. Take a fresh backup (`scripts/backup-database.*`).
3. Deploy code, `composer install --no-dev -o`, `npm ci && npm run build`, `php artisan migrate --force`, re-cache config/routes/views, `php artisan queue:restart`, `php artisan up`.
4. Smoke check: `/health`, sign in, open a page of each role.

**Rollback**
- Code only (no migration in the release): redeploy the previous tag, re-cache, `queue:restart`.
- With a migration: `php artisan migrate:rollback --step=<n>` runs each migration's `down()` (all migrations are reversible), then redeploy the previous code. If data was transformed, restore the pre-release backup instead. Migrations are additive by convention so the previous code keeps working against the new schema during a rolling deploy.
- Payments: a rollback never touches already captured payments; reconcile with the provider's dashboard.

## Payments go-live checklist
1. Implement `App\Services\Payments\PaymentGateway` for your provider (create charge → checkout URL, verify webhook signature, refund) and register it in `AppServiceProvider::register()` under a new `PAYMENT_DRIVER` value.
2. Point the provider's webhook to `POST /webhooks/payments` and set `PAYMENT_WEBHOOK_SECRET`.
3. Automatic renewal consent and reminders are implemented; **charging a saved card automatically needs provider tokenisation** (not included).
4. Replace the receipt with a ZATCA-compliant tax invoice if required.
