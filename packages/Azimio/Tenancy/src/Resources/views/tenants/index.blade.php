<x-admin::layouts>
    <x-slot:title>
        @lang('azimio::app.tenants.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <!-- Header -->
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-gray-300">
                @lang('azimio::app.tenants.title')
            </div>
        </div>

        <!-- Tabs -->
        @include('azimio::partials.nav', ['active' => 'tenants'])

        <!-- Create tenant -->
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">
                @lang('azimio::app.tenants.create-title')
            </p>

            <form method="POST" action="{{ route('super.tenants.store') }}" class="grid gap-3 md:grid-cols-5">
                @csrf

                <input type="text" name="name" required placeholder="@lang('azimio::app.tenants.company-name')" value="{{ old('name') }}"
                    class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />

                <input type="text" name="admin_name" required placeholder="@lang('azimio::app.tenants.admin-name')" value="{{ old('admin_name') }}"
                    class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />

                <input type="email" name="admin_email" required placeholder="@lang('azimio::app.tenants.admin-email')" value="{{ old('admin_email') }}"
                    class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />

                <input type="password" name="admin_password" required placeholder="@lang('azimio::app.tenants.admin-password')"
                    class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />

                <div class="flex gap-2">
                    <select name="locale"
                        class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                        @foreach (config('app.available_locales') as $code => $title)
                            <option value="{{ $code }}" @selected(old('locale', 'sw') == $code)>{{ $title }}</option>
                        @endforeach
                    </select>

                    <button type="submit" class="primary-button whitespace-nowrap">
                        @lang('azimio::app.tenants.create-btn')
                    </button>
                </div>
            </form>

            @if ($errors->any())
                <p class="mt-2 text-sm text-red-600">{{ $errors->first() }}</p>
            @endif
        </div>

        <!-- Tenants table -->
        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                <thead class="border-b border-gray-200 text-xs uppercase dark:border-gray-800">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">@lang('azimio::app.tenants.company-name')</th>
                        <th class="px-4 py-3">Slug</th>
                        <th class="px-4 py-3">@lang('azimio::app.tenants.locale')</th>
                        <th class="px-4 py-3">@lang('azimio::app.tenants.users')</th>
                        <th class="px-4 py-3">@lang('azimio::app.tenants.leads')</th>
                        <th class="px-4 py-3">@lang('azimio::app.tenants.persons')</th>
                        <th class="px-4 py-3">@lang('azimio::app.tenants.status')</th>
                        <th class="px-4 py-3">@lang('azimio::app.tenants.actions')</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($tenants as $tenant)
                        <tr class="border-b border-gray-200 dark:border-gray-800">
                            <td class="px-4 py-3">{{ $tenant->id }}</td>
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-white">{{ $tenant->name }}</td>
                            <td class="px-4 py-3">{{ $tenant->slug }}</td>
                            <td class="px-4 py-3">{{ config('app.available_locales')[$tenant->locale] ?? $tenant->locale }}</td>
                            <td class="px-4 py-3">{{ $tenant->users_count }}</td>
                            <td class="px-4 py-3">{{ $tenant->leads_count }}</td>
                            <td class="px-4 py-3">{{ $tenant->persons_count }}</td>
                            <td class="px-4 py-3">
                                @if ($tenant->isActive())
                                    <span class="rounded-full bg-green-100 px-2 py-1 text-xs text-green-800">@lang('azimio::app.tenants.active')</span>
                                @else
                                    <span class="rounded-full bg-red-100 px-2 py-1 text-xs text-red-800">@lang('azimio::app.tenants.suspended-label')</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('super.tenants.edit', $tenant->id) }}" class="secondary-button !px-3 !py-1 text-xs">
                                        @lang('azimio::app.tenants.edit-btn')
                                    </a>

                                    <form method="POST" action="{{ route('super.tenants.toggle', $tenant->id) }}">
                                        @csrf
                                        <button type="submit" class="secondary-button !px-3 !py-1 text-xs">
                                            {{ $tenant->isActive() ? trans('azimio::app.tenants.suspend-btn') : trans('azimio::app.tenants.activate-btn') }}
                                        </button>
                                    </form>

                                    @if ($tenant->isActive())
                                        <form method="POST" action="{{ route('super.tenants.impersonate', $tenant->id) }}">
                                            @csrf
                                            <button type="submit" class="secondary-button !px-3 !py-1 text-xs">
                                                @lang('azimio::app.tenants.impersonate-btn')
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-6 text-center">@lang('azimio::app.tenants.empty')</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin::layouts>
