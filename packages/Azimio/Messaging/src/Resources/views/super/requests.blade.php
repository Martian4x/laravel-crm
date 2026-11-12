<x-admin::layouts>
    <x-slot:title>
        @lang('messaging::app.requests.queue-title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-gray-300">
                @lang('messaging::app.requests.queue-title')

                @if ($pendingCount)
                    <span class="ml-2 rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">
                        {{ $pendingCount }}
                    </span>
                @endif
            </div>

            <a href="{{ route('super.sms.index') }}" class="text-xs text-blue-600">
                @lang('messaging::app.super.title')
            </a>
        </div>

        @include('azimio::partials.nav', ['active' => 'sms'])

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <form method="GET" action="{{ route('super.sms.requests.index') }}" class="flex gap-3">
            <select name="status" class="rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <option value="all" @selected($status === 'all')>@lang('messaging::app.sms.log.any-status')</option>
                @foreach ($statuses as $case)
                    <option value="{{ $case->value }}" @selected($status === $case->value)>
                        @lang('messaging::app.requests.status.' . str_replace('_', '-', $case->value))
                    </option>
                @endforeach
            </select>

            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
                @lang('messaging::app.sms.log.filter')
            </button>
        </form>

        <div class="flex flex-col gap-3">
            @forelse ($requests as $request)
                <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="font-semibold">{{ $request->tenant?->name ?? $request->tenant_id }}</p>

                            <p class="text-xs text-gray-500">
                                @lang('messaging::app.requests.requested'):
                                <strong>{{ number_format($request->credits_requested) }}</strong>
                                · {{ $request->requestedBy?->name }}
                                · {{ $request->created_at?->diffForHumans() }}
                            </p>
                        </div>

                        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs dark:bg-gray-800">
                            @lang('messaging::app.requests.status.' . str_replace('_', '-', $request->status->value))
                        </span>
                    </div>

                    @if ($request->reason)
                        <p class="mb-3 rounded-md bg-gray-50 px-3 py-2 text-xs dark:bg-gray-800">{{ $request->reason }}</p>
                    @endif

                    @if ($request->status->isDecided())
                        <p class="text-xs text-gray-500">
                            @lang('messaging::app.requests.granted'):
                            <strong>{{ number_format((int) $request->credits_granted) }}</strong>
                            @if ($request->reviewedBy)
                                · {{ $request->reviewedBy->name }}
                            @endif
                            @if ($request->admin_note)
                                · {{ $request->admin_note }}
                            @endif
                        </p>
                    @else
                        <form method="POST" action="{{ route('super.sms.requests.review', $request->id) }}"
                            class="flex flex-wrap items-end gap-2">
                            @csrf

                            <label class="text-xs text-gray-500">
                                @lang('messaging::app.requests.granted')

                                <input type="number" name="credits_granted" min="0"
                                    value="{{ $request->credits_requested }}"
                                    class="mt-1 w-32 rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                            </label>

                            <input type="text" name="admin_note" maxlength="500"
                                placeholder="@lang('messaging::app.super.note')"
                                class="w-64 rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />

                            <button type="submit" name="decision" value="approve"
                                class="rounded-md bg-emerald-600 px-4 py-2 text-xs font-semibold text-white">
                                @lang('messaging::app.requests.approve')
                            </button>

                            <button type="submit" name="decision" value="reject"
                                class="rounded-md border border-red-300 px-4 py-2 text-xs font-semibold text-red-700">
                                @lang('messaging::app.requests.reject')
                            </button>

                            <p class="w-full text-xs text-gray-500">
                                @lang('messaging::app.requests.partial-help')
                            </p>
                        </form>
                    @endif
                </div>
            @empty
                <div class="rounded-lg border border-gray-200 bg-white px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900">
                    @lang('messaging::app.requests.none')
                </div>
            @endforelse
        </div>

        {{ $requests->links() }}
    </div>
</x-admin::layouts>
