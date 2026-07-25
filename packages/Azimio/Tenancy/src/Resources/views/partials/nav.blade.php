@php($active = $active ?? 'tenants')

<div class="flex items-center gap-2 border-b border-gray-200 dark:border-gray-800">
    <a href="{{ route('super.tenants.index') }}"
        @class([
            'border-b-2 px-4 py-2 text-sm font-medium',
            'border-brandColor text-brandColor' => $active === 'tenants',
            'border-transparent text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white' => $active !== 'tenants',
        ])>
        @lang('azimio::app.users.nav-tenants')
    </a>

    <a href="{{ route('super.users.index') }}"
        @class([
            'border-b-2 px-4 py-2 text-sm font-medium',
            'border-brandColor text-brandColor' => $active === 'users',
            'border-transparent text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white' => $active !== 'users',
        ])>
        @lang('azimio::app.users.nav-users')
    </a>
</div>
