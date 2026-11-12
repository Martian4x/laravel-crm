<x-admin::layouts>
    <x-slot:title>
        @lang('messaging::app.sms.log.batches')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-gray-300">
                @lang('messaging::app.sms.log.batches')
            </div>

            <a href="{{ route('admin.sms.log.index') }}" class="text-xs text-blue-600">
                @lang('messaging::app.sms.log.title')
            </a>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800">
                    <tr>
                        <th class="px-4 py-2">#</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.log.batch-name')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.log.status')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.log.recipients')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.log.failed')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.compose.credits')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.log.when')</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($batches as $batch)
                        <tr class="border-b border-gray-100 dark:border-gray-800 dark:text-gray-300">
                            <td class="px-4 py-2">
                                <a href="{{ route('admin.sms.batches.show', $batch->id) }}" class="text-blue-600">
                                    {{ $batch->id }}
                                </a>
                            </td>

                            <td class="px-4 py-2">{{ $batch->name }}</td>

                            <td class="px-4 py-2">
                                <span class="rounded-full bg-gray-100 px-2 py-1 text-xs dark:bg-gray-800">
                                    {{ $batch->status->value }}
                                </span>
                            </td>

                            <td class="px-4 py-2">{{ number_format($batch->total_recipients) }}</td>

                            <td class="px-4 py-2">
                                @if ($batch->failed_count)
                                    <span class="text-red-600">{{ number_format($batch->failed_count) }}</span>
                                @else
                                    0
                                @endif
                            </td>

                            <td class="px-4 py-2">{{ number_format($batch->credits_charged) }}</td>
                            <td class="px-4 py-2 text-xs text-gray-500">{{ $batch->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">
                                @lang('messaging::app.sms.log.no-batches')
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $batches->links() }}
    </div>
</x-admin::layouts>
