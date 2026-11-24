<x-admin::layouts>
    <x-slot:title>
        @lang('services::app.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
            <div>
                <div class="text-xl font-bold dark:text-gray-300">@lang('services::app.title')</div>
                <div class="text-xs text-gray-500">@lang('services::app.subtitle')</div>
            </div>

            @if (bouncer()->hasPermission('services.create'))
                <a href="{{ route('admin.services.create') }}"
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
                    @lang('services::app.create')
                </a>
            @endif
        </div>

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <form method="GET" class="flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
            <input type="search" name="search" value="{{ $search }}"
                placeholder="{{ trans('services::app.search') }}"
                class="w-64 rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />

            <select name="status"
                class="rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <option value="">@lang('services::app.filter-all')</option>
                <option value="active" @selected($status === 'active')>@lang('services::app.filter-active')</option>
                <option value="inactive" @selected($status === 'inactive')>@lang('services::app.filter-inactive')</option>
            </select>

            <button type="submit" class="rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:text-gray-300">
                @lang('services::app.search')
            </button>
        </form>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            @if ($services->isEmpty())
                <div class="px-4 py-10 text-center text-sm text-gray-500">
                    <p class="font-semibold">
                        @lang($search !== '' || $status ? 'services::app.empty-search' : 'services::app.empty')
                    </p>

                    @if ($search === '' && ! $status)
                        <p class="mt-1 text-xs">@lang('services::app.empty-hint')</p>
                    @endif
                </div>
            @else
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800">
                        <tr>
                            <th class="px-4 py-3">@lang('services::app.name')</th>
                            <th class="px-4 py-3">@lang('services::app.code')</th>
                            <th class="px-4 py-3">@lang('services::app.price')</th>
                            <th class="px-4 py-3">@lang('services::app.status')</th>
                            <th class="px-4 py-3 text-right">@lang('services::app.actions')</th>
                        </tr>
                    </thead>

                    <tbody class="dark:text-gray-300">
                        @foreach ($services as $service)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                <td class="px-4 py-3">
                                    <div class="font-semibold">{{ $service->name }}</div>

                                    @if ($service->description)
                                        <div class="text-xs text-gray-500">{{ str($service->description)->limit(80) }}</div>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-gray-500">{{ $service->code ?: '—' }}</td>

                                <td class="px-4 py-3">
                                    {{ $service->priceLabel() }}

                                    @if ($service->is_taxable)
                                        <span class="ml-1 text-xs text-gray-500">+ VAT</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3">
                                    @if ($service->is_active)
                                        <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs text-emerald-800">@lang('services::app.status-active')</span>
                                    @else
                                        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-600">@lang('services::app.status-inactive')</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        @if (bouncer()->hasPermission('services.edit'))
                                            <a href="{{ route('admin.services.edit', $service->id) }}" class="text-blue-600">
                                                @lang('services::app.edit')
                                            </a>

                                            <form method="POST" action="{{ route('admin.services.toggle', $service->id) }}">
                                                @csrf
                                                <button type="submit" class="text-gray-600 dark:text-gray-400">
                                                    @lang($service->is_active ? 'services::app.deactivate' : 'services::app.activate')
                                                </button>
                                            </form>
                                        @endif

                                        @if (bouncer()->hasPermission('services.delete'))
                                            <form method="POST" action="{{ route('admin.services.delete', $service->id) }}"
                                                onsubmit="return confirm(@js(trans('services::app.delete-confirm')))">
                                                @csrf
                                                <button type="submit" class="text-red-600">
                                                    @lang('services::app.delete')
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        @if ($services->hasPages())
            <div>{{ $services->links() }}</div>
        @endif
    </div>
</x-admin::layouts>
