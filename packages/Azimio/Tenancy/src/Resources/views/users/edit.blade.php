<x-admin::layouts>
    <x-slot:title>
        @lang('azimio::app.users.edit-title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <!-- Header -->
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-gray-300">
                @lang('azimio::app.users.edit-title') — {{ $user->name }}
            </div>

            <a href="{{ route('super.users.index') }}" class="secondary-button">
                @lang('azimio::app.users.back-btn')
            </a>
        </div>

        @include('azimio::partials.nav', ['active' => 'users'])

        @if ($errors->any())
            <div class="rounded-lg border border-gray-200 bg-white p-3 text-sm text-red-600 dark:border-gray-800 dark:bg-gray-900">
                {{ $errors->first() }}
            </div>
        @endif

        <!-- Account details -->
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
            x-data="{ tenantId: '{{ old('tenant_id', $user->tenant_id) }}', roleId: '{{ old('role_id', $user->role_id) }}', roles: {{ Illuminate\Support\Js::from($roles) }} }">
            <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">
                @lang('azimio::app.users.account-details')
            </p>

            @if ($user->is_super_admin)
                <p class="mb-4 rounded-md bg-purple-100 px-3 py-2 text-sm text-purple-800">
                    @lang('azimio::app.users.super-admin-note')
                </p>
            @endif

            <form method="POST" action="{{ route('super.users.update', $user->id) }}" class="grid gap-4 md:grid-cols-2">
                @csrf
                @method('PUT')

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">@lang('azimio::app.users.name')</label>
                    <input type="text" name="name" required value="{{ old('name', $user->name) }}"
                        class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">@lang('azimio::app.users.email')</label>
                    <input type="email" name="email" required value="{{ old('email', $user->email) }}"
                        class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">@lang('azimio::app.users.phone')</label>
                    <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}"
                        class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">@lang('azimio::app.users.phone2')</label>
                    <input type="tel" name="phone2" value="{{ old('phone2', $user->phone2) }}"
                        class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">@lang('azimio::app.users.tenant')</label>
                    @if ($user->is_super_admin)
                        <input type="text" value="@lang('azimio::app.users.no-tenant')" disabled
                            class="w-full rounded-md border bg-gray-100 px-3 py-2 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-950" />
                    @else
                        <select name="tenant_id" x-model="tenantId" required
                            class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                            @foreach ($tenants as $tenant)
                                <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">@lang('azimio::app.users.role')</label>
                    <select name="role_id" x-model="roleId" required
                        class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                        <template x-for="role in roles.filter(r => r.tenant_id === null || String(r.tenant_id) === String(tenantId))" :key="role.id">
                            <option :value="role.id" x-text="role.name"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">@lang('azimio::app.users.locale')</label>
                    <select name="locale"
                        class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">@lang('azimio::app.users.use-system-default')</option>
                        @foreach ($locales as $code => $title)
                            <option value="{{ $code }}" @selected(old('locale', $user->locale) === $code)>{{ $title }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">@lang('azimio::app.users.status')</label>
                    <label class="flex items-center gap-2 pt-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="status" value="1" @checked(old('status', $user->status)) />
                        @lang('azimio::app.users.active')
                    </label>
                </div>

                <div class="md:col-span-2">
                    <button type="submit" class="primary-button">@lang('azimio::app.users.save-btn')</button>
                </div>
            </form>
        </div>

        <!-- Change password -->
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">
                @lang('azimio::app.users.change-password')
            </p>

            <form method="POST" action="{{ route('super.users.password', $user->id) }}" class="grid gap-4 md:grid-cols-2">
                @csrf
                @method('PUT')

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">@lang('azimio::app.users.password')</label>
                    <input type="password" name="password" required
                        class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">@lang('azimio::app.users.confirm-password')</label>
                    <input type="password" name="password_confirmation" required
                        class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                </div>

                <div class="md:col-span-2">
                    <button type="submit" class="secondary-button">@lang('azimio::app.users.update-password-btn')</button>
                </div>
            </form>
        </div>
    </div>
</x-admin::layouts>
