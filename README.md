<p align="center">
    <picture>
        <source media="(prefers-color-scheme: dark)" height="100" srcset="packages/Webkul/Admin/src/Resources/assets/images/dark-logo.svg">
        <source media="(prefers-color-scheme: light)" height="100" srcset="packages/Webkul/Admin/src/Resources/assets/images/logo.svg">
        <img alt="AzimioCustomers" height="100" src="packages/Webkul/Admin/src/Resources/assets/images/logo.svg">
    </picture>
</p>

# AzimioCustomers

**AzimioCustomers** is a multi-tenant CRM tailored for the Tanzanian market —
Swahili & English, Tanzanian Shilling (TZS), and multiple companies managed
under one installation.

It is a fork of [Krayin CRM](https://github.com/krayin/laravel-crm) v2.1
(Laravel 10 + Vue.js), rebranded and extended. See
[AZIMIO_TODO.md](AZIMIO_TODO.md) for the full roadmap and progress.

## Key differences from upstream Krayin

- **Multi-tenant**: single database, row-level tenancy (`tenant_id` on all
  business tables). Multiple companies share one domain/host; a platform
  super admin manages tenants.
- **Localization**: English and Swahili only; TZS as default currency;
  `Africa/Dar_es_Salaam` timezone.
- **Branding**: AzimioCustomers branding throughout; per-tenant custom
  branding (logo, colors) planned.
- **Privacy**: the upstream installer's call-home to `updates.krayincrm.com`
  (which reported the domain and admin contact) has been removed.

## Architecture notes

- Upstream code lives in `packages/Webkul/*`. The internal `Webkul\` PHP
  namespaces and `krayin/*` package names are **intentionally kept** so
  upstream fixes can still be merged. Only user-visible branding was changed.
- All AzimioCustomers-specific code lives in `packages/Azimio/*`
  (e.g. `Azimio\Tenancy`).
- The `2.1` branch tracks upstream; development happens on
  `azimiocustomers-dev`.

## Requirements

- PHP 8.2+, Composer
- MySQL 8 / MariaDB 10.4+
- Node.js 18+ (asset builds via Vite)

## Installation (development)

```bash
composer install
cp .env.example .env        # configure DB, APP_URL, etc.
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan db:seed --class="Azimio\Tenancy\Database\Seeders\DevTenantSeeder"
npm install && npm run build
php artisan serve
```

The dev tenant seeder creates two demo tenants (Twiga Traders Ltd, Kilimanjaro
Suppliers Co) and promotes the primary account to platform super admin.

## License

MIT — see [LICENSE](LICENSE). Based on Krayin CRM by Webkul, also MIT-licensed.
