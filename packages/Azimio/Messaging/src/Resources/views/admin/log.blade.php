<x-admin::layouts>
    <x-slot:title>
        @lang('messaging::app.sms.log.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-gray-300">
                @lang('messaging::app.sms.log.title')
            </div>

            <div class="flex items-center gap-3 text-xs">
                <span>@lang('messaging::app.sms.log.total-messages'): <strong>{{ number_format($totals['messages']) }}</strong></span>
                <span>@lang('messaging::app.sms.compose.segments'): <strong>{{ number_format($totals['segments']) }}</strong></span>
                <span>@lang('messaging::app.sms.compose.credits'): <strong>{{ number_format($totals['credits']) }}</strong></span>

                <a href="{{ route('admin.sms.batches.index') }}" class="text-blue-600">
                    @lang('messaging::app.sms.log.batches')
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <form method="GET" action="{{ route('admin.sms.log.index') }}"
            class="grid gap-3 rounded-lg border border-gray-200 bg-white p-4 md:grid-cols-6 dark:border-gray-800 dark:bg-gray-900">

            <select name="status" class="rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <option value="">@lang('messaging::app.sms.log.any-status')</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>
                        @lang('messaging::app.sms.status.' . str_replace('_', '-', $status->value))
                    </option>
                @endforeach
            </select>

            <select name="batch_id" class="rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <option value="">@lang('messaging::app.sms.log.any-batch')</option>
                @foreach ($batches as $batch)
                    <option value="{{ $batch->id }}" @selected((string) $filters['batch_id'] === (string) $batch->id)>
                        #{{ $batch->id }} — {{ $batch->name }}
                    </option>
                @endforeach
            </select>

            <input type="text" name="number" value="{{ $filters['number'] }}" placeholder="@lang('messaging::app.sms.log.number')"
                class="rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />

            <input type="date" name="from" value="{{ $filters['from'] }}"
                class="rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />

            <input type="date" name="to" value="{{ $filters['to'] }}"
                class="rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />

            <div class="flex gap-2">
                <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
                    @lang('messaging::app.sms.log.filter')
                </button>

                <a href="{{ route('admin.sms.log.export', request()->query()) }}"
                    class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold dark:border-gray-700 dark:text-gray-300">
                    @lang('messaging::app.sms.log.export')
                </a>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800">
                    <tr>
                        <th class="px-4 py-2">@lang('messaging::app.sms.log.number')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.compose.message')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.log.status')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.compose.segments')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.compose.credits')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.log.when')</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($messages as $message)
                        <tr class="border-b border-gray-100 dark:border-gray-800 dark:text-gray-300">
                            <td class="px-4 py-2 font-mono text-xs">{{ $message->dest_addr }}</td>

                            <td class="px-4 py-2">
                                <span title="{{ $message->body }}">{{ str($message->body)->limit(48) }}</span>

                                @if ($message->batch_id)
                                    <a href="{{ route('admin.sms.batches.show', $message->batch_id) }}"
                                        class="ml-1 text-xs text-blue-600">#{{ $message->batch_id }}</a>
                                @endif
                            </td>

                            <td class="px-4 py-2">
                                <span class="rounded-full bg-gray-100 px-2 py-1 text-xs dark:bg-gray-800">
                                    @lang('messaging::app.sms.status.' . str_replace('_', '-', $message->status->value))
                                </span>

                                @if ($message->error_message)
                                    <p class="mt-1 text-xs text-red-600">{{ $message->error_message }}</p>
                                @endif
                            </td>

                            <td class="px-4 py-2">{{ $message->segments }}</td>
                            <td class="px-4 py-2">{{ $message->credits_charged }}</td>
                            <td class="px-4 py-2 text-xs text-gray-500">{{ $message->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">
                                @lang('messaging::app.sms.log.empty')
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $messages->links() }}
    </div>
</x-admin::layouts>
