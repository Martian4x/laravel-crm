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
| `APP_URL` | `https://customers.azimio.co.tz` | Used in emails/links |
| `APP_TIMEZONE` | `Africa/Dar_es_Salaam` | |
| `APP_LOCALE` | `en` or `sw` | Platform fallback locale |
| `APP_CURRENCY` | `TZS` | Renders as "TSh 1,500,000" |
| `FILESYSTEM_DISK` | `public` | Logo/config uploads must be web-servable |
| `QUEUE_CONNECTION` | `database` or `redis` | Queued mail/imports carry tenant context |
| `SESSION_DRIVER` | `database` | Already migrated (`sessions` table) |

## First install (VPS / generic)

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

## Deploying to cPanel — customers.azimio.co.tz (step by step)

Assumes a cPanel account on `azimio.co.tz` hosting with SSH/Terminal access.
Frontend assets are **already committed** to the repo (`public/admin/build`,
`public/installer/build`), so **no Node.js is needed on the server** — but if
you change any JS/CSS, rebuild locally and commit before deploying.

### 1. Check server requirements

1. cPanel → **Select PHP Version** (or *MultiPHP Manager*): set **PHP 8.2**
   for the account/subdomain.
2. In the same screen, enable extensions: `intl`, `imap`, `gd`, `mbstring`,
   `pdo_mysql`, `zip`, `fileinfo`, `openssl`, `curl`.
3. cPanel → **MultiPHP INI Editor** → the subdomain: set
   `memory_limit = 256M`, `max_execution_time = 120`,
   `upload_max_filesize = 20M`, `post_max_size = 25M`.
4. Confirm in **Terminal**: `php -v` shows 8.2+ and
   `php -m | grep -E "intl|imap|gd"` lists all three. If the CLI shows a
   different PHP than the site, use the versioned binary (e.g.
   `/opt/cpanel/ea-php82/root/usr/bin/php`) in every command and cron below.

### 2. Create the subdomain

1. cPanel → **Domains** (or *Subdomains*) → Create: subdomain
   `customers` on `azimio.co.tz`.
2. Set the **Document Root** to `/home/USER/apps/azimiocustomers/public`
   (you'll create the app there next — the docroot must be the app's
   `public/` folder, never the app root, or `.env` becomes downloadable).
3. cPanel → **SSL/TLS Status**: run **AutoSSL** for
   `customers.azimio.co.tz` so HTTPS works before go-live.

### 3. Create the database

1. cPanel → **MySQL Databases**:
   - Create database, e.g. `USER_azimiocrm`.
   - Create user, e.g. `USER_azimio` with a strong password.
   - Add the user to the database with **ALL PRIVILEGES**.
2. Note all three values for `.env`.

### 4. Upload the code

Option A — **Git (recommended)**: cPanel → **Git Version Control** →
Create: clone URL of your private repo, repository path
`/home/USER/apps/azimiocustomers`, branch `azimiocustomers-dev` (or your
release branch/tag). Deploy updates later with *Pull or Deploy*.

Option B — **Zip upload**: locally run `git archive -o release.zip HEAD`,
upload via **File Manager** into `/home/USER/apps/azimiocustomers`, extract.

### 5. Install and configure (cPanel Terminal)

```bash
cd ~/apps/azimiocustomers

# Composer (install it locally if the host has none)
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php && rm composer-setup.php
php composer.phar install --no-dev --optimize-autoloader

cp .env.example .env
nano .env
```

Set in `.env`:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://customers.azimio.co.tz
APP_TIMEZONE=Africa/Dar_es_Salaam
APP_CURRENCY=TZS
DB_HOST=localhost
DB_DATABASE=USER_azimiocrm
DB_USERNAME=USER_azimio
DB_PASSWORD=********
FILESYSTEM_DISK=public
QUEUE_CONNECTION=database
SESSION_DRIVER=database
MAIL_MAILER=smtp          # use the cPanel mailbox or a transactional service
MAIL_HOST=mail.azimio.co.tz
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=noreply@azimio.co.tz
MAIL_PASSWORD=********
MAIL_FROM_ADDRESS=noreply@azimio.co.tz
```

Then:

```bash
php artisan key:generate
php artisan migrate --seed          # tables + platform defaults + super admin
php artisan storage:link            # if symlinks are disabled by the host, see note
php artisan config:cache && php artisan route:cache && php artisan view:cache
chmod -R ug+rwx storage bootstrap/cache
```

> **Symlink note:** if `storage:link` fails (some shared hosts block
> `symlink()`), ask support to enable it, or create the link in Terminal:
> `ln -s ~/apps/azimiocustomers/storage/app/public ~/apps/azimiocustomers/public/storage`.

### 6. Cron jobs (cPanel → Cron Jobs)

Shared hosting cannot run a persistent queue worker; run it from cron and
let it exit when the queue is empty. Add:

| Schedule | Command |
|---|---|
| `* * * * *` | `cd /home/USER/apps/azimiocustomers && php artisan schedule:run >> /dev/null 2>&1` |
| `* * * * *` | `cd /home/USER/apps/azimiocustomers && php artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1` |
| `*/5 * * * *` | `cd /home/USER/apps/azimiocustomers && php artisan inbound-emails:process >> /dev/null 2>&1` |

(Use the full versioned PHP path from step 1.4 if plain `php` is not 8.2.)

### 7. Go live

1. Visit `https://customers.azimio.co.tz` → you should see the login page
   with AzimioCustomers branding; `/register` shows the public
   company-registration page.
2. Log in as the seeded super admin → you land on `/admin/super/tenants`.
   **Immediately change the super admin email + password** (My Account).
3. Create the first real tenant from the panel, impersonate it, and run
   the Post-deploy checklist at the bottom of this document.
4. cPanel → **Backup Configuration**: confirm daily backups are on, and
   set up the nightly `mysqldump` cron from the Backups section below.

### 8. Updating a cPanel deployment

```bash
cd ~/apps/azimiocustomers
php artisan down
# Git Version Control -> Pull or Deploy   (or upload/extract the new zip)
php composer.phar install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

### cPanel troubleshooting

- **500 after deploy** → almost always cache built with wrong paths: run
  `php artisan optimize:clear`, then re-cache.
- **White page / old logo** → the browser cached hashed assets; hard-refresh.
  If assets 404, confirm the committed `public/*/build` folders uploaded.
- **Uploads 404** → the `public/storage` symlink is missing (step 5 note).
- **Mail not sending** → many hosts force SMTP through their own mail
  server; verify the `mail.azimio.co.tz` credentials with cPanel webmail
  first, and check SPF includes the sending host.
- **Queue mail never arrives** → confirm the second cron above exists;
  `php artisan queue:monitor database` shows pending jobs.

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
