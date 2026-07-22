# AzimioCustomers — Deployment Runbook

Multi-tenant CRM for the Tanzanian market. One installation serves all tenants
on a single domain. See [AZIMIO_TODO.md](AZIMIO_TODO.md) for feature status.

## Requirements

- PHP 8.2+ with `intl`, `imap`, `gd` extensions; Composer 2
- MySQL 8 / MariaDB 10.4+
- Node.js 18+ (build only)
- A queue driver (database works; Redis recommended at scale)

## Environment (`.env`)

| Variable | Production value | Why |
|---|---|---|
| `APP_NAME` | `AzimioCustomers` | Platform display name |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | Never expose debug traces |
| `APP_URL` | `https://crm.azimio.co.tz` (your host) | Used in emails/links |
| `APP_TIMEZONE` | `Africa/Dar_es_Salaam` | |
| `APP_LOCALE` | `en` or `sw` | Platform fallback locale |
| `APP_CURRENCY` | `TZS` | Renders as "TSh 1,500,000" |
| `FILESYSTEM_DISK` | `public` | Logo/config uploads must be web-servable |
| `QUEUE_CONNECTION` | `database` or `redis` | Queued mail/imports carry tenant context |
| `SESSION_DRIVER` | `database` | Already migrated (`sessions` table) |

## First install

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate   # then edit .env
php artisan migrate --seed        # seeds platform defaults + super admin
php artisan storage:link
npm install && npm run build      # root, packages/Webkul/Admin, packages/Webkul/Installer
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

The seeded user (`users` id 1) is the **platform super admin** (no tenant).
Change its email/password immediately after install.

## Tenant management

- Create: `php artisan azimio:tenant:create "Company Ltd" admin@company.co.tz --locale=sw`
  or via the panel at `/admin/super/tenants` (super admin only; also
  suspend/activate and "login as" impersonation).
- Migrating a pre-tenancy database: create the tenant, then
  `php artisan azimio:tenant:adopt <id|slug> --dry-run` (review) and run
  without `--dry-run` to assign all orphan data to it.
- Plan limits: set `max_users` on the tenant row; user creation is blocked
  beyond it.

## Required processes

```bash
# Queue worker (systemd/supervisor) — tenant context is restored per job
php artisan queue:work --tries=3 --max-time=3600

# Scheduler (cron, every minute)
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1

# Per-tenant IMAP inbound mail (cron or scheduler entry, e.g. every 5 min)
php artisan inbound-emails:process
```

## Backups & data residency

- **Nightly `mysqldump`** of the single database + `storage/app/public`
  (tenant uploads). Keep at least 14 daily snapshots off-server.
- All tenants share one database; a restore restores everyone. Per-tenant
  export can be produced with `mysqldump --where="tenant_id=<id>"` per table
  if a tenant requests their data.
- **Data residency**: document your hosting location for procurement
  (e.g. "data is hosted in <region> with nightly encrypted backups
  retained 14 days"). TZ organizations commonly ask; keep the statement in
  your contract template.

## Upgrading (pulling upstream Krayin fixes)

1. `git fetch upstream && git checkout 2.1 && git merge upstream/2.1` —
   the `2.1` branch tracks upstream and carries no Azimio changes.
2. `git checkout azimiocustomers-dev && git merge 2.1`.
3. Conflict hotspots (ours is authoritative): `config/app.php` (locales,
   currency, providers), installer lang/blades (rebrand), `Core.php` (TZS
   formatting), `AdminServiceProvider` (middleware order), the models
   carrying `BelongsToTenant`, and the blades touched for branding/phone
   links. Everything under `packages/Azimio/` is ours alone and never
   conflicts.
4. If upstream added **new tables**: decide strict vs shared, add the table
   to `Azimio\Tenancy\TenantTables`, a `tenant_id` migration, and the trait
   to the new model. If upstream added **new DataGrids**, they are covered
   automatically by the wildcard listener.
5. Run `./vendor/bin/pest` — the isolation suite is the tenancy safety net.

## Post-deploy checklist

- [ ] `php artisan azimio-crm:version` matches the release
- [ ] Log in as super admin → `/admin/super/tenants` loads
- [ ] Log in as a tenant admin → sees only tenant data, Swahili if configured
- [ ] Create a lead in tenant A, confirm invisible in tenant B
- [ ] Quote PDF shows the tenant's company info (TIN/VRN)
- [ ] Queued mail carries tenant branding (send a test workflow mail)
