<x-admin::layouts>
    <x-slot:title>
        @lang('messaging::app.sms.log.batch') #{{ $batch->id }}
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-gray-300">
                {{ $batch->name }}
                <span class="text-sm font-normal text-gray-500">#{{ $batch->id }}</span>
            </div>

            <a href="{{ route('admin.sms.batches.index') }}" class="text-xs text-blue-600">
                @lang('messaging::app.sms.log.batches')
            </a>
        </div>

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="grid gap-4 rounded-lg border border-gray-200 bg-white p-4 text-sm md:grid-cols-5 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div>
                <p class="text-xs text-gray-500">@lang('messaging::app.sms.log.status')</p>
                <p class="font-semibold">{{ $batch->status->value }}</p>
            </div>

            <div>
                <p class="text-xs text-gray-500">@lang('messaging::app.sms.log.recipients')</p>
                <p class="font-semibold">{{ number_format($batch->total_recipients) }}</p>
            </div>

            <div>
                <p class="text-xs text-gray-500">@lang('messaging::app.sms.log.delivered')</p>
                <p class="font-semibold">{{ number_format($batch->delivered_count) }}</p>
            </div>

            <div>
                <p class="text-xs text-gray-500">@lang('messaging::app.sms.log.failed')</p>
                <p class="font-semibold {{ $batch->failed_count ? 'text-red-600' : '' }}">
                    {{ number_format($batch->failed_count) }}
                </p>
            </div>

            <div>
                <p class="text-xs text-gray-500">@lang('messaging::app.sms.compose.credits')</p>
                <p class="font-semibold">{{ number_format($batch->credits_charged) }}</p>
            </div>
        </div>

        <div class="rounded-md bg-gray-50 p-3 text-sm dark:bg-gray-800 dark:text-gray-300">
            <p class="mb-1 text-xs font-semibold text-gray-500">@lang('messaging::app.sms.compose.message')</p>
            <p class="whitespace-pre-wrap">{{ $batch->body }}</p>
        </div>

        @if ($failedCount)
            {{--
                Decision 8.0.6, as a UI. The AZIMIOCRM offer appears only
                when the platform balance actually covers the retry; when it
                does not, there is no button at all, just the shortfall and
                where to fix it.
            --}}
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <p class="mb-3 font-semibold">
                    @lang('messaging::app.sms.log.failed-summary', ['count' => $failedCount])
                </p>

                <div class="flex flex-wrap items-center gap-3">
                    <form method="POST" action="{{ route('admin.sms.batches.retry', $batch->id) }}">
                        @csrf

                        <button type="submit" class="rounded-md border border-amber-400 px-4 py-2 text-xs font-semibold">
                            @lang('messaging::app.sms.log.retry-same')
                        </button>
                    </form>

                    @if ($mode->value === 'own')
                        @if ($platformCredits >= $retryableCredits)
                            <form method="POST" action="{{ route('admin.sms.batches.retry', $batch->id) }}">
                                @csrf
                                <input type="hidden" name="via_platform" value="1" />

                                <button type="submit" class="rounded-md bg-amber-600 px-4 py-2 text-xs font-semibold text-white">
                                    @lang('messaging::app.sms.errors.retry-via-platform', ['credits' => $retryableCredits])
                                </button>
                            </form>
                        @else
                            <span class="text-xs">
                                @lang('messaging::app.sms.errors.no-platform-credits', [
                                    'needed'    => $retryableCredits,
                                    'available' => $platformCredits,
                                ])

                                <a href="{{ route('admin.sms.settings.index') }}" class="font-semibold underline">
                                    @lang('messaging::app.sms.settings.title')
                                </a>
                            </span>
                        @endif
                    @endif
                </div>
            </div>
        @endif

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800">
                    <tr>
                        <th class="px-4 py-2">@lang('messaging::app.sms.log.number')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.log.status')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.compose.credits')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.log.error')</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($messages as $message)
                        <tr class="border-b border-gray-100 dark:border-gray-800 dark:text-gray-300">
                            <td class="px-4 py-2 font-mono text-xs">{{ $message->dest_addr }}</td>

                            <td class="px-4 py-2">
                                @lang('messaging::app.sms.status.' . str_replace('_', '-', $message->status->value))
                            </td>

                            <td class="px-4 py-2">{{ $message->credits_charged }}</td>

                            <td class="px-4 py-2 text-xs text-red-600">
                                {{ $message->error_message }}

                                @if ($message->credit_shortfall)
                                    <span class="block text-amber-700">
                                        @lang('messaging::app.sms.log.shortfall', ['credits' => $message->credit_shortfall])
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $messages->links() }}
    </div>
</x-admin::layouts>
