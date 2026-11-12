<x-admin::layouts>
    <x-slot:title>
        @lang('messaging::app.requests.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-gray-300">
                @lang('messaging::app.requests.title')
            </div>

            <a href="{{ route('admin.sms.settings.index') }}" class="text-xs text-blue-600">
                @lang('messaging::app.sms.settings.title')
            </a>
        </div>

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-6 rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div>
                <p class="text-xs text-gray-500">@lang('messaging::app.sms.settings.credit-balance')</p>
                <p class="text-2xl font-semibold">{{ number_format($balance) }}</p>
            </div>

            @if ($balance <= $threshold)
                <p class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    @lang('messaging::app.sms.settings.low-balance')
                </p>
            @endif
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <p class="mb-1 text-base font-semibold text-gray-800 dark:text-white">
                @lang('messaging::app.requests.new-title')
            </p>

            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                @lang('messaging::app.requests.new-help')
            </p>

            @if ($hasOpen)
                <p class="rounded-md bg-blue-50 px-3 py-2 text-sm text-blue-800">
                    @lang('messaging::app.requests.already-open')
                </p>
            @else
                <form method="POST" action="{{ route('admin.sms.credits.store') }}" class="grid gap-3 md:grid-cols-3">
                    @csrf

                    <input type="number" name="credits_requested" min="1" required
                        placeholder="@lang('messaging::app.requests.requested')"
                        value="{{ old('credits_requested') }}"
                        class="rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />

                    <input type="text" name="reason" maxlength="1000"
                        placeholder="@lang('messaging::app.requests.reason')"
                        value="{{ old('reason') }}"
                        class="rounded-md border px-3 py-2 text-sm md:col-span-2 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />

                    <div>
                        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
                            @lang('messaging::app.requests.submit')
                        </button>
                    </div>
                </form>
            @endif
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800">
                    <tr>
                        <th class="px-4 py-2">@lang('messaging::app.requests.requested')</th>
                        <th class="px-4 py-2">@lang('messaging::app.requests.granted')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.log.status')</th>
                        <th class="px-4 py-2">@lang('messaging::app.super.note')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.log.when')</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($requests as $request)
                        <tr class="border-b border-gray-100 dark:border-gray-800 dark:text-gray-300">
                            <td class="px-4 py-2">{{ number_format($request->credits_requested) }}</td>
                            <td class="px-4 py-2">{{ $request->credits_granted === null ? '—' : number_format($request->credits_granted) }}</td>

                            <td class="px-4 py-2">
                                <span class="rounded-full bg-gray-100 px-2 py-1 text-xs dark:bg-gray-800">
                                    @lang('messaging::app.requests.status.' . str_replace('_', '-', $request->status->value))
                                </span>
                            </td>

                            <td class="px-4 py-2 text-xs">{{ $request->admin_note }}</td>
                            <td class="px-4 py-2 text-xs text-gray-500">{{ $request->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">
                                @lang('messaging::app.requests.none')
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $requests->links() }}
    </div>
</x-admin::layouts>
