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

- [ ] 1.1 Global rename sweep of user-visible "Krayin" strings — all files under
      `packages/Webkul/*/src/Resources/lang/*` (`app.php`), installer views
      (`packages/Webkul/Installer/src/Resources/views/installer/index.blade.php`),
      onboarding template (`packages/Webkul/Installer/src/Templates/on-boarding.php`),
      and email templates seeder (`packages/Webkul/Installer/src/Database/Seeders/EmailTemplate`).
- [ ] 1.2 Replace logos/favicons/login imagery in `public/` and package asset sources
      (`packages/Webkul/Admin/src/Resources/assets`); rebuild with `npm run build`.
- [ ] 1.3 Update `composer.json` root (`krayin/laravel-crm` → `azimio/customers`),
      `README.md`, `APP_NAME` in `.env.example`, and mail "from" name defaults.
- [ ] 1.4 Leave internal namespaces (`Webkul\`, `krayin/*` package names in
      `packages/Webkul/*/composer.json`) **unchanged** — renaming them breaks upstream
      merges and autoloading for zero user-facing value. Document this decision in README.
- [ ] 1.5 Update PDF print templates (quotes) header/footer branding
      (`barryvdh/laravel-dompdf` views in Quote/Admin packages).
- [ ] 1.6 Check `Core.php` and `Version.php` console command for hardcoded brand/version strings.

## Phase 2 — Localization: TZS + Swahili/English only

- [ ] 2.1 Set `APP_CURRENCY=TZS` default (`config/app.php:180`); verify
      `Core.php::currencySymbol()` / `formatBasePrice()` render as **"TSh 1,500,000"**
      (TZS conventionally shows no decimal places — adjust the NumberFormatter if needed).
- [ ] 2.2 Set default timezone `Africa/Dar_es_Salaam` and date format conventions in config.
- [ ] 2.3 Create Swahili (`sw`) translations: copy `en` folders and translate —
      `packages/Webkul/Admin/src/Resources/lang/sw/app.php` (the big one),
      plus Installer, and any other package with a `lang` dir.
- [ ] 2.4 Register `sw` in the locale list (`Webkul\Core\Core@locales` — see how
      existing locales are stored/seeded; likely `locales` table via Installer seeder).
- [ ] 2.5 Remove/disable non-target locales (ar, es, fa, pt_BR, tr, vi) from the locale
      options so only English & Swahili are selectable. Prefer disabling in the
      seeder/options over deleting lang folders (keeps upstream merges clean).
- [ ] 2.6 Per-user language preference: verify existing user locale setting works and
      persists; default new users to `sw` or `en` per tenant setting (Phase 3).
- [ ] 2.7 Swahili email templates (welcome, forgot password, lead/quote notifications).

## Phase 3 — Multi-Tenancy Core

- [ ] 3.1 Migration: `tenants` table — name, slug, logo, primary color, status
      (active/suspended), locale default, currency, plan/limits fields, timestamps.
- [ ] 3.2 Migration: add nullable `tenant_id` (indexed, FK) to all business tables:
      users, groups, roles, leads, persons, organizations, products, quotes, tags,
      activities, emails, email_templates, workflows, web_forms, warehouses, attributes
      (custom ones), pipelines/stages, sources/types. Audit full list from
      `packages/Webkul/Installer/src/Database/Migrations/` + `database/migrations/`.
- [ ] 3.3 `BelongsToTenant` trait: global scope filtering by current tenant + auto-fill
      `tenant_id` on create. Apply to every model listed in 3.2 (models live in
      `packages/Webkul/*/src/Models/`).
- [ ] 3.4 Tenant context resolver: middleware that sets current tenant from the
      authenticated user; a `Tenant` facade/helper (`tenant()`) for the active tenant.
- [ ] 3.5 Super-admin panel (new package, e.g. `packages/Azimio/SuperAdmin`):
      CRUD tenants, suspend/activate, impersonate ("login as tenant admin"),
      usage stats (users, leads, storage per tenant).
- [ ] 3.6 Tenant onboarding flow: creating a tenant seeds its defaults — admin user,
      default pipeline/stages, lead sources/types, email templates (Swahili + English),
      roles. Extract from `Installer/src/Database/Seeders/*` into a reusable
      `TenantSeeder`.
- [ ] 3.7 Auth changes: login stays on the shared domain; scope unique-email validation
      to tenant (or keep emails globally unique — decide; globally unique is simpler
      and avoids ambiguous login). Block login for suspended tenants.
- [ ] 3.8 Tenant-scope the config/settings system (`core_config` table via
      `SystemConfig`): settings like locale, branding, email must be per-tenant.
- [ ] 3.9 Tenant-scope file storage: uploads under `storage/app/tenants/{id}/...`;
      scope IMAP/mail settings (`webklex/laravel-imap` inboxes are per-tenant).
- [ ] 3.10 Tenant-scope queued jobs, workflows/automation, and scheduled tasks —
      every job must carry and restore tenant context.
- [ ] 3.11 Tenant-scope DataGrids (`packages/Webkul/DataGrid`) — verify grids use
      Eloquent (scope applies) vs raw query builder (scope must be added manually).
      Same audit for repositories using `DB::` directly and for global search.
- [ ] 3.12 Web forms (`WebForm` package) are publicly embedded — form URLs/keys must
      encode the tenant so submissions land in the right tenant.
- [ ] 3.13 REST API / Sanctum tokens: tokens resolve tenant from user; test cross-tenant
      access is impossible via API.
- [ ] 3.14 **Isolation test suite** (most important tests in the project): for each
      module, tenant A must never read/update/delete tenant B data — via UI routes,
      API, global search, exports (maatwebsite/excel), PDFs, and lookups/dropdowns.

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
