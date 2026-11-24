{{--
    Shared by create and edit (9.4.1).

    One form in one file: the two pages differ only in their action URL and
    whether a record is being edited, and keeping them apart is how a field
    ends up added to create and forgotten on edit.
--}}
@php($service = $service ?? null)

<div class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-900">
    @if ($errors->any())
        <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
            @lang('services::app.name')
        </label>

        <input type="text" name="name" required maxlength="255"
            value="{{ old('name', $service?->name) }}"
            class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
            @lang('services::app.code')
        </label>

        <input type="text" name="code" maxlength="64"
            value="{{ old('code', $service?->code) }}"
            class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />

        <span class="mt-1 block text-xs text-gray-500">@lang('services::app.code-help')</span>
    </div>

    <div class="flex flex-wrap gap-4">
        <div class="flex-1">
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                @lang('services::app.price')
            </label>

            <input type="number" name="price" step="0.01" min="0" required
                value="{{ old('price', $service?->price ?? '0.00') }}"
                class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
        </div>

        <div class="flex-1">
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                @lang('services::app.unit')
            </label>

            <select name="unit" required
                class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                @foreach ($units as $value => $label)
                    <option value="{{ $value }}" @selected(old('unit', $service?->unit?->value ?? 'fixed') === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
            @lang('services::app.description')
        </label>

        <textarea name="description" rows="4" maxlength="5000"
            class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">{{ old('description', $service?->description) }}</textarea>
    </div>

    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
        {{-- Hidden companion so an unchecked box submits 0 rather than
             vanishing from the request entirely. --}}
        <input type="hidden" name="is_taxable" value="0" />
        <input type="checkbox" name="is_taxable" value="1"
            @checked(old('is_taxable', $service?->is_taxable ?? true)) />
        @lang('services::app.taxable')
    </label>

    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
        <input type="hidden" name="is_active" value="0" />
        <input type="checkbox" name="is_active" value="1"
            @checked(old('is_active', $service?->is_active ?? true)) />
        @lang('services::app.active')
    </label>

    <div class="flex items-center gap-3">
        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
            @lang('services::app.save')
        </button>

        <a href="{{ route('admin.services.index') }}" class="text-sm text-gray-600 dark:text-gray-400">
            @lang('services::app.cancel')
        </a>
    </div>
</div>
