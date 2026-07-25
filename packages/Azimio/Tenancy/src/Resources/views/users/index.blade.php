<x-admin::layouts>
    <x-slot:title>
        @lang('azimio::app.users.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <!-- Header -->
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-gray-300">
                @lang('azimio::app.users.title')
            </div>

            <a href="{{ route('super.users.create') }}" class="primary-button">
                @lang('azimio::app.users.add-btn')
            </a>
        </div>

        <!-- Tabs -->
        @include('azimio::partials.nav', ['active' => 'users'])

        <!-- Filters -->
        <form method="GET" action="{{ route('super.users.index') }}"
            class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <input type="text" name="search" value="{{ request('search') }}"
                placeholder="@lang('azimio::app.users.search')"
                class="w-64 rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />

            <select name="tenant_id"
                class="rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <option value="">@lang('azimio::app.users.all-tenants')</option>
                @foreach ($tenants as $tenant)
                    <option value="{{ $tenant->id }}" @selected(request('tenant_id') == $tenant->id)>{{ $tenant->name }}</option>
                @endforeach
            </select>

            <button type="submit" class="secondary-button">
                @lang('azimio::app.users.filter-btn')
            </button>
        </form>

        <!-- Users table -->
        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                <thead class="border-b border-gray-200 text-xs uppercase dark:border-gray-800">
                    <tr>
                        <th class="px-4 py-3">@lang('azimio::app.users.name')</th>
                        <th class="px-4 py-3">@lang('azimio::app.users.email')</th>
                        <th class="px-4 py-3">@lang('azimio::app.users.tenant')</th>
                        <th class="px-4 py-3">@lang('azimio::app.users.role')</th>
                        <th class="px-4 py-3">@lang('azimio::app.users.status')</th>
                        <th class="px-4 py-3">@lang('azimio::app.users.actions')</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($users as $user)
                        <tr class="border-b border-gray-200 dark:border-gray-800">
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-white">
                                {{ $user->name }}
                                @if ($user->is_super_admin)
                                    <span class="ml-1 rounded-full bg-purple-100 px-2 py-0.5 text-xs text-purple-800">@lang('azimio::app.users.super-admin')</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $user->email }}</td>
                            <td class="px-4 py-3">
                                {{ $user->tenant?->name ?? trans('azimio::app.users.no-tenant') }}
                            </td>
                            <td class="px-4 py-3">{{ $user->role?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($user->status)
                                    <span class="rounded-full bg-green-100 px-2 py-1 text-xs text-green-800">@lang('azimio::app.users.active')</span>
                                @else
                                    <span class="rounded-full bg-red-100 px-2 py-1 text-xs text-red-800">@lang('azimio::app.users.inactive')</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <a href="{{ route('super.users.edit', $user->id) }}" class="secondary-button !px-3 !py-1 text-xs">
                                        @lang('azimio::app.users.edit-btn')
                                    </a>

                                    <form method="POST" action="{{ route('super.users.destroy', $user->id) }}"
                                        onsubmit="return confirm('{{ trans('azimio::app.users.delete-confirm') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="secondary-button !px-3 !py-1 text-xs !text-red-600">
                                            @lang('azimio::app.users.delete-btn')
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center">@lang('azimio::app.users.empty')</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if ($users->hasPages())
            <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-300">
                <span>{{ $users->firstItem() }}–{{ $users->lastItem() }} / {{ $users->total() }}</span>

                <div class="flex gap-2">
                    @if ($users->onFirstPage())
                        <span class="secondary-button !px-3 !py-1 opacity-50">&laquo;</span>
                    @else
                        <a href="{{ $users->previousPageUrl() }}" class="secondary-button !px-3 !py-1">&laquo;</a>
                    @endif

                    @if ($users->hasMorePages())
                        <a href="{{ $users->nextPageUrl() }}" class="secondary-button !px-3 !py-1">&raquo;</a>
                    @else
                        <span class="secondary-button !px-3 !py-1 opacity-50">&raquo;</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-admin::layouts>
