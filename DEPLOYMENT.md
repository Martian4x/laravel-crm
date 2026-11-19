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

## Text Messaging (SMS)

The SMS module (`packages/Azimio/Messaging`) lets each company send either on
its **own provider account** or on the platform's **AZIMIOCRM** account using
credits you allocate. Both modes share one sending pipeline, so opt-outs,
quiet hours, segment counting and the message log apply to every send
however it was triggered.

### Before you go live

1. **Register the `AZIMIOCRM` sender ID with the mobile operators** through
   your Beem account. An unregistered sender ID is rejected on every send.
2. **Create the platform sender account**: sign in as a super admin →
   **Tenants → SMS** → *Platform sender account*. Enter the Beem API key and
   secret. Leave either field blank on a later edit to keep the stored value.
3. **Allocate credits** to a company before it can send in platform mode. A
   note is required on every movement — it is your evidence in a billing
   dispute.

### Environment

```bash
AZIMIO_SMS_DRIVER=log          # 'log' writes to the log and sends nothing — use in staging
AZIMIO_SMS_PLATFORM_SENDER=AZIMIOCRM
AZIMIO_SMS_PLATFORM_DRIVER=beem
BEEM_API_KEY=
BEEM_SECRET_KEY=
AZIMIO_SMS_WEBHOOKS=false      # inbound callbacks; see "Inbound SMS" below
AZIMIO_SMS_WEBHOOK_SECRET=
```

> **Keep `AZIMIO_SMS_DRIVER=log` in staging.** It exercises the whole
> pipeline — reservations, settlement, delivery status — without contacting a
> network or spending a single credit.

### ⚠️ `APP_KEY` is now backup-critical

Provider credentials are **encrypted at rest** in
`sms_sender_profiles.credentials` using Laravel's encrypter, which derives
its key from `APP_KEY`.

**If you lose `APP_KEY`, every stored credential becomes unrecoverable** —
not degraded, gone — and every company with its own provider will have to
re-enter its API secret. Back `APP_KEY` up separately from the database, and
never rotate it without first re-saving every sender profile.

### Required processes

The queue worker and scheduler in **Required processes** above are not
optional for SMS:

- **Queue worker** — sends are queued (`SendSmsJob`). With no worker,
  messages sit at `queued` forever and nothing is delivered.
- **Scheduler** — drives three commands the module registers itself:

| Command | Frequency | Purpose |
|---|---|---|
| `azimio:sms:poll-delivery-reports` | every 10 min | Beem has **no delivery callback**; status is polled |
| `azimio:sms:notify-low-balance` | daily 08:30 | warns companies before sends start failing |
| `azimio:sms:check-failure-rate` | hourly | alerts you when a company's sends start failing |

```bash
# Run any of them by hand:
php artisan azimio:sms:poll-delivery-reports --limit=500
php artisan azimio:sms:notify-low-balance --force
php artisan azimio:sms:check-failure-rate --hours=6 --threshold=25
```

**Mind the polling cost.** Beem's delivery-report endpoint takes one
`(request_id, dest_addr)` pair per call, so a 10,000-recipient campaign is
10,000 requests. The command only polls non-terminal messages, waits five
minutes after submission, gives up after
`messaging.delivery_reports.max_age_hours` (default 48), and caps each run
with `--limit`.

### Inbound SMS — read before enabling

Inbound/two-way SMS is a **separate Beem product** needing a two-way number,
and **Beem documents no signature or HMAC scheme for its callbacks**. A
request arriving at the inbound route therefore cannot be proven to come
from Beem.

The route is **disabled by default** (`AZIMIO_SMS_WEBHOOKS=false`). If you
enable it:

- set `AZIMIO_SMS_WEBHOOK_SECRET` and configure Beem to send it as the
  `X-Azimio-Secret` header;
- restrict the endpoint at the network or web-server level if you can;
- the URL to register with Beem is `https://<your-host>/messaging/webhook/inbound`;
- **ask Beem for a real verification method** before trusting it in
  production.

Every payload is logged to `sms_webhook_events` before it is trusted,
rejected ones included — a run of rejections is what a forged-callback
attempt looks like. Replies of `STOP`, `ACHA`, `SITAKI` or `UNSUBSCRIBE`
automatically add the sender to that company's opt-out list.

### ⚠️ Reconcile credit billing against a real Beem invoice

One credit is **one SMS segment**, because that is how Beem bills. But
**Beem does not document how it counts segments on `/v1/send`** — the
GSM-7 160/153 and UCS-2 70/67 figures this module uses come from their
*Multicountry* product, a different API.

**Before you bill anyone per segment**, send a known mix of messages (short,
long, and one containing a non-GSM character such as a curly quote or
emoji), then compare the module's `credits_charged` totals against the Beem
invoice for the same period. Adjust `messaging.segments` if they disagree.

### Credit reconciliation runbook

`sms_credit_ledger` is the truth; `tenants.sms_credits` is a cache of it.
Drift between them is normally an **abandoned reservation** — a send that
died between reserving its credits and settling them.

1. Super admin → **Tenants → SMS**. The table flags any company whose cached
   and ledger balances disagree.
2. Open that company's **Ledger** and press **Reconcile from ledger**.
3. The cache is recomputed from the ledger, which **returns the stranded
   credits to the company** — drift always resolves in their favour, which
   is the right way to fail for something they paid for.

Reconciling is safe to run at any time and changes nothing when the figures
already agree.

### Pausing all platform sending

Super admin → **Tenants → SMS** → **Pause platform sending**. This stops
every send that uses platform credits.

Companies on their **own** provider keep sending — it is their credit and
their provider account, not yours to switch off.

### Per-company settings

Each company controls its own, under **Settings → SMS**:

- provider mode (own account or platform credits), with credentials verified
  against the provider before the profile goes active;
- quiet hours and a daily send cap — blank means "use the platform default";
- its opt-out list, which is **per company**: opting out of one company's
  messages does not silence another.

### Tanzania compliance

Bulk sender IDs are subject to **TCRA and mobile-operator rules**. The
defaults shipped here (quiet hours 21:00–08:00, an unsubscribe notice on
marketing messages, a daily cap) are a sensible starting point, **not legal
advice**. Confirm your obligations with TCRA and your operator before
running large campaigns.


## Deployed on az1.azimio.co.tz (production)

The CRM runs as its own site on `az1.azimio.co.tz`, alongside the other
production sites and the **FreeRADIUS** server already on that box. Nothing
below touches those; the only shared surfaces are nginx, MySQL and cron, and
each is extended rather than modified.

| Thing | Value |
|---|---|
| Code | `/var/www/clients/{releases,current,shared}` (atomic releases) |
| Served from | `/var/www/clients/current/public` |
| System user | `clients` |
| PHP | **8.4** via `/run/php/clients.sock` (pool `/etc/php/8.4/fpm/pool.d/clients.conf`) |
| nginx | `/etc/nginx/sites-available/clients` |
| Database | `azimio_clients`, user `azimio_clients@localhost` |
| Queue worker | `systemctl status clients-queue` |
| Scheduler | root crontab, `schedule:run` every minute |
| Logs | `/var/log/nginx/clients.{access,error}.log`, `/var/log/clients-queue.log` |

### Why PHP 8.4 and not 8.5

`sabberworm/php-css-parser` — a dompdf dependency, used to render quote PDFs
— declares no support above PHP 8.4, so `composer install` refuses to run
under 8.5. `composer.json` pins `config.platform.php` to `8.4` so local, CI
and production all resolve against the same version. **Do not "fix" this by
passing `--ignore-platform-reqs`**: that installs a CSS parser that does not
support the running PHP, and quote rendering is where it would surface.

### Secrets on the box

| File | Contents |
|---|---|
| `/var/www/clients/shared/.env` | app config, `APP_KEY`, DB password (mode 640, owner `clients`) |
| `/root/.clients-db-pass` | database password (mode 600) |
| `/root/.clients-admin-pass` | initial super-admin password (mode 600) |

`APP_KEY` is **backup-critical**: SMS provider credentials are encrypted with
it, so losing it makes every stored credential unrecoverable. Back it up
separately from the database.

### Deploying

Prefer the GitHub Actions **Deploy** workflow (Actions → Deploy → Run
workflow, type `deploy` to confirm). It runs the test suite first and will
not deploy a failing build.

By hand, from a clean checkout:

```bash
git archive --format=tar HEAD | ssh az1 "cat > /tmp/rel.tar"
```

```bash
scp bin/deploy-az1.sh az1:/tmp/ && ssh az1 'bash /tmp/deploy-az1.sh /tmp/rel.tar'
```

`bin/deploy-az1.sh` builds the new release completely — composer, migrations,
caches — **before** moving the `current` symlink, then smoke-tests
`/admin/login` and **rolls back to the previous release automatically** if it
does not return 200. The last five releases are kept, so a manual rollback is
just re-pointing the symlink:

```bash
ssh az1 'ls -1dt /var/www/clients/releases/*/ | head -3'
```

### Rolling back by hand

```bash
ssh az1 'ln -sfn /var/www/clients/releases/<TIMESTAMP> /var/www/clients/current && systemctl reload php8.4-fpm && systemctl restart clients-queue'
```

### Hostnames and TLS

| Hostname | DNS | TLS | Serves |
|---|---|---|---|
| `clients.azimio.co.tz` | A → 152.239.123.46, DNS-only | `*.azimio.co.tz` wildcard | the app (canonical on azimio.co.tz) |
| `clients.co.tz` | A → 152.239.123.46, DNS-only | own cert, expires 2027-02-17 | the app |
| `www.clients.co.tz` | A → 152.239.123.46, DNS-only | own cert | 301 → `clients.co.tz` |
| `customers.azimio.co.tz` | **unchanged — still cPanel** | — | see the warning below |

DNS lives in Cloudflare, records set **DNS-only (grey cloud)**. The origin
holds valid Let's Encrypt certificates, so proxying adds nothing that is
needed; turning it on later means setting SSL mode to Full (strict) first,
or requests will loop.

Certbot renews `clients.co.tz` automatically. Adding a TLS block for a new
hostname always goes cert-first: referencing a certificate path that does
not exist makes `nginx -t` fail, and a failed reload takes **every** site on
this box down.

> ### ⚠️ `customers.azimio.co.tz` was deliberately NOT repointed
>
> That hostname already serves a **separate, live instance of this same
> CRM** on the cPanel host `65.181.113.174` — the deployment described in
> the cPanel section above. It answers with an AzimioCustomers login page
> and sets an `azimiocustomers_session` cookie.
>
> Repointing its DNS to az1 would send its users to a different database.
> Their logins would fail and their data would look deleted, while actually
> remaining on cPanel.
>
> Decide first what should happen to that instance: migrate its data to az1
> and then repoint, keep both and leave DNS alone, or retire it.

### Never reload nginx blind

nginx on this box serves nine other production sites. Always:

```bash
ssh az1 'nginx -t && systemctl reload nginx'
```

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
- [ ] `APP_KEY` is backed up **separately from the database** (encrypted SMS
      provider credentials cannot be recovered without it)
- [ ] Platform sender account saved and `AZIMIOCRM` registered with the operators
- [ ] Credits allocated to at least one company, and its ledger shows the movement
- [ ] Queue worker running — send a test SMS from Settings → SMS and confirm it
      leaves `queued`
- [ ] Scheduler running — `php artisan schedule:list` shows the three
      `azimio:sms:*` commands
- [ ] Opt-out honoured: add your own number to a company's opt-out list and
      confirm a send to it is excluded at the confirmation step
- [ ] Credit billing reconciled against a real Beem invoice (see the warning
      in the SMS section) **before charging anyone per segment**
