<x-admin::layouts>
    <x-slot:title>
        @lang('messaging::app.sms.bulk.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-gray-300">
                @lang('messaging::app.sms.bulk.title')
            </div>

            <span class="text-xs text-gray-500">
                @lang('messaging::app.sms.settings.credit-balance'): <strong>{{ number_format($platformCredits) }}</strong>
            </span>
        </div>

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

        @unless ($hasProfile)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                @lang('messaging::app.sms.settings.no-profile')
            </div>
        @endunless

        <form method="POST" action="{{ route('admin.sms.bulk.confirm') }}"
            class="grid gap-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            @csrf

            @foreach ($personIds as $id)
                <input type="hidden" name="person_ids[]" value="{{ $id }}" />
            @endforeach

            @foreach ($leadIds as $id)
                <input type="hidden" name="lead_ids[]" value="{{ $id }}" />
            @endforeach

            @if ($personIds || $leadIds)
                <p class="rounded-md bg-blue-50 px-3 py-2 text-sm text-blue-800">
                    @lang('messaging::app.sms.bulk.from-selection', [
                        'count' => count($personIds) + count($leadIds),
                    ])
                </p>
            @endif

            <label class="text-xs font-semibold text-gray-500">
                @lang('messaging::app.sms.bulk.name')

                <input type="text" name="name" maxlength="255" value="{{ old('name') }}"
                    placeholder="@lang('messaging::app.sms.bulk.name-placeholder')"
                    class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
            </label>

            <label class="text-xs font-semibold text-gray-500">
                @lang('messaging::app.sms.bulk.numbers')

                <textarea name="numbers" rows="5"
                    placeholder="@lang('messaging::app.sms.bulk.numbers-placeholder')"
                    class="mt-1 w-full rounded-md border px-3 py-2 font-mono text-xs dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">{{ old('numbers') }}</textarea>

                <span class="mt-1 block font-normal text-gray-500">
                    @lang('messaging::app.sms.bulk.numbers-help')
                </span>
            </label>

            <label class="text-xs font-semibold text-gray-500">
                @lang('messaging::app.sms.bulk.category')

                <select name="category" required
                    class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                    <option value="transactional" @selected(old('category') === 'transactional')>
                        @lang('messaging::app.sms.bulk.category-transactional')
                    </option>
                    <option value="marketing" @selected(old('category', 'marketing') === 'marketing')>
                        @lang('messaging::app.sms.bulk.category-marketing')
                    </option>
                </select>

                <span class="mt-1 block font-normal text-gray-500">
                    @lang('messaging::app.sms.bulk.category-help')
                </span>
            </label>

            @if ($templates->isNotEmpty())
                <label class="text-xs font-semibold text-gray-500">
                    @lang('messaging::app.sms.compose.template')

                    <select onchange="document.getElementById('bulk-body').value = this.selectedOptions[0].dataset.body || document.getElementById('bulk-body').value"
                        class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">@lang('messaging::app.sms.compose.no-template')</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}" data-body="{{ $template->body }}">{{ $template->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label class="text-xs font-semibold text-gray-500">
                @lang('messaging::app.sms.compose.message')

                <textarea id="bulk-body" name="body" rows="5" required
                    class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">{{ old('body') }}</textarea>
            </label>

            <p class="text-xs text-gray-500">
                @lang('messaging::app.sms.bulk.merge-note', ['fields' => implode(', ', array_keys($mergeFields))])
            </p>

            <div>
                <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
                    @lang('messaging::app.sms.bulk.review')
                </button>
            </div>
        </form>
    </div>
</x-admin::layouts>
