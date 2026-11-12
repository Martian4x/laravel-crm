<x-admin::layouts>
    <x-slot:title>
        @lang('messaging::app.super.all-messages')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-gray-300">
                @lang('messaging::app.super.all-messages')
            </div>

            <a href="{{ route('super.sms.index') }}" class="text-xs text-blue-600">
                @lang('messaging::app.super.title')
            </a>
        </div>

        <form method="GET" action="{{ route('super.sms.messages') }}"
            class="flex flex-wrap gap-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">

            <select name="tenant_id" class="rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <option value="">@lang('messaging::app.super.any-tenant')</option>
                @foreach ($tenants as $tenant)
                    <option value="{{ $tenant->id }}" @selected((string) $filters['tenant_id'] === (string) $tenant->id)>{{ $tenant->name }}</option>
                @endforeach
            </select>

            <input type="text" name="status" value="{{ $filters['status'] }}" placeholder="@lang('messaging::app.sms.log.status')"
                class="rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />

            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
                @lang('messaging::app.sms.log.filter')
            </button>
        </form>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800">
                    <tr>
                        <th class="px-4 py-2">@lang('messaging::app.super.tenant')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.log.number')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.log.status')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.compose.credits')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.log.when')</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($messages as $message)
                        <tr class="border-b border-gray-100 dark:border-gray-800 dark:text-gray-300">
                            <td class="px-4 py-2 text-xs">{{ $message->tenant?->name ?? $message->tenant_id }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $message->dest_addr }}</td>
                            <td class="px-4 py-2 text-xs">{{ $message->status->value }}</td>
                            <td class="px-4 py-2">{{ $message->credits_charged }}</td>
                            <td class="px-4 py-2 text-xs text-gray-500">{{ $message->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">
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
