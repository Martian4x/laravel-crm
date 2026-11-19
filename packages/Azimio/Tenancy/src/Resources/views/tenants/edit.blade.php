<x-admin::layouts>
    <x-slot:title>
        @lang('azimio::app.tenants.edit-title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <!-- Header -->
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-gray-300">
                @lang('azimio::app.tenants.edit-title') — {{ $tenant->name }}
            </div>

            <a href="{{ route('super.tenants.index') }}" class="secondary-button">
                @lang('azimio::app.tenants.back-btn')
            </a>
        </div>

        @include('azimio::partials.nav', ['active' => 'tenants'])

        @if (session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-950 dark:text-green-300">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-gray-200 bg-white p-3 text-sm text-red-600 dark:border-gray-800 dark:bg-gray-900">
                {{ $errors->first() }}
            </div>
        @endif

        <!-- Tenant settings -->
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">
                @lang('azimio::app.tenants.settings-title')
            </p>

            <form method="POST" action="{{ route('super.tenants.update', $tenant->id) }}" class="grid gap-4 md:grid-cols-2">
                @csrf
                @method('PUT')

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">@lang('azimio::app.tenants.company-name')</label>
                    <input type="text" name="name" required value="{{ old('name', $tenant->name) }}"
                        class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Slug</label>
                    <input type="text" value="{{ $tenant->slug }}" disabled
                        class="w-full rounded-md border bg-gray-100 px-3 py-2 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-950" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">@lang('azimio::app.tenants.locale')</label>
                    <select name="locale" class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                        @foreach ($locales as $code => $title)
                            <option value="{{ $code }}" @selected(old('locale', $tenant->locale) === $code)>{{ $title }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">@lang('azimio::app.tenants.currency')</label>
                    <input type="text" name="currency" value="{{ old('currency', $tenant->currency) }}" maxlength="10"
                        placeholder="TZS"
                        class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">@lang('azimio::app.tenants.plan')</label>
                    <input type="text" name="plan" value="{{ old('plan', $tenant->plan) }}" maxlength="50"
                        placeholder="starter / pro / enterprise"
                        class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">@lang('azimio::app.tenants.max-users')</label>
                    <input type="number" name="max_users" value="{{ old('max_users', $tenant->max_users) }}" min="1" max="9999"
                        placeholder="@lang('azimio::app.tenants.max-users-placeholder')"
                        class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                    <p class="mt-1 text-xs text-gray-400">@lang('azimio::app.tenants.max-users-hint')</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">@lang('azimio::app.tenants.trial-ends')</label>
                    <input type="date" name="trial_ends_at"
                        value="{{ old('trial_ends_at', $tenant->trial_ends_at?->toDateString()) }}"
                        class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                    <p class="mt-1 text-xs text-gray-400">@lang('azimio::app.tenants.trial-ends-hint')</p>
                </div>

                {{--
                    7.9: state and reason, because "suspended" as a bare
                    boolean tells the next admin nothing — pending
                    verification, a lapsed trial and a deliberate suspension
                    need completely different responses.
                --}}
                <div class="md:col-span-2 rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-800">
                    <p class="text-xs font-semibold text-gray-500">@lang('azimio::app.tenants.state')</p>

                    <p class="font-semibold dark:text-gray-300">
                        @lang('azimio::app.tenants.state-' . $tenant->state())
                    </p>

                    @if ($tenant->suspension_reason)
                        <p class="mt-1 text-xs text-red-700">
                            @lang('azimio::app.tenants.suspended-because', [
                                'reason' => $tenant->suspension_reason,
                                'when'   => $tenant->suspended_at?->toDayDateTimeString() ?? '—',
                            ])
                        </p>
                    @endif

                    @if ($tenant->isPending())
                        <p class="mt-1 text-xs text-amber-700">
                            @lang('azimio::app.tenants.pending-hint')
                        </p>
                    @endif
                </div>

                <div class="md:col-span-2">
                    <button type="submit" class="primary-button">@lang('azimio::app.tenants.save-btn')</button>
                </div>
            </form>
        </div>

        <!-- Danger zone -->
        <div class="rounded-lg border border-red-200 bg-white p-4 dark:border-red-900 dark:bg-gray-900">
            <p class="mb-3 text-base font-semibold text-red-700 dark:text-red-400">
                @lang('azimio::app.tenants.danger-zone')
            </p>

            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                @lang('azimio::app.tenants.delete-warning')
            </p>

            <form method="POST" action="{{ route('super.tenants.destroy', $tenant->id) }}"
                onsubmit="return confirm('{{ trans('azimio::app.tenants.delete-confirm', ['name' => $tenant->name]) }}')">
                @csrf
                @method('DELETE')
                <button type="submit" class="secondary-button !text-red-600">
                    @lang('azimio::app.tenants.delete-btn')
                </button>
            </form>
        </div>
    </div>
</x-admin::layouts>
