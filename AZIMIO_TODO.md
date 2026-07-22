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

- [ ] 4.1 Tenant branding settings page: logo upload, favicon, primary color, app
      display name, login-page welcome text.
- [ ] 4.2 Inject branding into admin layout (menu logo, page titles) and login screen;
      fall back to AzimioCustomers defaults.
- [ ] 4.3 Per-tenant branding in outgoing emails and quote PDFs (logo + footer).

## Phase 5 — Tanzania-Market Features (prioritized backlog)

- [ ] 5.1 **Phone-first contacts**: default country code +255, validate TZ mobile
      formats (07XX/06XX), click-to-call links.
- [ ] 5.2 **WhatsApp integration**: WhatsApp is the dominant business channel in TZ —
      wa.me deep links from person/lead pages first; WhatsApp Business Cloud API
      (send templates, log conversations as activities) as a later item.
- [ ] 5.3 **SMS notifications** via local gateways (Beem Africa, Africa's Talking):
      lead assignment alerts, activity reminders, quote sent — SMS reaches users email doesn't.
- [ ] 5.4 **Mobile-money fields on quotes/payments**: record payment method
      (M-Pesa, Tigo Pesa, Airtel Money, Halopesa, bank, cash) and transaction reference
      on won deals/quotes.
- [ ] 5.5 **TRA compliance fields**: TIN and VRN fields on organizations and on the
      tenant's own profile; 18% VAT line on quotes; quote PDF layout matching local
      proforma-invoice expectations.
- [ ] 5.6 **Low-bandwidth friendliness**: audit page weight, lazy-load dashboards,
      make the app usable on 3G; test on low-end Android (most users are mobile).
- [ ] 5.7 **Swahili-first UX defaults**: tenant chooses default language at onboarding;
      dates in `dd/mm/yyyy`.
- [ ] 5.8 Region/district address dropdowns for Tanzania (regions → districts → wards)
      instead of free-text, for clean reporting.
- [ ] 5.9 Simple subscription/plan management for tenants (per-user pricing, limits on
      users/leads), with manual mobile-money payment recording first, gateway later.
- [ ] 5.10 Data residency/backup note: document where data is hosted and daily backup
      policy — a common procurement question from TZ organizations.

## Phase 6 — Hardening & Launch

- [ ] 6.1 Full regression pass per module (Leads, Contacts, Quotes, Products, Mail,
      Automation, Web Forms, Settings) as a tenant user and as super admin.
- [ ] 6.2 Performance: add composite indexes `(tenant_id, ...)` on hot query paths.
- [ ] 6.3 Migration script for any pre-existing single-tenant data → tenant #1.
- [ ] 6.4 Update installer (`packages/Webkul/Installer`) to create the super admin +
      first tenant instead of a plain admin.
- [ ] 6.5 Deployment runbook: env vars, queue workers, scheduler, backups, upgrade
      procedure that preserves tenancy patches when merging upstream Krayin releases.

---

### Suggested execution order
1 (rebrand — quick win, visible) → 2 (locale/currency) → 3 (tenancy — the big one,
do 3.1–3.4 first, then module-by-module scoping) → 4 → 6.1–6.4 → launch → 5 as
post-launch iterations (5.1 and 5.5 are worth pulling forward if quoting is a
day-one use case).
