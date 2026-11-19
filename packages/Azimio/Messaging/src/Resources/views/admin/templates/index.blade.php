<x-admin::layouts>
    <x-slot:title>
        @lang('messaging::app.templates.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-gray-300">
                @lang('messaging::app.templates.title')
            </div>

            <a href="{{ route('admin.sms.templates.create') }}"
                class="rounded-md bg-blue-600 px-4 py-2 text-xs font-semibold text-white">
                @lang('messaging::app.templates.new')
            </a>
        </div>

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800">
                    <tr>
                        <th class="px-4 py-2">@lang('messaging::app.templates.name')</th>
                        <th class="px-4 py-2">@lang('messaging::app.compose-body')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.bulk.category')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.compose.segments')</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($templates as $template)
                        <tr class="border-b border-gray-100 dark:border-gray-800 dark:text-gray-300">
                            <td class="px-4 py-2 font-medium">
                                {{ $template->name }}

                                @if ($template->isPlatformDefault())
                                    <span class="ml-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">
                                        @lang('messaging::app.templates.platform')
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-2 text-xs">{{ str($template->body)->limit(60) }}</td>

                            <td class="px-4 py-2 text-xs">
                                @lang('messaging::app.sms.bulk.category-' . $template->category->value)
                            </td>

                            <td class="px-4 py-2">
                                {{ $template->segment_count }}

                                @if ($template->is_unicode)
                                    <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">UCS-2</span>
                                @endif
                            </td>

                            <td class="px-4 py-2 text-right">
                                @unless ($template->isPlatformDefault())
                                    <a href="{{ route('admin.sms.templates.edit', $template->id) }}"
                                        class="text-xs text-blue-600">@lang('messaging::app.templates.edit')</a>

                                    <form method="POST" action="{{ route('admin.sms.templates.destroy', $template->id) }}"
                                        class="inline" onsubmit="return confirm('@lang('messaging::app.templates.confirm-delete')')">
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" class="ml-2 text-xs text-red-600">
                                            @lang('messaging::app.templates.delete')
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-gray-400">@lang('messaging::app.templates.read-only')</span>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">
                                @lang('messaging::app.templates.none')
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $templates->links() }}
    </div>
</x-admin::layouts>
