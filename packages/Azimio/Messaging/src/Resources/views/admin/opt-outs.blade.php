<x-admin::layouts>
    <x-slot:title>
        @lang('messaging::app.opt-outs.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-gray-300">
                @lang('messaging::app.opt-outs.title')
                <span class="text-sm font-normal text-gray-500">{{ number_format($total) }}</span>
            </div>

            <a href="{{ route('admin.sms.opt-outs.export') }}" class="text-xs text-blue-600">
                @lang('messaging::app.sms.log.export')
            </a>
        </div>

        <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            @lang('messaging::app.opt-outs.help')
        </div>

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        @if (session('invalid_numbers'))
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="mb-1 font-semibold">@lang('messaging::app.opt-outs.invalid-lines')</p>

                <ul class="list-inside list-disc font-mono text-xs">
                    @foreach (session('invalid_numbers') as $line)<li>{{ $line }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-2">
            <form method="POST" action="{{ route('admin.sms.opt-outs.store') }}"
                class="grid gap-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                @csrf

                <p class="text-base font-semibold text-gray-800 dark:text-white">
                    @lang('messaging::app.opt-outs.add-title')
                </p>

                <input type="text" name="dest_addr" required placeholder="0712 345 678"
                    value="{{ old('dest_addr') }}"
                    class="rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />

                <input type="text" name="reason" maxlength="255"
                    placeholder="@lang('messaging::app.opt-outs.reason')"
                    value="{{ old('reason') }}"
                    class="rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />

                <div>
                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
                        @lang('messaging::app.opt-outs.add')
                    </button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.sms.opt-outs.import') }}"
                class="grid gap-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                @csrf

                <p class="text-base font-semibold text-gray-800 dark:text-white">
                    @lang('messaging::app.opt-outs.import-title')
                </p>

                <textarea name="numbers" rows="4" required
                    placeholder="@lang('messaging::app.sms.bulk.numbers-placeholder')"
                    class="rounded-md border px-3 py-2 font-mono text-xs dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">{{ old('numbers') }}</textarea>

                <div>
                    <button type="submit" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold dark:border-gray-700 dark:text-gray-300">
                        @lang('messaging::app.opt-outs.import')
                    </button>
                </div>
            </form>
        </div>

        <form method="GET" action="{{ route('admin.sms.opt-outs.index') }}" class="flex gap-3">
            <input type="text" name="search" value="{{ $search }}"
                placeholder="@lang('messaging::app.sms.log.number')"
                class="w-64 rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />

            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
                @lang('messaging::app.sms.log.filter')
            </button>
        </form>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800">
                    <tr>
                        <th class="px-4 py-2">@lang('messaging::app.sms.log.number')</th>
                        <th class="px-4 py-2">@lang('messaging::app.opt-outs.source')</th>
                        <th class="px-4 py-2">@lang('messaging::app.opt-outs.reason')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.log.when')</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($optOuts as $optOut)
                        <tr class="border-b border-gray-100 dark:border-gray-800 dark:text-gray-300">
                            <td class="px-4 py-2 font-mono text-xs">{{ $optOut->dest_addr }}</td>

                            <td class="px-4 py-2 text-xs">
                                @lang('messaging::app.opt-outs.source-' . $optOut->source)
                            </td>

                            <td class="px-4 py-2 text-xs">{{ $optOut->reason }}</td>
                            <td class="px-4 py-2 text-xs text-gray-500">{{ $optOut->created_at?->diffForHumans() }}</td>

                            <td class="px-4 py-2 text-right">
                                <form method="POST" action="{{ route('admin.sms.opt-outs.destroy', $optOut->id) }}"
                                    onsubmit="return confirm('@lang('messaging::app.opt-outs.confirm-remove')')">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="text-xs text-red-600">
                                        @lang('messaging::app.opt-outs.remove')
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">
                                @lang('messaging::app.opt-outs.none')
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $optOuts->links() }}
    </div>
</x-admin::layouts>
