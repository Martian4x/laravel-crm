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
- [x] 7.2 **Email verification** — **DONE.** Migration
      `2026_11_19_000002` adds `users.email_verified_at` and
      `tenants.activated_at`; `VerifyEmailController` (notice, verify,
      resend), `VerifyTenantEmail` mail, a notice page, en/sw. 12 tests.
      **`activated_at` is a separate column, not another `status` value.** A
      pending tenant is a *third* state alongside active/suspended, and
      turning `status` into a string would have broken `isActive()`, the
      super panel and every existing row at once.
      **Existing tenants are backfilled as activated.** They predate
      verification and were vouched for; locking them out on deploy would be
      a far worse bug than the one this prevents. Verified: 0 unactivated
      tenants and 0 unverified users after the migration.
      **Behaviour change, deliberate:** registration no longer signs the
      admin in. The company is created pending and cannot use the CRM until
      the address is confirmed — signing them in regardless would make the
      check decorative. The old test asserting a dashboard redirect was
      rewritten rather than worked around.
      **A pending tenant gets the verification notice, not the "suspended"
      message.** They have not been refused, so a message they could do
      nothing about would be wrong. The verify routes sit outside the
      `tenant` middleware, so this cannot loop.
      Links are Laravel **signed** URLs, so an id cannot be edited to verify
      somebody else's account (tested), expire after seven days, and are
      idempotent because people forward these mails to themselves (tested).
      Resend is throttled and **gives the same answer whether or not the
      address exists**, so it cannot enumerate registered emails (tested).
      A super-admin-created tenant is vouched for and skips all of this.
      Original scope for reference:
- [ ] (original) 7.2 **Email verification**: registering admin must verify email before
      the tenant activates (tenant status `pending` until verified; en/sw
      verification email through the existing branded mail layout).
- [x] 7.3 **Terms + Privacy pages** at `/terms` and `/privacy` (en/sw), linked from
      the registration checkbox. **Content is a sensible template — must be reviewed
      by legal counsel before public launch (noted on the pages themselves).**
- [x] 7.4 **New-registration notification**: queued email to all active super admins
      (tenant, admin contact, language, link to the panel); failures logged, never
      block registration.
- [x] 7.5 **Trial defaults + expiry** — **DONE.** `tenants.trial_ends_at`
      added in the same migration; self-registration sets a trial from
      `config('azimio.trial_days', 14)`; `azimio:tenant:expire-trials`
      scheduled daily at 02:00 from the package's own provider.
      **Suspends rather than deletes, and records why** — the data survives
      if they pay, and the next admin can see the cause. A tenant with no
      `trial_ends_at` is on a real plan and is never touched (tested), and
      `--dry-run` reports without changing anything (tested).
      The suspended-login message already told them to contact sales (7.1).
      Original scope for reference:
- [ ] (original) 7.5 **Trial defaults + expiry**: self-registered tenants start on a
      `trial` plan (e.g. 14 days, `max_users` = 3); scheduler task suspends
      expired trials; suspended-login message tells them to contact sales.
      Needs `trial_ends_at` column on tenants.

### B. First login & onboarding

- [x] 7.6 **Onboarding checklist card** — **DONE.** `OnboardingChecklist`
      service, a card partial, a dismiss route, en/sw. 7 tests.
      **Every step is DERIVED from real data, never from a stored "done"
      flag.** The card cannot claim something is finished when it is not,
      and a step un-ticks itself if whatever satisfied it is removed — a
      flag would drift the first time somebody deleted their logo. Steps:
      logo uploaded, TIN set (the field the TRA actually asks for), a second
      user invited, and a first lead created.
      **Injected through Krayin's own `view_render_event` hook** rather than
      by editing the dashboard view, so an upstream merge cannot drop it or
      conflict with it.
      Hidden when every step is done **or** when dismissed: finishing should
      make it vanish by itself, and somebody who does not want it should not
      have to finish it to be rid of it. The dismissal lives in
      tenant-scoped `core_config` (3.8), so one company hiding it does not
      hide it from everybody — tested.
      **Bug found by the tests:** Krayin's `core_config` is just
      `(code, value)` with no `channel_code`/`locale_code` columns, and
      `core()->getConfigData()` caches per request and falls back to file
      config — neither correct for a per-tenant flag written in the same
      request. The flag is read straight from the table instead.
      Original scope for reference:
- [ ] (original) 7.6 **Onboarding checklist card** on the tenant dashboard until completed/
      dismissed: upload your logo → set Company Information (TIN/VRN) → invite
      your team → create your first lead. Each step links to the right page.
- [x] 7.7 **Super admin landing**: after login, super admins are redirected to
      `/admin/super/tenants` instead of the unscoped CRM dashboard.

### C. Daily-use gaps (found while walking the flows)

- [x] 7.8 **Super panel discoverability + impersonation UI**: "Tenants" link in the
      user dropdown (super admins only) and an amber banner on every page while
      impersonating — "You are logged in as {tenant} — Return to super admin"
      wired to `super.tenants.revert` (en/sw).
- [x] 7.9 **Plan editing in the super panel** — **DONE.** Plan name and
      `max_users` were already editable; this adds **trial end** editing and
      answers "why is this tenant suspended". 9 tests.
      Migration `2026_11_19_000003` adds `suspension_reason` and
      `suspended_at`. Suspending captures a reason (placeholder when none is
      given) and **reactivating clears it**, so a stale reason never hangs
      around on a working account.
      **`Tenant::state()` returns one word for what is actually going on** —
      active / trial / trial-expired / pending / suspended. A bare boolean
      left pending verification, a lapsed trial and a deliberate suspension
      looking identical while needing completely different responses.
      Suspension outranks the rest, because it is the thing to act on.
      On this screen a blank trial date means "no trial", not "leave it
      alone" — tested, since the two readings differ.
      **Pre-existing bug fixed:** `TenantController::update()` read
      `currency`, `plan` and `max_users` with `?:` on `nullable` fields, so
      any update omitting them raised "Undefined array key". Confirmed
      present at HEAD (introduced in `4d654756`). **Third instance of this
      exact bug class** after `260f6429` (locale) and `a9ca5baa` (batch
      name); swept the whole Tenancy package, and these three were the only
      remaining ones.
      Original scope for reference:
- [ ] (original) 7.9 **Plan editing in the super panel**: edit plan name / `max_users` /
      trial end per tenant; show why a tenant is suspended.
- [x] 7.10 **Per-tenant outgoing mail identity**: `MessageSending` listener keeps the
      platform from-address (SPF-safe) but sets the display name to the tenant's
      name whenever tenant context is active (covers queued mail via 3.10).
      Leftover `laravel@krayincrm.com` in `.env.example` fixed →
      `noreply@azimio.co.tz`. **Reply-to-the-sending-user deferred (sender not
      available in queued context; needs plumbing through mailables).**
- [ ] 7.11 **Human click-through regression** of create/edit flows per module
      — ⚠️ **NEEDS A PERSON. This one cannot be delegated to Claude or to
      the test suite**, and it is the last thing standing between "tested"
      and "known to work".
      What the automated suite does and does not cover, stated plainly:
      - `ModuleSmokeTest` renders all 25 module index pages for both a
        tenant admin and the super admin — **page rendering only**.
      - The 250-odd feature tests cover the mutations they were written for
        (tenancy, SMS, credits, registration, verification, plans) — but
        **not** create/edit on Leads, Contacts, Organizations, Products,
        Quotes, Activities or the Settings modules, where nobody has
        exercised the forms end to end since the tenancy rewrite.
      - A rendering test cannot catch a form that posts to the wrong route,
        a validation rule that rejects valid input, an attribute that does
        not save, or a datagrid filter that silently drops rows.
      **Suggested approach:** one pass per module as a *tenant admin* (not
      the super admin, whose unscoped context hides exactly the bugs
      tenancy introduces) — create a record, edit it, filter for it, delete
      it. Then repeat the create step as a second tenant and confirm the
      first tenant's record is invisible.
      Worth doing before any real customer, because the failures it would
      catch are the kind that make a CRM feel broken rather than throw.
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

- [x] 7.12 **Tenant data export** — **DONE.** `TenantExporter` builds a zip
      of CSVs (11 tables plus a README) streamed from the super panel. 4
      tests.
      **Deliberately raw `DB::table()` with an explicit `tenant_id`
      filter**, not the models: an export must include rows regardless of
      global scopes or anything a model hides, and it runs as a super admin
      with no tenant context for a scope to use anyway. The filter is
      written out every time rather than inferred.
      **Passwords, tokens and provider credentials are never exported** —
      the column is dropped from the header, not just blanked, and a test
      asserts the word `password` does not appear.
      **The table list is explicit, so adding one is a decision.**
      `core_config` is settings rather than data, `sms_sender_profiles`
      holds encrypted credentials that must never leave, and the
      operational `sms_*` tables are noise in an offboarding pack.
      Tables with no rows are omitted — an archive of empty files is
      harder to read, not more complete. JSON columns (a contact's emails,
      phone numbers) stay as JSON rather than being invented into columns,
      which would lose data.
      The archive is streamed then deleted: it is a full copy of one
      customer's data and has no business sitting in storage.
      **This makes the DEPLOYMENT.md data-residency promise true** — it
      previously said per-tenant export was possible via a `mysqldump`
      recipe, which is not something you hand a customer.
      Original scope for reference:
- [ ] (original) 7.12 **Tenant data export**: per-tenant Excel/CSV export (leads, persons,
      organizations, quotes, activities) — needed for offboarding requests and
      the DEPLOYMENT.md data-residency promise.
- [x] 7.13 **Tenant deletion with grace period** — **DONE.**
      `TenantEraser` plus an offboarding panel on the tenant edit page.
      7 tests.
      **This replaced an immediate, unguarded delete.** The old `destroy()`
      removed the tenant and its users on the spot, so one mis-click
      destroyed a customer's data with nothing in the way. Three things now
      are:
      1. **Still active?** Refused. Deleting an account somebody is using is
         almost always a mistake — suspending first is what starts the clock.
      2. **Grace period still running?** Refused, with the days remaining
         and the eligible date, so a wrong suspension can be undone by
         reactivating. Default 14 days via
         `config('azimio.deletion_grace_days')`.
      3. **Name not typed exactly?** Refused. A yes/no dialog is too easy to
         click through for something this final.
      `blockedReason()` returns a *reason* rather than a bool, so the UI can
      say which rule is in the way instead of greying a button out.
      **Row counts are taken BEFORE deleting** and logged with the actor —
      afterwards there is nothing to count, and "we deleted something" is
      not an audit record.
      File cleanup works through the rows that reference uploads before
      those rows disappear, since Phase 3.9 made upload paths entity-scoped
      rather than tenant-scoped so there is no single directory to drop. A
      missing file is logged and skipped: an orphan file is a smaller
      problem than a half-deleted tenant.
      **Bug found by the tests:** `daysRemaining()` lost a whole day to
      sub-second drift (`now()` advances between building `suspended_at` and
      measuring, so an exact 9-day gap read as 8.999 and truncated). Rounded
      up — a partial day is still a day of grace.
      Original scope for reference:
- [ ] (original) 7.13 **Tenant deletion with grace period**: suspend → export → delete
      (FK cascades already wipe child rows); confirmation flow in the super
      panel and file-storage cleanup for the tenant's uploads.

**Status (2026-11-19): Phase 7 is complete except 7.11**, which needs a
person rather than more code — see the note on that item for exactly what
the automated suite does and does not cover.

Original suggested order: 7.8 → 7.7 → 7.10 → 7.3 → 7.1 → 7.2 → 7.4 → 7.5 →
7.6 → 7.9 → 7.11 → 7.12 → 7.13.
Rationale: fix the sharp edges super admins hit today first, then the
registration funnel in dependency order (terms before the page, verification
before opening it up), then lifecycle.

---

## Phase 8 — Text Messaging (SMS) Module  ✅ **COMPLETE** (v1)

Added 2026-11-12, completed 2026-11-19. Supersedes the one-line 5.3
placeholder. Everything except 8.21 (explicitly deferred) is done and
tested; 8.17 was dropped by decision.

**Three things to settle before this bills real money:**

1. **Reconcile per-segment charging against a real Beem invoice.** Beem does
   not document how it counts segments on `/v1/send`; our figures come from
   their Multicountry product, a different API. See 8.0.1 and 8.26.
2. **Confirm TCRA / operator rules** for bulk sender IDs. The shipped quiet
   hours, unsubscribe notice and daily cap are a sensible starting point, not
   legal advice. See 8.12.
3. **Ask Beem for an inbound-callback verification method** before enabling
   that route. They define none, so the route ships disabled. See 8.19.

Smaller follow-ups: wire the three specific notification triggers (8.20),
per-user staff alert SMS (needs a phone column on `users`), and a native
Swahili review of the module strings (8.24).

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
- [x] 8.5 **Sending pipeline.** **DONE.** `CreditLedger`, `QuietHours`,
      `SendPlanner` (+ `SendPlan`, `PlannedRecipient`), `SmsSender`,
      `SendSmsJob`, `Reservation`. 22 tests across `SmsCreditLedgerTest` and
      `SmsPipelineTest`.
      **Reservation is a conditional UPDATE, not a read-then-write:**
      `UPDATE tenants SET sms_credits = sms_credits - n WHERE id = ? AND
      sms_credits >= n`. The database decides, so two batches racing for the
      last credits cannot both win — the loser matches zero rows. No lock is
      held across a round trip. The 8.25 double-spend case is tested here
      rather than deferred.
      **Crash safety:** dying between reserve and settle leaves credits held
      with no ledger row, so the cache sits *below* the ledger sum.
      `reconcile()` recomputes from the ledger and hands them back. Drift
      therefore always resolves in the tenant's favour, which is the right
      way to fail for something they paid for.
      **A release writes no ledger row at all** — per 8.0.2 nothing was ever
      charged, so a debit-and-refund pair would be audit noise, not
      information. Tested by asserting the row count is unchanged.
      **The plan the user approves is the object the pipeline executes**, so a
      quoted price cannot drift from the charged price. Gate order matters and
      is tested: normalize → dedupe → opt-out, because deduping or matching
      opt-outs on the *raw* string lets `0712 345 678` and `+255712345678`
      count as two people, or defeat a suppression outright.
      **Bug found by the tests, in the pipeline rather than the tests:**
      `$batch->senderProfile` returned null for every platform-mode send.
      `SenderProfile` is STRICT (8.2 correction 1) and the platform row has
      `tenant_id = NULL`, so the tenant scope hid it and the job failed
      everything as `sender_not_approved`. The relation on `Batch` and
      `Message` now drops the tenant scope — safe because the batch itself is
      tenant-scoped, so a tenant can only reach it through their own batch,
      and `credentials` stays in `$hidden`. This is the second time making
      that table STRICT has had a knock-on effect; worth remembering when
      anything else loads a profile.
      **Resumability is re-reading, not bookkeeping:** the job always queries
      which messages are still non-terminal instead of trusting a list
      captured at dispatch. A retried job therefore cannot re-charge or
      re-deliver, which is tested by running the job twice and asserting the
      balance and provider request ids are unchanged.
      **Still to wire (needs the UI items):** the 8.0.6 retry path is recorded
      on the message (`failed_own_provider` + `credit_shortfall`) but has no
      button yet (8.8/8.10); per-tenant quiet-hours overrides still read
      platform config (8.12); a deferred batch is marked `scheduled` with a
      delayed job but there is no scheduler sweep to pick up ones whose
      worker was down.
      Original scope for reference:
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

- [x] 8.6 **SMS settings page** — **DONE.** `SmsSettingsController`,
      `messaging::admin.settings`, four routes, `sms` + `sms.settings` ACL keys
      and a Messaging menu entry (partial 8.23), en/sw. 11 tests.
      **Verification replaces trust:** a submitted profile is saved as
      `pending`, then the credentials are used to call the provider. Where the
      driver can list sender names, the submitted one is checked against the
      account — an unregistered sender ID is rejected on *every* send, so
      finding out at setup beats finding out mid-campaign. Only if that passes
      does the profile become `approved` and the tenant switch to own mode. A
      failed verification leaves them on platform mode, tested.
      **Switching back to platform keeps the credentials** rather than deleting
      them: a tenant toggling back should not have to retype an API secret they
      may no longer have. Tested.
      **A test asserts the API key and secret never appear in the page HTML**
      (they are encrypted at rest and in `$hidden`, but the form must not echo
      them back either).
      The log driver is only offered as a provider outside production.
      **This undermines the premise of 8.17 — see the note there.**
      the tenant admin **set up their own provider**: driver, sender name, API
      key/secret, with a **"Send test SMS"** button that must pass before the
      profile is saved as active. Shows their provider balance when the driver
      reports one. Switching back to platform mode is one click. In platform mode
      the page shows the `AZIMIOCRM` sender name (read-only), the credit balance,
      and the link to request credits.
- [x] 8.7 **Compose single SMS.** **DONE.** `ComposeController` (index,
      preview, send), `MergeFields`, `messaging::admin.compose`, an **SMS**
      link beside the existing call/WA actions on every contact number,
      `sms.create` ACL key + menu entry, en/sw. 13 tests.
      **The live counter calls the server rather than reimplementing GSM-7 in
      JavaScript.** A debounced POST to `admin.sms.compose.preview` returns the
      canonical `SegmentCount`, merged preview, credits, balance, number
      validity and opt-out state. Decision 8.0.1 bills per segment, so the
      number on screen must be the number charged — a second copy of the
      segment rules in the browser is exactly what would drift from it. (The
      WhatsApp helper in 8.4 had to be a copy because it builds a URL with no
      server round trip; this one does not, so it is not.)
      **Merge fields render BEFORE counting**, because a merged name changes
      the length and therefore the price. Tested: a template that fits one
      segment becomes two once a long name is merged in.
      **An unknown placeholder renders as empty, never as raw
      `{{person.first_name}}`** — sending template syntax to a customer is
      worse than an awkward sentence and costs the same. Tested.
      Opt-out and invalid-number states surface in the preview and disable the
      send button, so they are seen before composing rather than after.
      **Two bugs found by the tests:** nested `{{ }}` in the merge-field
      buttons broke Blade compilation at render time (it compiled fine as a
      string, so only an actual page load caught it), and a test asserting
      "price the merged text" used a name 4 characters short of crossing the
      segment boundary — it would have passed for the wrong reason.
      Original scope for reference:
      ("Send SMS" beside the existing call/WhatsApp actions) and from a standalone
      compose screen. Live character/segment/credit counter, template picker,
      merge-field preview against the actual recipient, opt-out warning.
- [x] 8.8 **Bulk send.** **DONE.** `BulkController` (index, fromSelection,
      confirm, send), two views, **Send SMS mass actions on the Person and
      Lead DataGrids**, `sms.bulk` ACL key + menu entry, en/sw. 10 tests.
      **Three steps on purpose: compose → CONFIRM → send.** Billing is per
      segment (8.0.1), so a 2-segment body to 4,000 people is 8,000 credits.
      Nothing is written or reserved until the final POST — a test asserts
      zero messages and an unchanged balance after the confirmation screen
      has been drawn.
      **The confirmation re-plans at send time and refuses if the cost
      moved.** The audience, an opt-out or the balance may have changed since
      the screen was drawn, so the send compares against the figure the user
      actually confirmed and shows the screen again rather than charging
      something nobody agreed to. Tested with a deliberately stale figure.
      **Exclusions are itemised, not counted.** Duplicates, opt-outs and
      invalid numbers each get their own panel listing the offending input
      and the reason (a landline says "landline, cannot receive SMS"), so a
      user can fix their list rather than wonder where 47 contacts went.
      **Audience ids are re-resolved through the tenant-scoped models**, so a
      tampered form cannot reach another company's contacts — tested with a
      foreign tenant's person id, which simply does not resolve.
      **Bug found by the tests:** `$validated['name']` on a nullable field
      raised "Undefined array key" — the exact trap as the `locale` bug in
      `260f6429`. Swept the whole package for the pattern; this was the only
      instance, every other access is on a `required` field.
      Original scope for reference:
      leads — the mass-action path, already tenant-scoped by Phase 3.11) or by
      pasting/importing numbers. Pre-send summary: recipients after dedupe,
      invalid numbers listed, opt-outs excluded, segments, **total credits and
      whether the balance covers it** — blocked with a clear message if not.
      Confirm step before anything is submitted. Failure handling follows the
      8.0.6 two-branch rule, and the per-segment cost (8.0.1) is spelled out in
      the summary so a long body never surprises anyone at confirmation time.
- [x] 8.9 **Templates CRUD** — **DONE.** `TemplateController` (index, create,
      store, edit, update, destroy, preview), two views, `sms.templates` ACL
      key + menu entry, en/sw. 6 tests.
      **Platform templates are refused explicitly, not silently.**
      `BelongsToTenant` already blocks the write (Phase 6.1), but relying on
      its `return false` would leave a user clicking Save and seeing nothing
      happen — so edit/update/destroy check `isPlatformDefault()` and refuse
      with a reason. Tested on all three.
      **The list shows each template segment count**, from the same counter
      the pipeline bills with: a template is reused many times, so one
      careless extra segment is a recurring charge rather than a one-off.
      Original scope for reference: with merge fields (`{{person.first_name}}`,
      `{{lead.title}}`, `{{user.name}}`, `{{tenant.name}}`, …), category, and a
      preview. Platform templates (`tenant_id = NULL`) are read-only for tenants,
      per the Phase 6.1 guard.
- [x] 8.10 **Message log + batch history.** **DONE** (commit `cbc7bdf0`).
      `MessageLogController` (index, batches, show, retry, export), three
      views, `SmsActivityLogger`, `sms.logs` ACL key + menu entries, en/sw.
      6 tests that **render the real pages** — 8.7 shipped a Blade error that
      `compileString` did not catch and only a page load surfaced, so the
      lesson is applied here.
      **Built on Eloquent, not a raw DataGrid, deliberately.** Every other
      grid in this codebase uses `DB::table()` and therefore depends on the
      `ScopeDataGridToTenant` listener from Phase 3.11 to stay tenant-safe.
      These queries go through the `Message`/`Batch` models, so the
      `BelongsToTenant` global scope applies and the listing cannot leak
      across tenants **by construction** rather than by remembering to
      register a listener. This also satisfies that part of 8.22 for these
      screens.
      **The batch detail page is where 8.0.6 became a UI:** the "Send via
      AZIMIOCRM" button appears only when the platform balance actually
      covers the retry; otherwise there is no button at all, just the
      shortfall and a link to settings. A retry **reserves afresh**, because
      the originals were released when the messages failed (8.0.2) and a
      spent hold must never be reused.
      **Timeline entries are capped** at
      `messaging.activity.timeline_max_recipients` (default 10): a
      10,000-recipient campaign would write 10,000 entries and bury the real
      conversation on every contact it touched. Campaigns live in the batch
      history instead.
      CSV export shares the filter builder with the screen, so it returns the
      rows on display rather than a different set, and streams in chunks.
      Original scope for reference:
      recipient, cost, error and timestamps; filters by status/date/batch/user;
      CSV export; batch detail page with per-recipient breakdown and a **retry
      failed** action. Sent messages also land on the person/lead **activity
      timeline** so the SMS history sits with the rest of the conversation.
- [x] 8.11 **Opt-out management.** **DONE.** `OptOutController` (index,
      store, import, export, destroy), one view, `sms.opt_outs` ACL key +
      menu entry, en/sw. 7 tests.
      **Everything entering the list is normalised**, including imports —
      that is the whole point. A suppression stored as `0712 345 678` would
      not match a send addressed to `+255712345678`, and the contact would be
      messaged anyway. Tested, including that two spellings of one number
      collapse to a single row rather than tripping the unique index.
      **Import reports what happened to each line** (added / already there /
      unreadable) and hands back the unreadable ones so a user can fix their
      list instead of guessing.
      **Removal is deliberately possible**: a number added by mistake, or a
      customer asking to come back, has to be fixable. The SMS log still
      records every message ever sent.
      Cross-tenant isolation tested: one company never sees another list.
      **Still pending:** the auto-appended unsubscribe notice on marketing
      bodies (config key exists, not yet injected at send time) and
      `STOP`/`ACHA` handling, which needs inbound (8.19).
      Original scope for reference: Opt-out list UI (add/remove/import), automatic
      suppression at send time, and an unsubscribe instruction auto-appended to
      `marketing`-category messages (configurable text, en/sw). Honours
      `STOP`/`ACHA` replies once inbound is live (8.19).
- [x] 8.12 **Compliance guardrails** — **DONE.** Migration
      `2026_11_19_000001` adds four nullable columns to `tenants`;
      `QuietHours` became tenant-aware; `SendPlanner` appends the
      unsubscribe notice and computes the daily allowance; `SendPlan` gained
      `body`, `dailyCapRemaining` and `isSendable()`; settings page gained a
      Sending rules card. 15 tests.
      **NULL means "use the platform default", and that is not the same as
      0.** For the daily cap, 0 means *uncapped* while NULL means *inherit*
      — collapsing them would either silently stop a tenant sending or
      uncap one who never asked. Tested both ways.
      **The unsubscribe notice is appended BEFORE counting**, so the quoted
      price includes it. Appending at send time would have quietly added a
      segment to every marketing message *after* the user approved the cost
      — there is a test where 150 characters plus the notice crosses into a
      second segment. It is skipped when already present, so re-sending a
      template that contains it does not stack it up. Transactional bodies
      never get it: a quote reading "reply STOP" is not what the rule is for.
      **`SendPlan` now carries the final body**, and `dispatch()` takes it
      from there rather than as a separate argument. That removes the class
      of bug where the priced text and the sent text could diverge.
      **`dispatch()` guards on `isSendable()`** (recipients + credits + cap)
      rather than listing conditions at the call site, so a future guard
      added to the plan cannot be forgotten by a caller.
      The daily allowance counts only messages that actually reached the
      provider — something rejected before submission did not use it up.
      TCRA note surfaced on the settings card, still flagged as a starting
      point rather than legal advice.
      Original scope for reference:
- [ ] 8.12 (original) **Compliance guardrails** — per-tenant **quiet hours** (default
      08:00–21:00 EAT) that defer `marketing` batches to the next window while
      `transactional` messages pass through; a per-tenant daily send cap; and a
      **consent note** on the bulk screen about TCRA rules and sender-ID
      registration. **Confirm the actual TCRA / operator requirements for bulk
      sender IDs in Tanzania before launch — treat the defaults here as a
      placeholder, not legal advice.**
- [x] 8.13 **Credit request flow (tenant side).** **DONE.**
      `CreditRequestController`, `messaging::admin.credits`,
      `CreditRequestSubmitted` mail to all active super admins,
      `sms.credits` ACL key + menu entry, en/sw. Page shows the balance, a
      low-balance warning against the per-tenant threshold, the request form
      and the full history with each decision and its note.
      **One open request at a time** — a queue full of duplicates from
      someone clicking twice wastes an admin's attention. Tested.
      **Mail failures are logged and swallowed**: the request is already
      safely in the database, so a mail outage must not lose it.
      **Pending:** the *queued email* when a balance crosses the threshold.
      The warning is in the UI; the proactive email needs a scheduled check,
      which belongs with the 8.19 scheduler work.
      Original scope for reference:
      (quantity, reason) → creates an `sms_credit_requests` row → notifies
      platform admins → tenant sees status and history, and is notified on
      approval/rejection. Plus **low-balance warnings** in the UI and a queued
      email when the balance crosses `sms_low_credit_threshold`.

### C. Platform-admin module (super panel)

- [x] 8.14 **Platform SMS settings** — **DONE.** `PlatformSmsController`,
      `messaging::super.index`, an **SMS** tab in the existing super-panel nav,
      six routes, en/sw. Configures the NULL-tenant AZIMIOCRM profile (the one
      every platform-mode tenant sends through), shows its live provider
      balance, and lists per-tenant credit balance, ledger balance, drift,
      messages sent and credits spent.
      **Blank credential fields mean "keep the stored value"**, so editing the
      sender name does not silently wipe the API secret. Tested.
      A tenant user gets a 404 on every route here, including the allocation
      POST — tested, because a tenant granting themselves credits is the one
      failure that would actually cost money.
      Also delivers the cross-tenant message log from 8.18 early, since the
      data was already to hand.
      Original scope for reference:
      `AZIMIOCRM` sender profile and its provider credentials, show the platform's
      live provider balance, and set the default credit price for reference.
- [x] 8.15 **Credit allocation + ledger.** **DONE.** Allocate, purchase,
      adjust or expire credits for any tenant; per-tenant ledger page showing
      every movement with who made it and why; reconcile action. 11 tests
      across 8.14/8.15.
      **A note is mandatory on every movement** (server-side rule, tested).
      These rows are the evidence in a billing dispute, and "someone added
      5,000 credits in March" is not evidence. `created_by` is recorded too.
      **A deduction cannot drive the balance negative.** A tenant cannot owe
      credits, and a negative balance would make every reservation fail in a
      way nobody could diagnose — so the controller refuses and says what the
      balance actually is. Both the refusal and the allowed case are tested.
      **Drift is surfaced, not hidden.** The index lists cached vs ledger
      balance per tenant and flags any difference; the ledger page explains
      that drift is normally an abandoned reservation (a send that died
      between reserving and settling) and that reconciling returns those
      credits to the tenant. A test creates real drift with a stranded
      reservation and reconciles it.
      Original scope for reference:
      tenant with a mandatory note; full append-only ledger view per tenant and
      platform-wide; **reconcile action** that recomputes `tenants.sms_credits`
      from the ledger and reports drift.
- [x] 8.16 **Credit request queue.** **DONE.**
      `CreditRequestQueueController`, `messaging::super.requests` (with a
      pending badge and a status filter), `CreditRequests` service,
      `CreditRequestReviewed` mail to the requester. 13 tests across
      8.13/8.16.
      **The decision and the credits it grants are written in ONE
      transaction** (`CreditRequests::approve`). A request marked approved
      with nothing allocated, or credits granted with no record of who
      approved them, are both states nobody could explain in a billing
      dispute — so neither can happen halfway. The ledger row carries
      `reference_type`/`reference_id` pointing back at the request, and a
      test asserts that link.
      **Partial approval is a first-class outcome**, not all-or-nothing: the
      granted figure comes from the form, and granting less than asked
      records `partially_approved`. A tenant who asked for 5,000 and got
      1,000 sees that rather than a bare "approved". Tested.
      **A decided request cannot be decided again**, so a double submit
      cannot allocate twice — tested, because that one would quietly hand out
      free credits.
      A tenant posting to the review endpoint gets a 404 and nothing is
      allocated; that is tested specifically.
      Original scope for reference:
      approve partially, or reject with a note — approval writes the allocation
      ledger entry in the same transaction and notifies the tenant.
- [~] 8.17 **Sender-name approval — DROPPED by decision, 2026-11-12.**
      **Decided: tenants may NOT request custom sender names on the platform
      account.** Platform mode sends as the fixed `AZIMIOCRM` name and nothing
      else, so there is nothing for a human approval queue to approve, and no
      queue is being built.
      Why the item existed and why it no longer does: the original reasoning
      was "a sender name must be registered with the operator before it
      delivers, so a platform admin should approve it". But for a tenant on
      their **own** provider the name is registered on *their* account under
      *their* operator relationship — the platform is not party to it, so a
      human clicking approve here would assert nothing. 8.6 already does
      something strictly better: it queries the tenant's own provider for the
      names on that account and refuses to activate one that is not there.
      Automatic, immediate, and actually verified.
      **What this decision also avoids:** per-tenant sender-ID registration
      fees, a registration workflow with the operator, and the platform
      carrying the operator relationship for names it does not control.
      **Nothing is removed.** `sms_sender_profiles.status`, the
      `SenderProfileStatus` enum and the `canSend()` gate stay and are load
      bearing: 8.6 writes `pending` → `approved` on verification, and 8.5
      refuses to send from a profile that is not approved (tested). The
      platform profile is approved once in 8.14.
      **Revisit only if** the product later offers tenants branded sender
      names on the platform account — at which point this item returns in
      full, along with the fees and workflow above.
- [x] 8.18 **Platform oversight** — **DONE.** Kill switch, failure-rate
      alerting, monthly usage, cross-tenant log (delivered early in 8.14).
      8 tests.
      **The kill switch reuses an enforced gate rather than adding a new
      one.** Pausing sets the platform sender profile to
      `SenderProfileStatus::Disabled`, which `SendSmsJob` already refuses to
      send from and `SendPlan::isPlatformPaused()` now reports — so the
      switch cannot drift out of step with what is actually enforced, which
      a separate boolean setting could. Folded into `isSendable()`, so it is
      the one gate again.
      **Own-provider tenants keep sending while platform is paused**, and
      that is tested: it is their credit and their provider account, not
      ours to switch off.
      **`azimio:sms:check-failure-rate`**, hourly, alerts platform admins
      when a tenant's outbound failure rate crosses a threshold. A failing
      integration is otherwise invisible — messages fail and the tenant
      quietly concludes "SMS does not work". **A minimum volume is
      required** before any rate is reported: one failure out of two is 50%
      and means nothing, and alerting on it would make the alert worthless.
      Throttled to one alert per tenant per day, since a provider outage
      would otherwise mail on every run.
      Monthly messages/credits for the last six months on the super page.
      **Bug found while wiring:** the command's private `alert()` collided
      with Laravel's public `Command::alert()`, which PHP refused outright.
      Renamed to `sendAlert()`.
      Original scope for reference:
- [ ] 8.18 (original) **Platform oversight**: cross-tenant message log (read-only, filterable
      by tenant/status/date), usage and spend per tenant per month, top senders,
      failure-rate alerting, and a global **kill switch** to pause all platform-mode
      sending (own-provider tenants keep working — it is their credit, not ours).

### D. Integrations & automation

- [x] 8.19 **Delivery reports + inbound.** **DONE**, built to the corrected
      plan rather than the original one. 15 tests.
      **Delivery reports: `azimio:sms:poll-delivery-reports`**, scheduled
      every ten minutes from the package's own provider (so an upstream
      merge cannot drop the schedule). Beem has no delivery callback, so
      this polls `(request_id, dest_addr)` — one request per recipient,
      which is the cost to respect. Guards: only non-terminal submitted
      rows; nothing younger than Beem's five-minute minimum; **messages
      older than `max_age_hours` are abandoned**, because polling them
      forever costs a request each and will never resolve; `--limit` caps
      each run. `PENDING` is left alone for the next pass. Batch counters
      are recomputed from the rows after any change.
      **Inbound: one route, disabled by default.** Beem defines no
      signature scheme, so a request here cannot be proven to come from
      them. Every payload is logged to `sms_webhook_events` **before** it is
      trusted — rejected ones included, since a run of them is what a forged
      callback looks like. A configured shared secret is compared with
      `hash_equals`; processing is idempotent on `transaction_id` because
      Beem retries; and **tenant context is resolved from the sender profile
      that owns the receiving number, never from the request body.** A
      callback for an unowned number is logged and ignored. Replies to
      `STOP`, `ACHA`, `SITAKI` or `UNSUBSCRIBE` write an opt-out with source
      `stop_reply`, which closes the last gap in 8.11.
      `phpunit.xml` enables the route for tests only; production default
      stays off.
      **Also completes 8.13's pending item:**
      `azimio:sms:notify-low-balance`, scheduled daily, emails platform-mode
      tenants at or below their threshold. Throttled to once a week per
      tenant — a daily mail until they top up trains people to ignore it.
      Own-provider tenants are skipped: their balance sits at their provider
      and is not ours to watch.
      **Still open:** ask Beem for a real inbound verification method before
      enabling that route in production.
      Original scope for reference:
- [ ] 8.19 (original) **Delivery reports + inbound.** **REWRITTEN after reading Beem's docs
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
- [x] 8.20 **Notification channel + workflow action** — *the original 5.3.*
      **DONE.** `SmsChannel` registered as the `sms` notification driver,
      `SmsNotification` message object, `WorkflowSms` service, and a
      **Send SMS to contact** action on the Lead workflow builder. 8 tests.
      **Both routes go through `SmsSender`, never a driver directly.** An
      automated send is still a send: normalised, opt-out checked,
      quiet-hours checked, segment counted, charged and logged exactly like
      a hand-written one. A channel or workflow that bypassed the pipeline
      would be **the one path that can message somebody who opted out** —
      so there is a test for precisely that on each route.
      **Failures are swallowed with a log line, never thrown.** A blocked
      SMS must not break the thing that triggered it: a lead assignment
      must still save, and a workflow must still run its other actions.
      Notifications default to `transactional`, so quiet hours do not hold
      them — a reminder that arrives twelve hours late is not a reminder.
      **Only one line was added to the upstream Automation package** (the
      action, delegating to `Azimio\Messaging\Services\WorkflowSms`), to
      keep future Krayin merges cheap.
      **Deferred, with reasons:** the three specific triggers (lead
      assigned, activity reminder, quote sent) need `Notifiable` on
      Krayin's own models and notification classes that do not exist yet —
      the channel they would use is done and tested, so wiring each is a
      small, separate change. Per-user alert SMS needs a phone column on
      `users` plus an opt-in, which is a schema change for a different
      audience (staff, not customers) and is better scoped on its own.
      Original scope for reference:
- [ ] 8.20 (original) **Notification channel + workflow action**
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

- [x] 8.22 **Tenant isolation** — **DONE, continuously.** All nine SMS tables
      registered in `TenantTables` (8.2); `BelongsToTenant` on every model
      except `WebhookEvent`, which is platform-only by design;
      `TenantTables::PLATFORM_ONLY` added for it.
      **The new grids avoid the `ScopeDataGridToTenant` dependency entirely**
      by using Eloquent rather than raw `DB::table()` (8.10), so they cannot
      leak by construction rather than by remembering a listener.
      Cross-tenant isolation is asserted in `SmsSchemaTest`,
      `SmsCreditLedgerTest`, `SmsBulkTest`, `SmsTemplateOptOutTest`,
      `SmsPlatformAdminTest` and `SmsCreditRequestTest` — including the two
      that would cost money: a tenant cannot allocate itself credits, and a
      tenant cannot approve its own credit request.
      **Two knock-ons from making `sms_sender_profiles` STRICT were found and
      fixed:** `azimio:tenant:adopt` would have hijacked the platform profile
      (8.2), and `$batch->senderProfile` returned null for every platform
      send (8.5).
- [ ] (original) 8.22 **Tenant isolation**: add every new table to `TenantTables` (strict vs
      shared as listed in 8.2), apply `BelongsToTenant` to every model, and verify
      the `ScopeDataGridToTenant` listener covers the new grids (they use raw
      `DB::table()` like all the others). **No tenant may ever read, send from, or
      bill against another tenant's sender profile, credits, log or opt-out list.**
- [x] 8.23 **ACL + menu** — **DONE, continuously.** Seven ACL keys added as
      each screen landed: `sms`, `sms.create`, `sms.bulk`, `sms.credits`,
      `sms.templates`, `sms.opt_outs`, `sms.logs`, `sms.settings`, each
      listing every route it guards. Matching **Messaging** menu with
      sub-items, plus an **SMS** tab in the super panel.
      This is the split the item asked for: a company can give an agent
      single-send rights (`sms.create`) without bulk sends (`sms.bulk`) or
      access to the provider credentials (`sms.settings`).
- [ ] (original) 8.23 **ACL + menu**: `sms` ACL keys (`sms`, `sms.create`, `sms.bulk`,
      `sms.templates`, `sms.settings`, `sms.logs`) in
      `packages/Webkul/Admin/src/Config/acl.php`, and a top-level **Messaging** menu
      entry in `menu.php` with sub-items — so a tenant can give an agent single-send
      rights without bulk-send or settings.
- [x] 8.24 **en + sw translations** — **DONE, continuously.** Every string in
      the module exists in both locales, added with the feature rather than
      retrofitted: UI, validation, flash messages, mail templates, provider
      error messages surfaced to users, and the five phone-rejection reasons.
      Swahili written as the primary voice per 5.7, not glossed from English.
      **Still worth a native-speaker review** before launch, same caveat as
      2.3 — these are competent translations, not reviewed ones.
- [ ] (original) 8.24 **en + sw translations** for every string, including provider error
      messages surfaced to users (Swahili first, per 5.7).
- [x] 8.25 **Tests** — **DONE, continuously.** 13 SMS test files, part of the
      244-test / 728-assertion suite. Everything the item asked for is
      covered: segment counting and phone normalization (unit-style), credit
      ledger arithmetic **including the refund rule and the concurrent-send
      double-spend**, cross-tenant isolation for profiles/credits/log/
      opt-outs, opt-out and quiet-hours suppression, the `LogDriver`
      end-to-end send, and super-panel allocation and approval access
      control.
      **Webhook signature rejection** is tested as far as it can be: Beem
      defines no signature scheme (8.19), so the test covers the shared
      secret this module adds instead.
      The tests have been earning their place — they caught the
      `$batch->senderProfile` platform bug (8.5), the trailing-slash and
      swallowed-exception bugs in `BeemDriver` (8.3), the nested-Blade render
      error (8.7), and a repeat of the nullable-key crash (8.8).
- [ ] (original) 8.25 **Tests**: segment counting and phone normalization (unit); credit
      ledger arithmetic including the refund rule and concurrent-send double-spend
      (feature); cross-tenant isolation for profiles/credits/log/opt-outs; opt-out
      and quiet-hours suppression; webhook signature rejection; the `LogDriver`
      end-to-end send; super-panel allocation and approval access control.
      Extends the Phase 3.14 isolation suite rather than standing apart from it.
- [x] 8.26 **Docs** — **DONE.** DEPLOYMENT.md gains a Text Messaging section
      and nine SMS items on the post-deploy checklist.
      Covers: provider setup and sender-ID registration, the environment
      block, **`APP_KEY` as backup-critical** (lose it and every stored
      credential is unrecoverable), the queue worker and the three scheduled
      commands with a table and the reason polling exists at all, the
      per-recipient polling cost, inbound webhooks with the warning that Beem
      defines no signature scheme, the credit reconciliation runbook, the
      kill switch, per-company settings, and the TCRA note.
      **Two warnings are deliberately prominent** rather than buried: back up
      `APP_KEY` separately, and **reconcile credit billing against a real
      Beem invoice before charging anyone per segment**, because Beem does
      not document how it counts segments on `/v1/send`.
- [ ] (original) 8.26 **Docs**: DEPLOYMENT.md gets the SMS section — provider setup, sender-ID
      registration steps, webhook URLs to whitelist, the queue worker requirement
      for sending, `APP_KEY` warning for encrypted credentials, and the credit
      reconciliation runbook.

**Suggested order:** ~~8.0~~ → ~~8.1~~ → ~~8.2~~ → ~~8.3 (drivers)~~ →
~~8.4~~ → ~~8.5~~ → ~~8.6~~ → ~~8.7~~ → ~~8.10~~ → ~~8.14~~ → ~~8.15 (credits)~~ →
~~8.13 + 8.16~~ → ~~8.8 (bulk)~~ → ~~8.17 (dropped by decision)~~ →
~~8.9~~ → ~~8.11~~ → ~~8.12~~ → ~~8.19~~ → ~~8.18~~ → ~~8.20~~ → ~~8.26 (docs)~~ → ~~8.22–8.25 (done continuously)~~ →
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

**Current state (2026-11-19):** Phases 0–4, 6 and **8 are done**; 5 and 7
have the open items marked above. Phase 8 superseded 5.3 entirely.

**Phases 0–8 are now done**, apart from two things that are not code:
**7.11**, the human click-through regression (needs a person), and the three
pre-launch items at the top of Phase 8 (reconcile Beem segment billing
against a real invoice, confirm TCRA rules, ask Beem for an inbound
verification method).

Everything still open is deferred by choice: **8.21** (SMS nice-to-haves),
**5.6** (low-bandwidth audit), the Phase 5 refinements, and a native
Swahili review of the newer strings.

---

## Phase 9 — Profile fields, Services, SMS setup retention

Added 2026-11-24 from direct feedback. Writing 9.2 turned up the reason the
TIN/VRN request was made at all: **the fields were shipped but have never
existed on any installed system.** That is 9.1, and it must land first or
anything else added the same way will vanish the same way.

### 9.1 Attributes added by migration are deleted at install — **BUG**

`krayin-crm:install` runs `migrate:fresh` (Installer.php:70), which applies
`2026_07_22_000006_add_tanzania_market_attributes` and inserts
`payment_method`, `payment_reference`, `tin` and `vrn`. It then runs the
seeders (line 73), and `AttributeSeeder.php:19` does
`DB::table('attributes')->delete()` before inserting Krayin's base 42.

So the migration is recorded as run and its rows are gone. Verified on
production az1 and locally: migration present, **0 of the 4 attributes**,
exactly 42 rows. 5.4 (mobile money on quotes) and 5.5 (TRA TIN/VRN) are
both ticked and neither has ever worked on a fresh install. The quote PDF
header documented in DEPLOYMENT.md has had nothing to print.

A second migration cannot fix this: on a fresh install migrations run
*before* the seeder, so it would be deleted too.

- [x] 9.1.1 `AzimioAttributeSeeder` — every Azimio-added attribute in one
      place, idempotent via `updateOrInsert` keyed on `(code, entity_type)`,
      safe to run any number of times.
- [x] 9.1.2 Run it on `krayin.installed` (the event Installer.php:315 already
      dispatches) so a fresh install gets the attributes *after* the seeder
      has finished deleting.
- [x] 9.1.3 A migration that calls the same seeder, to repair databases that
      already exist — production az1 among them.
- [x] 9.1.4 A test that fails if the attributes are missing, so this cannot
      regress silently a third time.

### 9.2 Contact and company profile fields

- [x] 9.2.1 **Organizations**: `phone`, `phone2`, `email`, `tin`, `vrn`,
      `business_type` (select), `location`. The table today has only
      `name`, `address` and `user_id` — a company profile with no phone
      number or email at all.
- [x] 9.2.2 **Business type** options for the TZ market, seeded as
      `attribute_options` and translatable.
- [x] 9.2.3 **Persons**: NOT adding `phone`/`phone2`. Persons already have
      `contact_numbers`, a multi-value phone field, and it is what the whole
      SMS module reads (ComposeController, BulkController, WorkflowSms,
      SmsChannel, InboundController). A parallel pair of fields would be a
      second source of truth that every SMS path silently ignores. Relabel
      and make sure two numbers are easy to enter instead.
      **Flagged for review — say the word and they get added properly,
      meaning SMS recipient resolution reads them too.**

### 9.3 Staff phone numbers

- [~] 9.3.1 `users.phone` + `users.phone2` as real columns (users is not an
      attribute entity), on the user create/edit form and profile.
- [ ] 9.3.2 This unblocks the per-user staff alert SMS left over from 8.20,
      which was blocked on exactly this column.

### 9.4 Services module

- [ ] 9.4.1 A Services module alongside Products: name, description, code,
      price, unit (hour/day/month/fixed), taxable, active.
- [ ] 9.4.2 Services are sellable on quotes the same way products are.
- [ ] 9.4.3 Tenant-scoped (`TenantTables::STRICT`), ACL keys, en/sw strings,
      DataGrid, tests — the Phase 3/6 checklist, not retrofitted.

### 9.5 SMS provider setup is re-asked every time — **BUG**

`SmsSettingsController::store()` validates `api_key` and `secret_key` as
`required` on every save, and the credentials are `$hidden` on the model, so
the form cannot show them back. Changing only a sender name means retyping
an API secret the tenant may not have kept.

- [x] 9.5.1 Keep the stored credentials when the fields are left blank;
      require them only when there is no profile yet, or the driver changed.
- [x] 9.5.2 Show that credentials are saved (masked, never the value) with a
      "replace" affordance, so the state is obvious.
- [x] 9.5.3 Re-verify against the provider only when something that affects
      authentication actually changed — not on every unrelated save.

### 9.6 Carried forward

- [ ] 9.6.1 5.1 refinement: TZ-format (07XX/06XX) client-side rule, +255 prefix.
- [ ] 9.6.2 5.5 refinement: auto-calculate 18% VAT toggle on the quote form.
- [ ] 9.6.3 5.7 refinement: audit date display for `dd/mm/yyyy` consistency.
- [ ] 9.6.4 5.6 low-bandwidth audit.
