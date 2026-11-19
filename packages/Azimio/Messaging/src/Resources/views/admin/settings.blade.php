<x-admin::layouts>
    <x-slot:title>
        @lang('messaging::app.sms.settings.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-gray-300">
                @lang('messaging::app.sms.settings.title')
            </div>

            <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">
                @lang('messaging::app.sms.modes.' . $mode->value)
            </span>
        </div>

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="mb-1 text-base font-semibold text-gray-800 dark:text-white">
                    @lang('messaging::app.sms.settings.platform-title')
                </p>

                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    @lang('messaging::app.sms.settings.platform-help')
                </p>

                <dl class="mb-4 grid grid-cols-2 gap-2 text-sm">
                    <dt class="text-gray-500">@lang('messaging::app.sms.settings.sender-name')</dt>
                    <dd class="font-mono font-semibold dark:text-gray-300">{{ $platformSender }}</dd>

                    <dt class="text-gray-500">@lang('messaging::app.sms.settings.credit-balance')</dt>
                    <dd class="font-semibold dark:text-gray-300">{{ number_format($platformCredits) }}</dd>
                </dl>

                @if ($platformCredits <= (int) config('messaging.credits.low_balance_threshold'))
                    <p class="mb-4 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        @lang('messaging::app.sms.settings.low-balance')
                    </p>
                @endif

                @if ($mode->value === 'platform')
                    <span class="text-xs font-semibold text-blue-700">
                        @lang('messaging::app.sms.settings.currently-active')
                    </span>
                @else
                    <form method="POST" action="{{ route('admin.sms.settings.platform') }}">
                        @csrf

                        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
                            @lang('messaging::app.sms.settings.use-platform')
                        </button>
                    </form>
                @endif
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="mb-1 text-base font-semibold text-gray-800 dark:text-white">
                    @lang('messaging::app.sms.settings.own-title')
                </p>

                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    @lang('messaging::app.sms.settings.own-help')
                </p>

                @if ($profile && $providerBalance !== null)
                    <dl class="mb-4 grid grid-cols-2 gap-2 text-sm">
                        <dt class="text-gray-500">@lang('messaging::app.sms.settings.provider-balance')</dt>
                        <dd class="font-semibold dark:text-gray-300">{{ number_format($providerBalance, 2) }}</dd>
                    </dl>
                @endif

                <form method="POST" action="{{ route('admin.sms.settings.store') }}" class="grid gap-3">
                    @csrf

                    <label class="text-xs font-semibold text-gray-500">
                        @lang('messaging::app.sms.settings.provider')

                        <select name="driver" required
                            class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                            @foreach ($drivers as $key => $label)
                                <option value="{{ $key }}" @selected(old('driver', $profile?->driver) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="text-xs font-semibold text-gray-500">
                        @lang('messaging::app.sms.settings.sender-name')

                        <input type="text" name="sender_name" required maxlength="11"
                            value="{{ old('sender_name', $profile?->sender_name) }}"
                            class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />

                        <span class="mt-1 block font-normal text-gray-500">
                            @lang('messaging::app.sms.settings.sender-name-help')
                        </span>
                    </label>

                    <label class="text-xs font-semibold text-gray-500">
                        @lang('messaging::app.sms.settings.api-key')

                        <input type="password" name="api_key" required autocomplete="off"
                            class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                    </label>

                    <label class="text-xs font-semibold text-gray-500">
                        @lang('messaging::app.sms.settings.secret-key')

                        <input type="password" name="secret_key" required autocomplete="off"
                            class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                    </label>

                    <p class="text-xs text-gray-500">
                        @lang('messaging::app.sms.settings.verify-note')
                    </p>

                    <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">
                        @lang('messaging::app.sms.settings.save-and-verify')
                    </button>
                </form>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <p class="mb-1 text-base font-semibold text-gray-800 dark:text-white">
                @lang('messaging::app.sms.compliance.title')
            </p>

            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                @lang('messaging::app.sms.compliance.help')
            </p>

            <form method="POST" action="{{ route('admin.sms.settings.compliance') }}" class="grid gap-3 md:grid-cols-4">
                @csrf

                <label class="flex items-center gap-2 text-xs font-semibold text-gray-500 md:col-span-4">
                    <input type="checkbox" name="quiet_hours_enabled" value="1"
                        @checked($quietHours->enabled($tenant)) />

                    @lang('messaging::app.sms.compliance.quiet-enabled')
                </label>

                <label class="text-xs font-semibold text-gray-500">
                    @lang('messaging::app.sms.compliance.quiet-from')

                    <input type="time" name="quiet_start" value="{{ $tenant->sms_quiet_start }}"
                        class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                </label>

                <label class="text-xs font-semibold text-gray-500">
                    @lang('messaging::app.sms.compliance.quiet-to')

                    <input type="time" name="quiet_end" value="{{ $tenant->sms_quiet_end }}"
                        class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                </label>

                <label class="text-xs font-semibold text-gray-500">
                    @lang('messaging::app.sms.compliance.daily-cap')

                    <input type="number" name="daily_cap" min="0" value="{{ $tenant->sms_daily_cap }}"
                        class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                </label>

                <div class="flex items-end">
                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
                        @lang('messaging::app.sms.compliance.save')
                    </button>
                </div>

                <p class="text-xs text-gray-500 md:col-span-4">
                    @lang('messaging::app.sms.compliance.quiet-note', [
                        'start' => config('messaging.compliance.quiet_hours.start'),
                        'end'   => config('messaging.compliance.quiet_hours.end'),
                    ])
                    @lang('messaging::app.sms.compliance.daily-cap-note')
                </p>

                <p class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800 md:col-span-4">
                    @lang('messaging::app.sms.compliance.tcra-note')
                </p>
            </form>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <p class="mb-1 text-base font-semibold text-gray-800 dark:text-white">
                @lang('messaging::app.sms.settings.test-title')
            </p>

            <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                @lang('messaging::app.sms.settings.test-help')
            </p>

            <form method="POST" action="{{ route('admin.sms.settings.test') }}" class="flex flex-wrap gap-3">
                @csrf

                <input type="text" name="test_number" required placeholder="0712 345 678"
                    value="{{ old('test_number') }}"
                    class="w-64 rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />

                <button type="submit" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold dark:border-gray-700 dark:text-gray-300">
                    @lang('messaging::app.sms.settings.send-test')
                </button>
            </form>
        </div>
    </div>
</x-admin::layouts>
