# AzimioCustomers — Rebrand & Multi-Tenancy TODO

Fork of Krayin CRM v2.1 (Laravel 10). Goal: rebrand to **AzimioCustomers**, add
**TZS currency**, **Swahili + English only**, and make it **multi-tenant on a single
domain/host** so multiple companies use one installation, centrally managed.

Work through the phases in order — each item is scoped to be done (and tested) on its own.

---

## Phase 0 — Groundwork

- [x] 0.1 Created `azimiocustomers-dev` working branch off `2.1`; `2.1` stays clean for
      pulling upstream fixes.
- [x] 0.2 **DECIDED: Single database, row-level tenancy** — `tenants` table + `tenant_id`
      column on every business table, enforced by a global Eloquent scope
      (`BelongsToTenant` trait, built in Phase 3). Rationale: same domain/host
      requirement, simplest ops (one DB, one queue, one backup). `stancl/tenancy`
      DB-per-tenant rejected as overkill for single-domain tenancy.
- [x] 0.3 **DECIDED: tenant identified from the logged-in user** (`users.tenant_id`,
      nullable — `NULL` + `is_super_admin = 1` means platform super admin). No URL
      change; super-admin panel will get its own guard/route prefix in Phase 3.5.
- [x] 0.4 Seedable local dev environment via new `packages/Azimio/Tenancy` package:
      `tenants` table, `tenant_id`/`is_super_admin` on users, and
      `Azimio\Tenancy\Database\Seeders\DevTenantSeeder` (2 fake tenants:
      Twiga Traders Ltd [sw] + Kilimanjaro Suppliers Co [en], each with an
      `admin@<slug>.test` / `password` user; `admin@azimio.co.tz` promoted to super admin).
      Re-run anytime: `php artisan db:seed --class="Azimio\Tenancy\Database\Seeders\DevTenantSeeder"`.

## Phase 1 — Rebrand to AzimioCustomers

- [x] 1.1 Rename sweep done: installer lang files (all 7 locales), installer view,
      console installer, on-boarding template, session views (login/forgot/reset
      "Powered by"), admin `en` lang, `core_config.php` footer default, installed-marker
      text. Email template seeders contained no brand strings. Links now point to
      https://azimio.co.tz. **Bonus:** removed the installer's call-home to
      `updates.krayincrm.com` (it reported domain + admin email/name) and the dead
      remote-logo route/controller (`ImageCacheController`).
- [x] 1.2 New AzimioCustomers wordmark SVGs (kept brand blue #0E90D9): Admin
      `logo.svg` / `dark-logo.svg` / `mobile-*-logo.svg`, installer logo (renamed
      `azimio-logo.svg`). Assets rebuilt via package-level `npm run build`.
      **Pending: `favicon.ico` (binary) still shows the Krayin icon — needs a real
      design asset; replace in `packages/Webkul/Admin/src/Resources/assets/images/`
      and `packages/Webkul/Installer/src/Resources/assets/images/`.** Ditto a proper
      designed logo to replace the placeholder wordmark when brand assets exist.
- [x] 1.3 Root `composer.json` renamed to `azimio/azimiocustomers`; `README.md`
      rewritten; `.env.example` `APP_NAME='AzimioCustomers'` (mail from-name derives
      from APP_NAME).
- [x] 1.4 Internal `Webkul\` namespaces and `krayin/*` package names kept; decision
      documented in README "Architecture notes".
- [x] 1.5 Quote PDF templates checked — no brand strings (they render org/tenant data
      only); per-tenant PDF branding lands in Phase 4.3.
- [x] 1.6 `Version.php` rebranded (`azimio-crm:version`); ICS `PRODID` in
      Automation's Activity helper rebranded; `Core.php` retains only internal comments.

## Phase 2 — Localization: TZS + Swahili/English only

- [x] 2.1 `APP_CURRENCY=TZS` default (config + .env + .env.example). `Core.php`
      renders **"TSh 1,500,000"** (custom-cased TZS: `TSh` symbol, 0 decimals).
      Installer (web + console) currency choices trimmed to TZS (default) + USD.
- [x] 2.2 Default timezone `Africa/Dar_es_Salaam` (config + .env + .env.example).
- [x] 2.3 Swahili (`sw`) lang files created for all 5 lang-bearing packages:
      Core, WebForm, DataTransfer, Installer (fully translated) and Admin
      (~640 high-traffic values translated — menus, auth, buttons, fields, common
      messages; longer prose still English as graceful fallback).
      **Ongoing: refine Admin sw translations section by section — a native
      Swahili speaker should review; file is
      `packages/Webkul/Admin/src/Resources/lang/sw/app.php`.**
- [x] 2.4 `sw` registered in `config/app.php` `available_locales`
      (drives `Core@locales`, installer locale pickers, validation).
- [x] 2.5 `available_locales` now en + Kiswahili only; old lang folders kept on disk
      (unselectable) to keep upstream merges clean.
- [x] 2.6 Per-user language: new nullable `users.locale` column (Azimio migration),
      exposed on My Account page (select with "Use system default"), validated
      against available locales; `Admin\Locale` middleware now prefers the user's
      locale over the global config. Dev seeder sets tenant admins' locale from
      their tenant.
- [x] 2.7 Email templates seed via `trans('installer::app.seeders.email...')`, which
      now has Swahili — reseeding with `locale=sw` (and per-tenant seeding in
      Phase 3.6) produces Swahili templates automatically.

## Phase 3 — Multi-Tenancy Core

- [x] 3.1 `tenants` table created in Phase 0 (`packages/Azimio/Tenancy`).
- [x] 3.2 `tenant_id` (nullable, indexed, FK, cascade) added to 22 business tables via
      one loop migration (`2026_07_22_000004`). Child/pivot tables (quote_items,
      lead_tags, attribute_values, ...) intentionally inherit tenancy through their
      parent row. Canonical strict/shared table lists live in
      `Azimio\Tenancy\TenantTables`.
- [x] 3.3 `BelongsToTenant` trait (global `TenantScope` + auto-stamp on create +
      `withoutTenancy()` escape hatch) applied to 23 models. Two scoping modes:
      **strict** (leads, persons, orgs, products, quotes, activities, emails, tags,
      users, groups, warehouses, web forms, webhooks, workflows, campaigns, events,
      core_config) and **shared platform defaults** (`$sharesPlatformDefaults`:
      attributes, pipelines, sources, types, roles, email templates — NULL-tenant rows
      visible to every tenant, so fresh tenants work without copying seed data).
- [x] 3.4 `TenantManager` singleton + `tenant()` / `tenant_id()` helpers + `runAs()`;
      `IdentifyTenant` middleware (alias `tenant`) resolves tenant from the logged-in
      user, blocks suspended tenants (logout + flash), and leaves super admins/console
      unscoped. Admin route middleware reordered to
      `['web', 'user', 'tenant', 'admin_locale']` so tenant context exists before
      config reads.
- [x] 3.5 Super-admin panel at `/admin/super/tenants` (part of `Azimio\Tenancy`,
      gated by `EnsureSuperAdmin` middleware — 404 for tenant users): list tenants
      with usage counts (users/leads/persons), create tenant (form → TenantOnboarder),
      suspend/activate toggle, impersonate ("login as" first tenant admin, with
      session-based revert via POST `/admin/super/revert`). en + sw translations.
      **Later polish: link the panel from the admin menu for super admins; richer
      per-tenant stats (storage, activity).**
- [x] 3.6 Tenant onboarding: `TenantOnboarder` service (tenant + admin user in one
      transaction, admin gets the shared platform Administrator role and the tenant's
      locale) + `php artisan azimio:tenant:create "Name" email@x.tz --locale=sw`
      console command. No per-tenant copies needed thanks to shared platform defaults.
- [x] 3.7 **DECIDED: emails stay globally unique** (single login form on a shared
      domain, no ambiguity). Suspended tenants: users are logged out on the next
      request by `IdentifyTenant` with a translated (en/sw) message.
- [x] 3.8 `core_config` is tenant-scoped (strict): tenant settings reads/writes hit
      their own rows; missing rows fall back to file config defaults. Global rows
      (NULL) remain for the platform/login screen.
- [x] 3.9 File storage audited: all upload paths are entity-id-scoped
      (`activities/{id}`, `{entity_type}/{entity_id}`) with globally unique ids and
      access always via tenant-scoped DB rows — no cross-tenant leak. Per-tenant
      directory prefix deferred as an ops enhancement (quotas, wipe-on-delete).
      IMAP: credentials live in tenant-scoped `core_config`;
      `inbound-emails:process` now iterates active tenants and fetches each inbox
      inside `runAs()` so fetched emails are stamped per tenant.
- [x] 3.10 Queue-wide tenant propagation in `TenancyServiceProvider`:
      `Queue::createPayloadUsing` stamps every job with the dispatching tenant;
      `JobProcessing` restores the context, `JobProcessed`/`JobFailed` clear it.
      Covers queued mail, imports, and notifications without touching job classes.
      Workflows/webhooks run synchronously in request context (already scoped).
- [x] 3.11 DataGrids **audited: every grid uses raw `DB::table()`** (bypasses Eloquent
      scopes). Fixed centrally with zero upstream edits: wildcard listener
      `ScopeDataGridToTenant` on `datagrid.*.query_builder.set.after` constrains each
      grid's root table per `TenantTables` (strict or shared). Covers listings,
      exports, and mass-action id selection.
      **Pending sub-item: audit remaining raw `DB::` usage outside DataGrids
      (reporting helpers in `Admin/Helpers/Reporting`, global search) — same
      root-table filter approach applies.**
- [x] 3.12 Web forms: the public `formStore` endpoint now resolves the form first and
      establishes tenant context from `web_forms.tenant_id` (404 if the tenant is
      suspended) — the person-dedupe lookup, lead/person creation, and
      pipeline/source/type defaults all run tenant-scoped.
- [x] 3.13 Audited: no live token API exists — `routes/api.php` is the Laravel stub
      and all admin REST endpoints sit inside the web group, which carries the
      `tenant` middleware. **If a Sanctum API is added later, its route group must
      include `tenant` (resolve from the token's user).**
- [x] 3.14 Isolation suite v1 (12 tests, 34 assertions): model scoping (read/update/
      delete/stamping), shared platform defaults, DataGrid raw-builder scoping,
      unscoped console, super-admin panel access control, suspended-tenant logout,
      impersonate/revert. Also fixed the raw email-union queries in
      Lead/Person ActivityControllers (were probe-able cross-tenant).
      **Ongoing: extend per-module coverage (exports, PDFs, mega-search, mail
      compose lookups) during Phase 6 regression.**

## Phase 4 — Per-Tenant Custom Branding

- [x] 4.1 Branding settings ride on Krayin's existing Configuration UI
      (Configuration → General), which is per-tenant since `core_config` became
      tenant-scoped: logo upload (`admin_logo.logo_image`), **favicon (new field
      added)**, brand color (`menu_color.brand_color`), footer text, custom CSS.
      Display name = `tenants.name` (set by super admin). `.env`
      `FILESYSTEM_DISK` switched local→public so config uploads are web-servable.
      **Note: the shared login page cannot be tenant-branded — the tenant is unknown
      before authentication. It carries AzimioCustomers platform branding.**
- [x] 4.2 Layout injection: page `<title>` gets " — Tenant Name" suffix; header +
      mobile-sidebar logos use tenant config with AzimioCustomers fallback; logo alt
      = tenant name; brand color flows through the existing `--brand-color` CSS var.
      Fixed upstream bugs found on the way: dead `cache/logo/bagisto.png` preload
      removed; mobile sidebar read a wrong config key (`general.design.*`); favicon
      blade key aligned to the new config field.
- [x] 4.3 Outgoing emails: header logo + footer "cheers" name are tenant-branded
      (absolute logo URL; tenant context restored in queued mail by Phase 3.10).
      Quote PDFs: tenant name + uploaded logo (non-SVG; dompdf limitation) render in
      the header above the quote title.

## Phase 5 — Tanzania-Market Features (prioritized backlog)

- [x] 5.1 **Click-to-call**: every contact number in the shared inline-phone component
      (persons, orgs, leads, warehouses) renders as a `tel:` link.
      **Pending refinement: TZ-format (07XX/06XX) client-side validation rule and a
      +255 input prefix — the current `phone` vee-validate rule is generic.**
- [x] 5.2 **WhatsApp v1**: a "WA" deep link (`wa.me`) next to each contact number;
      local 0-prefixed numbers auto-convert to +255 international format.
      **Later: WhatsApp Business Cloud API (template sends, conversation logging)
      — needs a Meta business account.**
- [ ] 5.3 **SMS notifications** via local gateways (Beem Africa, Africa's Talking):
      lead assignment alerts, activity reminders, quote sent.
      **Blocked on choosing a gateway + credentials; then implement as a Laravel
      notification channel + workflow action.**
- [x] 5.4 **Mobile-money on quotes**: new shared attributes `payment_method`
      (M-Pesa, Tigo Pesa, Airtel Money, Halopesa, T-Pesa, Bank Transfer, Cash) and
      `payment_reference` (TxID) — appear automatically in quote create/edit forms
      via the attribute system (migration `2026_07_22_000006`).
- [x] 5.5 **TRA compliance**: `tin` + `vrn` attributes on organizations; new
      Configuration → General → **Company Information** section (TIN, VRN, phone,
      address — per tenant, en/sw) printed in the quote PDF header. Quotes already
      carry a `tax_amount` field for the VAT line.
      **Pending refinement: auto-calculate 18% VAT toggle on the quote form.**
- [ ] 5.6 **Low-bandwidth friendliness**: audit page weight, lazy-load dashboards,
      make the app usable on 3G; test on low-end Android (most users are mobile).
- [x] 5.7 **Swahili-first defaults**: tenant locale chosen at onboarding (CLI defaults
      to `sw`, panel pre-selects Kiswahili); users inherit tenant locale.
      **Pending: audit date display format for `dd/mm/yyyy` consistency.**
- [x] 5.8 **Tanzania regions**: all 31 mikoa seeded into `country_states`
      (migration `2026_07_22_000005`) — address forms now show a region dropdown when
      country = Tanzania. **Districts/wards deferred (needs a maintained dataset;
      regions cover reporting needs for v1).**
- [~] 5.9 Plan management v1: `tenants.plan` + `max_users` fields exist; **user
      creation is now blocked beyond `max_users`** with a translated message (en/sw).
      **Pending: plan editing in the super-admin panel UI; lead/storage limits;
      manual mobile-money subscription payment recording.**
- [x] 5.10 Data residency + backup policy documented in
      [DEPLOYMENT.md](DEPLOYMENT.md) (nightly dumps, retention, per-tenant export
      recipe, procurement statement template).

## Phase 6 — Hardening & Launch

- [x] 6.1 Automated regression smoke (`ModuleSmokeTest`): all 25 module index pages
      (Leads, Contacts, Quotes, Products, Mail, Activities, all Settings modules,
      Configuration, Dashboard) render for a tenant admin AND the super admin —
      51 assertions. **Hardening found & fixed: platform-default rows (shared
      pipelines/roles/sources/...) were writable by tenants — now read-only for
      tenant contexts via update/delete guards in `BelongsToTenant` (tested).**
      **Remaining before launch: a human click-through of create/edit flows per
      module (automated smoke covers rendering, not every mutation).**
- [x] 6.2 Composite `(tenant_id, created_at)` indexes on the 7 highest-volume tables
      (activities, emails, leads, persons, organizations, products, quotes) —
      migration `2026_07_22_000007`.
- [x] 6.3 `php artisan azimio:tenant:adopt <id|slug> [--dry-run]`: assigns all orphan
      (NULL tenant_id) rows in strict business tables to a tenant; skips super
      admins and never touches shared platform defaults. Dry-run verified live.
- [x] 6.4 Installer (web wizard, console command, and seeder) now creates the initial
      admin as **platform super admin** (`is_super_admin = 1`, no tenant); first
      tenant is then created via the super panel or `azimio:tenant:create`.
- [x] 6.5 [DEPLOYMENT.md](DEPLOYMENT.md): env vars, first install, tenant management,
      queue/scheduler/IMAP processes, backups + **data residency note (covers 5.10)**,
      upstream-merge procedure with conflict hotspots, post-deploy checklist.

---

## Phase 7 — Tenant Self-Service Workflow (registration → dashboard → lifecycle)

Audit of the full tenant journey (2026-07-22). Today a tenant exists only if the
super admin creates it; there is **no public registration**, no first-login
onboarding, no trial/billing lifecycle, and no offboarding. Items below are
scoped to be done one by one, in the suggested order at the end.

### A. Registration (tenant acquisition)

- [ ] 7.1 **Public registration page** at `/register` ("Sajili Kampuni Yako" /
      "Register your Company"): company name, admin name, email, password,
      language (sw default), terms checkbox → `TenantOnboarder` creates
      tenant + admin, logs them in, redirects to dashboard. Same visual
      language as the login page. Must be throttled (rate limiter) + honeypot
      field; email stays globally unique (existing decision 3.7).
- [ ] 7.2 **Email verification**: registering admin must verify email before
      the tenant activates (tenant status `pending` until verified; en/sw
      verification email through the existing branded mail layout).
- [ ] 7.3 **Terms of Service + Privacy Policy pages** (en/sw), linked from the
      registration checkbox — required before 7.1 goes live.
- [ ] 7.4 **New-registration notification** to the platform super admin
      (email: tenant name, admin contact, chosen language).
- [ ] 7.5 **Trial defaults + expiry**: self-registered tenants start on a
      `trial` plan (e.g. 14 days, `max_users` = 3); scheduler task suspends
      expired trials; suspended-login message tells them to contact sales.
      Needs `trial_ends_at` column on tenants.

### B. First login & onboarding

- [ ] 7.6 **Onboarding checklist card** on the tenant dashboard until completed/
      dismissed: upload your logo → set Company Information (TIN/VRN) → invite
      your team → create your first lead. Each step links to the right page.
- [ ] 7.7 **Super admin landing**: after login, super admins should land on
      `/admin/super/tenants` (not the unscoped CRM dashboard, which mixes all
      tenants' data and is misleading).

### C. Daily-use gaps (found while walking the flows)

- [ ] 7.8 **Super panel discoverability + impersonation UI**: menu/user-dropdown
      link to the tenants panel (super admin only), and a persistent banner
      while impersonating — "You are logged in as {tenant} — Return to super
      admin" wired to the existing `super.tenants.revert` route (today the
      revert route exists but nothing in the UI triggers it).
- [ ] 7.9 **Plan editing in the super panel**: edit plan name / `max_users` /
      trial end per tenant; show why a tenant is suspended.
- [ ] 7.10 **Per-tenant outgoing mail identity**: from-name should be the tenant
      name and reply-to the sending user (MAIL_FROM_* is one global env today).
      Also fix leftover `MAIL_FROM_ADDRESS=laravel@krayincrm.com` in
      `.env.example` (missed in the Phase 1 rebrand).
- [ ] 7.11 **Human click-through regression** of create/edit flows per module
      as a tenant admin (carried over from 6.1 — automated smoke covers page
      rendering, not every mutation path).

### D. Lifecycle & offboarding

- [ ] 7.12 **Tenant data export**: per-tenant Excel/CSV export (leads, persons,
      organizations, quotes, activities) — needed for offboarding requests and
      the DEPLOYMENT.md data-residency promise.
- [ ] 7.13 **Tenant deletion with grace period**: suspend → export → delete
      (FK cascades already wipe child rows); confirmation flow in the super
      panel and file-storage cleanup for the tenant's uploads.

**Suggested order:** 7.8 (small, unblocks admin daily work) → 7.7 → 7.10 →
7.3 → 7.1 → 7.2 → 7.4 → 7.5 → 7.6 → 7.9 → 7.11 → 7.12 → 7.13.
Rationale: fix the sharp edges super admins hit today first, then the
registration funnel in dependency order (terms before the page, verification
before opening it up), then lifecycle.

---

### Suggested execution order
1 (rebrand — quick win, visible) → 2 (locale/currency) → 3 (tenancy — the big one,
do 3.1–3.4 first, then module-by-module scoping) → 4 → 6.1–6.4 → launch → 5 as
post-launch iterations (5.1 and 5.5 are worth pulling forward if quoting is a
day-one use case).
