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
- [→] 5.3 **SMS notifications** via local gateways — **superseded by Phase 8**, which
      grew this from "notification channel" into a full Text Messaging module.
      Gateway decided: **Beem Africa first**. The original scope (lead assignment
      alerts, activity reminders, quote sent) is now Phase 8 item 8.14.
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

- [x] 7.1 **Public registration page** live at `/register` (en/sw): company name,
      admin name, email, password, language (sw default), required terms checkbox →
      `TenantOnboarder` creates tenant + admin, logs them in, redirects to dashboard
      with a welcome flash. Throttled (5/min) + honeypot field (silent drop);
      globally-unique email enforced. Linked from the login page.
- [ ] 7.2 **Email verification**: registering admin must verify email before
      the tenant activates (tenant status `pending` until verified; en/sw
      verification email through the existing branded mail layout).
- [x] 7.3 **Terms + Privacy pages** at `/terms` and `/privacy` (en/sw), linked from
      the registration checkbox. **Content is a sensible template — must be reviewed
      by legal counsel before public launch (noted on the pages themselves).**
- [x] 7.4 **New-registration notification**: queued email to all active super admins
      (tenant, admin contact, language, link to the panel); failures logged, never
      block registration.
- [ ] 7.5 **Trial defaults + expiry**: self-registered tenants start on a
      `trial` plan (e.g. 14 days, `max_users` = 3); scheduler task suspends
      expired trials; suspended-login message tells them to contact sales.
      Needs `trial_ends_at` column on tenants.

### B. First login & onboarding

- [ ] 7.6 **Onboarding checklist card** on the tenant dashboard until completed/
      dismissed: upload your logo → set Company Information (TIN/VRN) → invite
      your team → create your first lead. Each step links to the right page.
- [x] 7.7 **Super admin landing**: after login, super admins are redirected to
      `/admin/super/tenants` instead of the unscoped CRM dashboard.

### C. Daily-use gaps (found while walking the flows)

- [x] 7.8 **Super panel discoverability + impersonation UI**: "Tenants" link in the
      user dropdown (super admins only) and an amber banner on every page while
      impersonating — "You are logged in as {tenant} — Return to super admin"
      wired to `super.tenants.revert` (en/sw).
- [ ] 7.9 **Plan editing in the super panel**: edit plan name / `max_users` /
      trial end per tenant; show why a tenant is suspended.
- [x] 7.10 **Per-tenant outgoing mail identity**: `MessageSending` listener keeps the
      platform from-address (SPF-safe) but sets the display name to the tenant's
      name whenever tenant context is active (covers queued mail via 3.10).
      Leftover `laravel@krayincrm.com` in `.env.example` fixed →
      `noreply@azimio.co.tz`. **Reply-to-the-sending-user deferred (sender not
      available in queued context; needs plumbing through mailables).**
- [ ] 7.11 **Human click-through regression** of create/edit flows per module
      as a tenant admin (carried over from 6.1 — automated smoke covers page
      rendering, not every mutation path).

### C. (continued)

- [x] 7.14 **Cross-tenant user management** in the super panel
      (`/admin/super/users`): list every user across all tenants (search by
      name/email, filter by company, paginated), with **add / edit / delete**
      and a **password reset**. Editing covers name, email, **company (tenant)
      affiliation**, **role**, language and active status. Role options filter
      live (Alpine) to the selected company's roles + shared platform defaults;
      a server-side guard rejects assigning another company's role. Super admins
      are shown as platform-level (no company) and the last super admin / your
      own account cannot be deleted. `tenant_id` is set via `forceFill` (it is
      intentionally not mass-assignable). "Users" tab added beside "Tenants" and
      linked from the super-admin dropdown. en/sw. Tests in
      `SuperAdminUserPanelTest`. **Note: super-panel user creation is not subject
      to the tenant `max_users` cap — super admins manage plans.**
      **Bug fixed 2026-11-12:** `UserController::store()` and `update()` read
      `$validated['locale']` with `?:`, which needs the key to exist. `locale` is
      `nullable`, so Laravel omits it from the validated array whenever the
      request does not send it — any create/update without that field returned a
      500. Now `($validated['locale'] ?? null) ?: null`, which both survives the
      missing key and keeps the original coercion of an empty string to NULL.
      Three regression tests added (create without locale, update without
      locale, empty string clears to NULL); the first two were verified to fail
      against the unfixed controller.

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

## Phase 8 — Text Messaging (SMS) Module  ⚡ **CURRENT PRIORITY**

Added 2026-11-12. Supersedes the one-line 5.3 placeholder.

### Goal

A complete, tenant-aware SMS module with **two independent sending modes**:

| Mode | Sender name | Credits consumed | Who sets it up |
|---|---|---|---|
| **Own provider** (BYO) | tenant's own registered sender name | tenant's own balance at their provider — **platform credits never touched** | the tenant, in CRM settings |
| **Platform** (default) | `AZIMIOCRM` | platform SMS credits allocated to that tenant | platform admin allocates; tenant just sends |

A tenant with no provider of their own falls back to platform mode, where they can
**request credits** and a platform admin **approves/allocates** them. Platform admins
get a module with full control over providers, sender names, credit allocation, usage
and audit across every tenant. Both modes share one sending pipeline, one message log
and one reporting surface, so the tenant UI is identical either way.

First provider driver: **Beem Africa**. The module is driver-based so Africa's
Talking (and others) drop in later without touching call sites.

### Decisions — **CONFIRMED 2026-11-12**

These six are settled. They are the contract the schema and the sending pipeline
are built against; changing one later means a migration, so treat them as fixed
unless there is a reason to revisit, and record the reason here if so.

- [x] 8.0.1 **Credit unit = one SMS *segment*.** Not one message, not one
      recipient. A 320-character message to 10 people costs 20 credits.
      Rationale: it is exactly how Beem bills us, so the ledger can never drift
      from real cost. Consequence: every send surface must show the live segment
      count *before* confirmation (8.7, 8.8) — a tenant must never be surprised
      by a 3x charge for a long message.
- [x] 8.0.2 **Platform-mode refunds: pre-submission only.** Credits are debited
      when the provider *accepts* the message, and refunded **only** if the
      provider rejects it before it reaches the network. A message that was
      submitted and then never delivered is **not** refunded — the operator
      already charged us. Failures caught before submission (invalid number,
      opt-out, quiet hours, insufficient credits) never debit at all, so there is
      nothing to refund. Consequence: the debit happens in the driver's
      post-submit callback, not at enqueue time; reserve-then-settle, never
      debit-then-hope.
- [x] 8.0.3 **Credit purchase is recorded manually.** Payment happens outside the
      CRM (mobile money / bank, consistent with 5.9), and a platform admin records
      the resulting allocation against the tenant. No payment gateway in this
      phase; auto-purchase is parked in 8.21.
- [x] 8.0.4 **One active sender profile per tenant.** A tenant runs *either*
      platform mode *or* their own provider, with one sender name, switched in
      settings. No per-message or per-campaign mode mixing. Multiple concurrent
      profiles and per-campaign sender choice stay deferred to 8.21.
- [x] 8.0.5 **Provider credentials are encrypted at rest** in
      `sms_sender_profiles.credentials` (Laravel encrypted JSON cast), *not* in
      `core_config`, which stores plaintext. Consequence: `APP_KEY` becomes
      backup-critical — losing it makes every stored credential unrecoverable.
      Must be called out in DEPLOYMENT.md (8.26).
- [x] 8.0.6 **Own-provider failure: fail, then offer platform send only if credits
      are already there.** When a tenant's own provider errors or their own
      balance runs dry, the send **fails** — platform credits are never spent
      silently. What happens next depends on their platform balance:
      - **Balance covers the send** → the failure screen offers an explicit
        *"Send via AZIMIOCRM (N credits)"* action. One click, clearly labelled
        with the credit cost and the fact that the sender name will change to
        `AZIMIOCRM`. Nothing is sent until they click.
      - **Balance is zero or short** → no retry offer at all. Fail with a distinct
        **"no platform credits"** alert that states the shortfall and links
        straight to the credit request form (8.13).
      Consequence: the send pipeline (8.5) needs a terminal
      `failed_own_provider` state carrying the shortfall, and the batch screen
      (8.8, 8.10) needs the two-branch failure UI. Never auto-fall back.

### A. Foundation

- [x] 8.1 **New package `packages/Azimio/Messaging`** — PSR-4 `Azimio\Messaging\`
      → `packages/Azimio/Messaging/src`, registered in root `composer.json`
      autoload and in `config/app.php` providers **after** `TenancyServiceProvider`
      (it builds on the `tenant` and `super_admin` middleware aliases that
      package registers). `MessagingServiceProvider` wires migrations, views
      (`messaging::`), en + sw translations, config merge + publish tag
      (`messaging-config`), routes, and empty listener/command hooks that 8.19
      and 8.20 fill in.
      **`src/Config/messaging.php` encodes the 8.0 decisions as real values** —
      `credits.unit = segment` (8.0.1), `credits.refund_policy =
      pre_submission_only` (8.0.2), the GSM-7 160/153 and UCS-2 70/67 segment
      tables, `+255`/TZ phone defaults, quiet-hours window, batch chunking and
      webhook settings. The Beem endpoint block carries an explicit
      "verify against current docs in 8.3" warning rather than values anyone
      should trust.
      **Deliberate difference from Tenancy:** routes live in three files under
      `src/Routes/` (`admin.php`, `super.php`, `webhook.php`) instead of inline
      in the provider, because this module has three different middleware
      stacks — tenant admin `['web','user','tenant','admin_locale']`, super
      admin `['web','user','super_admin','admin_locale']`, and public webhooks
      with no session. Each file is a stub naming the items that populate it.
      (The webhook stub originally promised signature verification; 8.3 found
      Beem documents no such mechanism — see the rewritten 8.19.)
      en/sw lang stubs already carry the two distinct 8.0.6 failure strings
      (`retry-via-platform` vs `no-platform-credits`). `.env.example` documents
      the new keys with `AZIMIO_SMS_DRIVER=log` as the dev default.
      **Verified:** class autoloads, provider boots, all config keys resolve,
      view namespace + publish tag registered, `route:list` builds (205 routes),
      en/sw translations resolve, and every middleware alias the provider uses
      exists (`throttle` from the Kernel, `user`/`admin_locale` from
      `AdminServiceProvider`, `tenant`/`super_admin` from Tenancy).
- [x] 8.2 **Schema** — 9 migrations (`2026_11_12_000001`–`000009`), all registered in
      `Azimio\Tenancy\TenantTables`, plus the Eloquent layer (8 models, 8 enums)
      and an isolation test file. Verified: migrated, rolled back all 9, and
      re-migrated with the seeded tenants intact, so `down()` works and the FK
      drop order is right.
      **Two corrections to the spec below, both found while building it:**
      1. **`sms_sender_profiles` is STRICT, not shared.** The spec said "strict,
         except `tenant_id = NULL` = the platform profile", which in
         `TenantTables` terms means SHARED — and SHARED makes NULL rows readable
         by every tenant. That row holds the platform's provider credentials,
         and the model's `encrypted:json` cast would hand them back **decrypted**
         to any tenant that read it. It is now STRICT; the sending service loads
         the platform profile explicitly via `SenderProfile::platform()`
         (`withoutTenancy()`), and tenant UI shows the sender name from
         `config('messaging.platform.sender_name')` instead of from the row.
         `credentials` is also in `$hidden`, so it stays out of logs, queue
         payloads and serialised output. Covered by a test.
      2. **`azimio:tenant:adopt` would have hijacked the platform profile.** It
         sweeps every STRICT table for NULL `tenant_id` rows and assigns them to
         one tenant — which, once `sms_sender_profiles` became STRICT, meant the
         platform's own sending identity and credentials. Added a `NEVER_ADOPT`
         list to `AdoptOrphanData` alongside the existing super-admin carve-out.
      Also added `TenantTables::PLATFORM_ONLY` for `sms_webhook_events`, which
      belongs in neither list: a callback arrives before tenant context exists.
      - `sms_sender_profiles` — **strict** (see correction 1). Columns: `label`,
        `sender_name`, `driver`
        (`beem|africastalking|log`), `credentials` (encrypted JSON), `is_default`,
        `status` (`pending|approved|rejected|disabled`), `approved_at`,
        `approved_by`, `notes`.
      - `sms_credit_ledger` — **strict**, append-only. `type`
        (`allocation|purchase|debit|refund|adjustment|expiry`), signed `credits`,
        `balance_after`, polymorphic `reference` (batch/message/request),
        `created_by`, `note`. Balance is the ledger sum; `tenants.sms_credits`
        caches it for cheap reads and is reconcilable from the ledger.
      - `sms_credit_requests` — **strict**. `requested_by`, `credits_requested`,
        `reason`, `status` (`pending|approved|partially_approved|rejected`),
        `credits_granted`, `reviewed_by`, `reviewed_at`, `admin_note`.
      - `sms_batches` — **strict**. `sender_profile_id`, `name`, `template_id`,
        `body`, `audience_type` (`manual|datagrid_selection|segment`),
        `total_recipients`, per-status counts, `credits_estimated`,
        `credits_charged`, `status`
        (`draft|scheduled|sending|completed|partially_failed|cancelled`),
        `scheduled_at`, `started_at`, `completed_at`, `created_by`.
      - `sms_messages` — **strict**, the one log for everything sent. `batch_id`
        (nullable → single send), `sender_profile_id`, polymorphic recipient
        (person / lead / user / ad-hoc), `dest_addr` (E.164), `raw_number`, `body`,
        `encoding` (`gsm7|ucs2`), `segments`, `credits_charged`, `cost_source`
        (`platform|own`), `direction` (`outbound|inbound`), `status`
        (`queued|sending|submitted|delivered|failed|rejected|undeliverable`),
        `provider_message_id`, `provider_request_id`, `error_code`,
        `error_message`, `scheduled_at`, `submitted_at`, `delivered_at`.
        Indexes: `(tenant_id, created_at)`, `(tenant_id, status)`,
        `(provider_message_id)`, `(batch_id)`.
      - `sms_templates` — **shared** (`tenant_id = NULL` = platform template every
        tenant can use, same pattern as `email_templates`). `name`, `body`,
        `category` (`transactional|marketing`), `locale`.
      - `sms_opt_outs` — **strict**, `unique(tenant_id, dest_addr)`. `source`
        (`stop_reply|manual|import`), `person_id`, `reason`. Scoped per tenant:
        opting out of Tenant A does not mute Tenant B.
      - `sms_webhook_events` — raw provider callbacks (`provider`, `payload`,
        `signature_valid`, `processed_at`) for replay and dispute evidence.
      - `tenants` additions: `sms_credits` (cached balance, default 0),
        `sms_mode` (`platform|own`, default `platform`),
        `sms_low_credit_threshold` (default 50).
- [x] 8.3 **Driver contract + Beem driver.** `SmsDriver` with `send()`,
      `balance()`, `senderNames()`, `deliveryReports()`, `maxRecipientsPerCall()`,
      `name()`; value objects `OutboundMessage`, `Recipient`, `SendResult`,
      `Balance`, `DeliveryReport`; `SendFailure` enum; `LogDriver`, `BeemDriver`,
      `AfricasTalkingDriver` stub; `DriverManager`. 16 tests.
      **Beem API verified against docs.beem.africa on 2026-11-12** (their
      OpenAPI spec + guides). All four endpoint URLs in the config were
      correct. What the docs changed:
      - `dest_addr` takes **no leading `+` and no leading zero** (`255712000001`).
        Our schema stores E.164, so the driver converts on the way out.
      - `encoding` is an **integer** (0 = default GSM), not our internal string.
      - Success is **body code 100**, not merely HTTP 200 — a 200 with code 111
        is a failure. Tested explicitly.
      - A send returns **one `request_id` for the whole call and no
        per-recipient message id**, so delivery status is looked up by
        `(request_id, dest_addr)`. `request_id` is a string in their schema but
        an integer in their own example, so both are accepted.
      - Max **1000 recipients** per request (their error code 114).
      - Their 23 documented body codes are mapped onto `SendFailure`; code 102
        (insufficient balance) is what drives the 8.0.6 branch, and **103 is the
        only transient one** — an invalid number or unregistered sender will
        fail identically forever, so retrying is pointless.
      **Deliberate design points:** `send()` never throws for a provider
      refusal (it returns a failed `SendResult`, because the pipeline must
      record it and release the reservation per 8.0.2) — throwing is reserved
      for programmer error like missing credentials, which is tested. There is
      **no separate bulk method**: Beem takes one body plus a recipients array,
      so a single send is just one recipient, and chunking is
      `OutboundMessage::chunk()`. A **transport failure is never auto-retried**:
      Beem documents no idempotency key, so a dropped POST is ambiguous and a
      retry risks charging and delivering twice. Drivers are **not singletons** —
      each carries one profile's credentials, so a shared instance would send
      through another tenant's account.
      **Still undocumented by Beem, deliberately not guessed:** how they count
      segments or charge for long/Unicode messages on `/v1/send`. Our GSM-7
      160/153 figures come from their *Multicountry* product, a different API.
      **Charges must be reconciled against real Beem invoices before anyone
      bills on them** — this is the weakest assumption under 8.0.1.
- [x] 8.4 **Phone normalization + segment counting** (`PhoneNormalizer`,
      `SegmentCounter`). Normalize `07XXXXXXXX` / `+255…` / `255…` / `0255…` /
      spaces and dashes to E.164 `+255XXXXXXXXX`; reject numbers that cannot be
      normalized, with the reason recorded on the message row rather than silently
      dropped. Segments: GSM-03.38 160/153, UCS-2 70/67; Swahili text stays
      GSM-7 (no diacritics) so cost stays predictable.
      **DONE.** `PhoneNormalizer` + `PhoneNumber` + `PhoneRejection` (5 reasons,
      en/sw), `SegmentCounter` + `SegmentCount`. 34 tests.
      **Why normalization is a correctness issue, not tidiness:** opt-out
      matching, dedupe and `dest_addr` all compare strings, so two spellings of
      one number must collapse to one — otherwise a suppressed contact can be
      messaged again just by storing `0712 345 678` instead of `+255712345678`.
      Tested directly.
      **Tanzanian landlines (02x) are rejected** as `not_mobile` rather than
      submitted and charged for. Unambiguous foreign numbers (`+254…`, `+256…`)
      pass through untouched — a CRM with Kenyan contacts must not have them
      refused because the default market is Tanzania.
      **Segment counting is more than `strlen/160`**, and each of these would
      undercharge if skipped:
      - GSM-7 applies only if *every* character is in GSM 03.38; one curly
        quote forces the whole body to UCS-2 and cuts capacity 160 → 70.
      - The 10 extension characters (`^{}\[~]|€`, form feed) cost **two**
        septets each, not one.
      - **A two-unit character may not straddle a segment boundary.** 153 euro
        signs is 306 septets: naive `ceil(306/153)` says 2 segments, but each
        part only holds 76 of them, so the real answer is **3**. There is a
        test asserting both numbers, precisely to pin the difference.
      - `é à ö ñ` *are* GSM characters — treating them as Unicode would triple
        the price of a cheap message.
      **The WhatsApp deep link (5.2) could NOT be merged into this helper** as
      the item assumed: it is client-side JavaScript in the inline phone Blade
      component and cannot call PHP. It has instead been aligned to the same
      rules (00-prefix, trunk zero, bare national number) and commented to say
      the PHP is canonical. It stays deliberately simpler — a dead deep link
      costs a click, a bad SMS costs money, so validation lives server-side.
      **Caveat carried from 8.3:** these are the GSM/3GPP rules. Beem does not
      document how *they* count on `/v1/send`, so this is our model of the cost
      and still needs reconciling against a real invoice.
- [ ] 8.5 **Sending pipeline.** One path for every send:
      resolve sender profile → normalize number (8.4) → opt-out check (8.11) →
      quiet-hours check (8.12) → segment count (8.4) → **reserve** credits
      (platform mode only) → `SendSmsJob` queued with tenant stamping (free via
      Phase 3.10) → driver submit → persist provider ids + status → **settle**:
      debit the reservation on acceptance, release it on pre-submission rejection.
      Per 8.0.2 this is **reserve-then-settle** — never debit at enqueue and hope.
      The reservation must be atomic against concurrent batches (row lock or
      conditional update on the cached balance) so two simultaneous sends cannot
      both spend the last credits; 8.25 tests exactly this double-spend.
      Own-provider failures take the 8.0.6 branch: terminal
      `failed_own_provider` status carrying the credit shortfall, so the UI can
      decide between offering the AZIMIOCRM retry and showing the
      no-platform-credits alert. Batches fan out into chunked jobs with a
      provider-friendly rate limit, and are resumable: a crashed batch restarts
      from the first non-terminal message instead of re-sending.

### B. Tenant-facing module

- [ ] 8.6 **SMS settings page** (Settings → SMS). Shows the active mode and lets
      the tenant admin **set up their own provider**: driver, sender name, API
      key/secret, with a **"Send test SMS"** button that must pass before the
      profile is saved as active. Shows their provider balance when the driver
      reports one. Switching back to platform mode is one click. In platform mode
      the page shows the `AZIMIOCRM` sender name (read-only), the credit balance,
      and the link to request credits.
- [ ] 8.7 **Compose single SMS.** From a person/lead/organization detail page
      ("Send SMS" beside the existing call/WhatsApp actions) and from a standalone
      compose screen. Live character/segment/credit counter, template picker,
      merge-field preview against the actual recipient, opt-out warning.
- [ ] 8.8 **Bulk send.** Build the audience from a DataGrid selection (persons or
      leads — the mass-action path, already tenant-scoped by Phase 3.11) or by
      pasting/importing numbers. Pre-send summary: recipients after dedupe,
      invalid numbers listed, opt-outs excluded, segments, **total credits and
      whether the balance covers it** — blocked with a clear message if not.
      Confirm step before anything is submitted. Failure handling follows the
      8.0.6 two-branch rule, and the per-segment cost (8.0.1) is spelled out in
      the summary so a long body never surprises anyone at confirmation time.
- [ ] 8.9 **Templates CRUD** with merge fields (`{{person.first_name}}`,
      `{{lead.title}}`, `{{user.name}}`, `{{tenant.name}}`, …), category, and a
      preview. Platform templates (`tenant_id = NULL`) are read-only for tenants,
      per the Phase 6.1 guard.
- [ ] 8.10 **Message log + batch history.** DataGrid of every message with status,
      recipient, cost, error and timestamps; filters by status/date/batch/user;
      CSV export; batch detail page with per-recipient breakdown and a **retry
      failed** action. Sent messages also land on the person/lead **activity
      timeline** so the SMS history sits with the rest of the conversation.
- [ ] 8.11 **Opt-out management.** Opt-out list UI (add/remove/import), automatic
      suppression at send time, and an unsubscribe instruction auto-appended to
      `marketing`-category messages (configurable text, en/sw). Honours
      `STOP`/`ACHA` replies once inbound is live (8.19).
- [ ] 8.12 **Compliance guardrails** — per-tenant **quiet hours** (default
      08:00–21:00 EAT) that defer `marketing` batches to the next window while
      `transactional` messages pass through; a per-tenant daily send cap; and a
      **consent note** on the bulk screen about TCRA rules and sender-ID
      registration. **Confirm the actual TCRA / operator requirements for bulk
      sender IDs in Tanzania before launch — treat the defaults here as a
      placeholder, not legal advice.**
- [ ] 8.13 **Credit request flow (tenant side).** "Request SMS credits" form
      (quantity, reason) → creates an `sms_credit_requests` row → notifies
      platform admins → tenant sees status and history, and is notified on
      approval/rejection. Plus **low-balance warnings** in the UI and a queued
      email when the balance crosses `sms_low_credit_threshold`.

### C. Platform-admin module (super panel)

- [ ] 8.14 **Platform SMS settings** at `/admin/super/sms`: configure the platform
      `AZIMIOCRM` sender profile and its provider credentials, show the platform's
      live provider balance, and set the default credit price for reference.
- [ ] 8.15 **Credit allocation + ledger.** Allocate/deduct/adjust credits for any
      tenant with a mandatory note; full append-only ledger view per tenant and
      platform-wide; **reconcile action** that recomputes `tenants.sms_credits`
      from the ledger and reports drift.
- [ ] 8.16 **Credit request queue.** Review pending requests: approve in full,
      approve partially, or reject with a note — approval writes the allocation
      ledger entry in the same transaction and notifies the tenant.
- [ ] 8.17 **Sender-name approval.** Tenant-submitted sender names land as
      `pending`; platform admin approves/rejects with a note (a sender name must be
      registered with the operator before it will deliver). A `pending` profile
      cannot send.
- [ ] 8.18 **Platform oversight**: cross-tenant message log (read-only, filterable
      by tenant/status/date), usage and spend per tenant per month, top senders,
      failure-rate alerting, and a global **kill switch** to pause all platform-mode
      sending (own-provider tenants keep working — it is their credit, not ours).

### D. Integrations & automation

- [ ] 8.19 **Delivery reports + inbound.** **REWRITTEN after reading Beem's docs
      in 8.3 — the original plan here was wrong.** It assumed a *signed
      delivery-report webhook*. Beem has neither:
      - **Delivery reports are POLLING ONLY.** Beem's REST SMS API has no
        delivery callback at all. Status comes from
        `GET /public/v1/delivery-reports?request_id=&dest_addr=`, **one request
        per recipient**, and their docs ask for a five-minute wait after
        submitting. So this is a **scheduled console command**, not a route:
        walk non-terminal messages older than the minimum age, poll per
        `(request_id, dest_addr)`, write `delivered_at`/`status`, and leave
        `PENDING` alone for the next pass. `BeemDriver::deliveryReports()`
        already does the fetching and status mapping. Mind the request cost:
        a 10,000-recipient batch is 10,000 polls, so batch size and backoff
        matter, and messages must stop being polled after
        `delivery_reports.max_age_hours`.
      - **Inbound/two-way IS a callback, but there is no signature scheme.**
        It is a separate Beem product needing a two-way number, and their
        security page offers only an unspecified "optionally validate the API
        key" — no HMAC, no documented header, no IP allowlist. **So an inbound
        callback cannot be authenticated as genuinely from Beem.** The route is
        therefore **disabled by default** (`messaging.webhooks.enabled = false`);
        enabling it means an unguessable path, HTTPS, and whatever network
        restriction the deployment can manage. Treat every payload as
        untrusted: log it to `sms_webhook_events` (including rejected ones —
        a run of them is what an attack looks like), process idempotently by
        `transaction_id`, resolve tenant from the sender profile that owns the
        number and never from the request body, and reply
        `{"transaction_id": "...", "successful": true}` as Beem expects.
        **Ask Beem directly for a real verification method before enabling
        this in production.**
- [ ] 8.20 **Notification channel + workflow action** — *this is the original 5.3.*
      A Laravel `sms` notification channel so existing notifications can ride SMS,
      wired to: lead assigned, activity reminder due, quote sent. Plus a
      **"Send SMS" workflow action** in the existing workflow builder (template +
      recipient resolution), so tenants can automate without code. Per-user
      opt-in for their own alert SMS.
- [ ] 8.21 **Later / nice-to-have** (explicitly out of scope for v1, listed so they
      are not forgotten): per-campaign sender choice and multiple concurrent sender
      profiles; scheduled recurring campaigns; Africa's Talking driver completed;
      SMS inside the Marketing Campaigns module; link shortening + click tracking;
      A/B bodies; contact segments saved as reusable audiences; WhatsApp Business
      Cloud API as a sibling channel under one "Messaging" umbrella (ties into 5.2);
      credit auto-purchase via mobile money.

### E. Cross-cutting (do not skip — Phase 3/6 lessons)

- [ ] 8.22 **Tenant isolation**: add every new table to `TenantTables` (strict vs
      shared as listed in 8.2), apply `BelongsToTenant` to every model, and verify
      the `ScopeDataGridToTenant` listener covers the new grids (they use raw
      `DB::table()` like all the others). **No tenant may ever read, send from, or
      bill against another tenant's sender profile, credits, log or opt-out list.**
- [ ] 8.23 **ACL + menu**: `sms` ACL keys (`sms`, `sms.create`, `sms.bulk`,
      `sms.templates`, `sms.settings`, `sms.logs`) in
      `packages/Webkul/Admin/src/Config/acl.php`, and a top-level **Messaging** menu
      entry in `menu.php` with sub-items — so a tenant can give an agent single-send
      rights without bulk-send or settings.
- [ ] 8.24 **en + sw translations** for every string, including provider error
      messages surfaced to users (Swahili first, per 5.7).
- [ ] 8.25 **Tests**: segment counting and phone normalization (unit); credit
      ledger arithmetic including the refund rule and concurrent-send double-spend
      (feature); cross-tenant isolation for profiles/credits/log/opt-outs; opt-out
      and quiet-hours suppression; webhook signature rejection; the `LogDriver`
      end-to-end send; super-panel allocation and approval access control.
      Extends the Phase 3.14 isolation suite rather than standing apart from it.
- [ ] 8.26 **Docs**: DEPLOYMENT.md gets the SMS section — provider setup, sender-ID
      registration steps, webhook URLs to whitelist, the queue worker requirement
      for sending, `APP_KEY` warning for encrypted credentials, and the credit
      reconciliation runbook.

**Suggested order:** ~~8.0~~ → ~~8.1~~ → ~~8.2~~ → ~~8.3 (drivers)~~ →
~~8.4 (phone + segments)~~ → **8.5 (next — sending pipeline)** → 8.6 → 8.7 →
8.10 → 8.14 →
8.15 → 8.17 → 8.8 → 8.9 → 8.11 → 8.13 → 8.16 → 8.12 → 8.19 → 8.18 → 8.20 →
8.22–8.26 continuously, not at the end.
Rationale: foundation first, then the narrowest useful slice (**one tenant sends
one SMS through the Log driver and sees it logged**), then platform credits so
platform mode works at all, then bulk, then automation. 8.22–8.26 (isolation,
ACL, translations, tests, docs) are written alongside each item — Phase 3 showed
that retrofitting tenant scoping is far more expensive than building it in.

---

### Suggested execution order
1 (rebrand — quick win, visible) → 2 (locale/currency) → 3 (tenancy — the big one,
do 3.1–3.4 first, then module-by-module scoping) → 4 → 6.1–6.4 → launch → 5 as
post-launch iterations (5.1 and 5.5 are worth pulling forward if quoting is a
day-one use case).

**Current state (2026-11-12):** Phases 0–4 and 6 are done; 5 and 7 have the
open items marked above. **Phase 8 (Text Messaging) is the active priority** and
runs ahead of the remaining Phase 5 and Phase 7 items.
