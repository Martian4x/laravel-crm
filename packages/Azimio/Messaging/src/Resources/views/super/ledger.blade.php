<x-admin::layouts>
    <x-slot:title>
        @lang('messaging::app.super.ledger') — {{ $tenant->name }}
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-gray-300">
                {{ $tenant->name }}
                <span class="text-sm font-normal text-gray-500">@lang('messaging::app.super.ledger')</span>
            </div>

            <a href="{{ route('super.sms.index') }}" class="text-xs text-blue-600">
                @lang('messaging::app.super.title')
            </a>
        </div>

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <div class="flex flex-wrap items-center gap-6 rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div>
                <p class="text-xs text-gray-500">@lang('messaging::app.super.cached-balance')</p>
                <p class="text-lg font-semibold">{{ number_format($cached) }}</p>
            </div>

            <div>
                <p class="text-xs text-gray-500">@lang('messaging::app.super.ledger-balance')</p>
                <p class="text-lg font-semibold">{{ number_format($true) }}</p>
            </div>

            <div>
                <p class="text-xs text-gray-500">@lang('messaging::app.super.drift')</p>
                <p class="text-lg font-semibold {{ $true !== $cached ? 'text-amber-700' : '' }}">
                    {{ $true - $cached }}
                </p>
            </div>

            <form method="POST" action="{{ route('super.sms.reconcile', $tenant->id) }}">
                @csrf

                <button type="submit" class="rounded-md border border-gray-300 px-4 py-2 text-xs font-semibold dark:border-gray-700 dark:text-gray-300">
                    @lang('messaging::app.super.reconcile')
                </button>
            </form>
        </div>

        @if ($true !== $cached)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                @lang('messaging::app.super.drift-help')
            </div>
        @endif

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800">
                    <tr>
                        <th class="px-4 py-2">#</th>
                        <th class="px-4 py-2">@lang('messaging::app.super.type')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.compose.credits')</th>
                        <th class="px-4 py-2">@lang('messaging::app.super.balance-after')</th>
                        <th class="px-4 py-2">@lang('messaging::app.super.by')</th>
                        <th class="px-4 py-2">@lang('messaging::app.super.note')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.log.when')</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($entries as $entry)
                        <tr class="border-b border-gray-100 dark:border-gray-800 dark:text-gray-300">
                            <td class="px-4 py-2 text-xs text-gray-500">{{ $entry->id }}</td>

                            <td class="px-4 py-2">
                                <span class="rounded-full bg-gray-100 px-2 py-1 text-xs dark:bg-gray-800">
                                    {{ $entry->type->value }}
                                </span>
                            </td>

                            <td class="px-4 py-2 font-semibold {{ $entry->credits < 0 ? 'text-red-600' : 'text-emerald-700' }}">
                                {{ $entry->credits > 0 ? '+' : '' }}{{ number_format($entry->credits) }}
                            </td>

                            <td class="px-4 py-2">{{ number_format($entry->balance_after) }}</td>
                            <td class="px-4 py-2 text-xs">{{ $entry->createdBy?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-xs">{{ $entry->note }}</td>
                            <td class="px-4 py-2 text-xs text-gray-500">{{ $entry->created_at?->toDayDateTimeString() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">
                                @lang('messaging::app.super.no-entries')
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $entries->links() }}
    </div>
</x-admin::layouts>
