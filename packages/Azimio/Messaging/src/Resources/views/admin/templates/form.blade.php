<x-admin::layouts>
    <x-slot:title>
        {{ $template->exists ? trans('messaging::app.templates.edit') : trans('messaging::app.templates.new') }}
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-gray-300">
                {{ $template->exists ? trans('messaging::app.templates.edit') : trans('messaging::app.templates.new') }}
            </div>

            <a href="{{ route('admin.sms.templates.index') }}" class="text-xs text-blue-600">
                @lang('messaging::app.templates.title')
            </a>
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
            action="{{ $template->exists ? route('admin.sms.templates.update', $template->id) : route('admin.sms.templates.store') }}"
            class="grid gap-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            @csrf

            @if ($template->exists)
                @method('PUT')
            @endif

            <label class="text-xs font-semibold text-gray-500">
                @lang('messaging::app.templates.name')

                <input type="text" name="name" required maxlength="255"
                    value="{{ old('name', $template->name) }}"
                    class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
            </label>

            <label class="text-xs font-semibold text-gray-500">
                @lang('messaging::app.sms.bulk.category')

                <select name="category" required
                    class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                    <option value="transactional" @selected(old('category', $template->category?->value) === 'transactional')>
                        @lang('messaging::app.sms.bulk.category-transactional')
                    </option>
                    <option value="marketing" @selected(old('category', $template->category?->value) === 'marketing')>
                        @lang('messaging::app.sms.bulk.category-marketing')
                    </option>
                </select>

                <span class="mt-1 block font-normal text-gray-500">
                    @lang('messaging::app.sms.bulk.category-help')
                </span>
            </label>

            <label class="text-xs font-semibold text-gray-500">
                @lang('messaging::app.templates.locale')

                <select name="locale"
                    class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                    <option value="">@lang('messaging::app.templates.any-locale')</option>
                    @foreach (config('app.available_locales') as $code => $title)
                        <option value="{{ $code }}" @selected(old('locale', $template->locale) === $code)>{{ $title }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-xs font-semibold text-gray-500">
                @lang('messaging::app.compose-body')

                <textarea id="template-body" name="body" rows="5" required maxlength="2000"
                    class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">{{ old('body', $template->body) }}</textarea>
            </label>

            <div class="flex flex-wrap gap-2">
                @foreach ($mergeFields as $field => $label)
                    @php($token = '{'.'{'.$field.'}'.'}')

                    <button type="button"
                        onclick="const b=document.getElementById('template-body'); b.value += '{{ $token }}'; b.dispatchEvent(new Event('input'));"
                        class="rounded-full border border-gray-300 px-3 py-1 text-xs dark:border-gray-700 dark:text-gray-300">
                        {{ $field }}
                    </button>
                @endforeach
            </div>

            <p class="text-xs text-gray-500">
                @lang('messaging::app.templates.cost-note')
            </p>

            <div>
                <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
                    @lang('messaging::app.templates.save')
                </button>
            </div>
        </form>
    </div>
</x-admin::layouts>
