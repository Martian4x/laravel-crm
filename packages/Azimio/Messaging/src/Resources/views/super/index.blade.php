<x-admin::layouts>
    <x-slot:title>
        @lang('messaging::app.super.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-gray-300">
                @lang('messaging::app.super.title')
            </div>

            <a href="{{ route('super.sms.messages') }}" class="text-xs text-blue-600">
                @lang('messaging::app.super.all-messages')
            </a>
        </div>

        @include('azimio::partials.nav', ['active' => 'sms'])

        {{--
            8.18 kill switch. Pausing sets the platform profile to disabled,
            which SendSmsJob already refuses to send from — so the switch
            reuses an enforced gate rather than adding a parallel one.
            Own-provider tenants keep sending: their credit, their account.
        --}}
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border {{ $platformPaused ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-white' }} p-4 text-sm dark:border-gray-800 dark:bg-gray-900">
            <div>
                <p class="font-semibold {{ $platformPaused ? 'text-red-800' : 'dark:text-gray-300' }}">
                    @lang($platformPaused ? 'messaging::app.super.paused-title' : 'messaging::app.super.active-title')
                </p>

                <p class="text-xs {{ $platformPaused ? 'text-red-700' : 'text-gray-500' }}">
                    @lang('messaging::app.super.pause-help')
                </p>
            </div>

            <form method="POST" action="{{ route('super.sms.toggle-pause') }}"
                onsubmit="return confirm('@lang('messaging::app.super.pause-confirm')')">
                @csrf

                <button type="submit"
                    class="rounded-md px-4 py-2 text-xs font-semibold text-white {{ $platformPaused ? 'bg-emerald-600' : 'bg-red-600' }}">
                    @lang($platformPaused ? 'messaging::app.super.resume' : 'messaging::app.super.pause')
                </button>
            </form>
        </div>

        @if (session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

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

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="mb-1 text-base font-semibold text-gray-800 dark:text-white">
                    @lang('messaging::app.super.profile-title')
                </p>

                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    @lang('messaging::app.super.profile-help')
                </p>

                @if ($providerBalance !== null)
                    <p class="mb-4 text-sm dark:text-gray-300">
                        @lang('messaging::app.super.provider-balance'):
                        <strong>{{ number_format($providerBalance, 2) }}</strong>
                    </p>
                @endif

                <form method="POST" action="{{ route('super.sms.store') }}" class="grid gap-3">
                    @csrf

                    <label class="text-xs font-semibold text-gray-500">
                        @lang('messaging::app.sms.settings.provider')

                        <select name="driver" required class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                            @foreach ($drivers as $key => $label)
                                <option value="{{ $key }}" @selected(old('driver', $profile?->driver ?? config('messaging.platform.driver')) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="text-xs font-semibold text-gray-500">
                        @lang('messaging::app.sms.settings.sender-name')

                        <input type="text" name="sender_name" required maxlength="11"
                            value="{{ old('sender_name', $profile?->sender_name ?? $platformSender) }}"
                            class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                    </label>

                    <label class="text-xs font-semibold text-gray-500">
                        @lang('messaging::app.sms.settings.api-key')

                        <input type="password" name="api_key" autocomplete="off"
                            placeholder="{{ $profile ? trans('messaging::app.super.leave-blank') : '' }}"
                            class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                    </label>

                    <label class="text-xs font-semibold text-gray-500">
                        @lang('messaging::app.sms.settings.secret-key')

                        <input type="password" name="secret_key" autocomplete="off"
                            placeholder="{{ $profile ? trans('messaging::app.super.leave-blank') : '' }}"
                            class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                    </label>

                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
                        @lang('messaging::app.super.save-profile')
                    </button>
                </form>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <p class="mb-1 text-base font-semibold text-gray-800 dark:text-white">
                    @lang('messaging::app.super.allocate-title')
                </p>

                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    @lang('messaging::app.super.allocate-help')
                </p>

                <form method="POST" action="{{ route('super.sms.allocate') }}" class="grid gap-3">
                    @csrf

                    <select name="tenant_id" required class="rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant->id }}">{{ $tenant->name }} ({{ number_format($tenant->sms_credits) }})</option>
                        @endforeach
                    </select>

                    <select name="type" required class="rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                        <option value="allocation">@lang('messaging::app.super.type-allocation')</option>
                        <option value="purchase">@lang('messaging::app.super.type-purchase')</option>
                        <option value="adjustment">@lang('messaging::app.super.type-adjustment')</option>
                        <option value="expiry">@lang('messaging::app.super.type-expiry')</option>
                    </select>

                    <input type="number" name="credits" required placeholder="@lang('messaging::app.super.credits-placeholder')"
                        class="rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />

                    <input type="text" name="note" required maxlength="500" placeholder="@lang('messaging::app.super.note-placeholder')"
                        class="rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />

                    <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">
                        @lang('messaging::app.super.apply')
                    </button>
                </form>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800">
                    <tr>
                        <th class="px-4 py-2">@lang('messaging::app.super.tenant')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.settings.credit-balance')</th>
                        <th class="px-4 py-2">@lang('messaging::app.super.ledger-balance')</th>
                        <th class="px-4 py-2">@lang('messaging::app.super.drift')</th>
                        <th class="px-4 py-2">@lang('messaging::app.super.sent')</th>
                        <th class="px-4 py-2">@lang('messaging::app.super.spent')</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($tenants as $tenant)
                        <tr class="border-b border-gray-100 dark:border-gray-800 dark:text-gray-300">
                            <td class="px-4 py-2 font-medium">{{ $tenant->name }}</td>
                            <td class="px-4 py-2">{{ number_format($tenant->sms_credits) }}</td>
                            <td class="px-4 py-2">{{ number_format($tenant->ledger_balance) }}</td>

                            <td class="px-4 py-2">
                                @if ($tenant->drift !== 0)
                                    <span class="text-amber-700">{{ $tenant->drift > 0 ? '+' : '' }}{{ $tenant->drift }}</span>
                                @else
                                    0
                                @endif
                            </td>

                            <td class="px-4 py-2">{{ number_format($tenant->sent_count) }}</td>
                            <td class="px-4 py-2">{{ number_format($tenant->spent_credits) }}</td>

                            <td class="px-4 py-2">
                                <a href="{{ route('super.sms.ledger', $tenant->id) }}" class="text-xs text-blue-600">
                                    @lang('messaging::app.super.ledger')
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <p class="px-4 pt-4 text-base font-semibold text-gray-800 dark:text-white">
                @lang('messaging::app.super.usage-title')
            </p>

            <table class="mt-2 w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800">
                    <tr>
                        <th class="px-4 py-2">@lang('messaging::app.super.month')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.log.total-messages')</th>
                        <th class="px-4 py-2">@lang('messaging::app.sms.compose.credits')</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($monthlyUsage as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-800 dark:text-gray-300">
                            <td class="px-4 py-2">{{ $row['month'] }}</td>
                            <td class="px-4 py-2">{{ number_format($row['messages']) }}</td>
                            <td class="px-4 py-2">{{ number_format($row['credits']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500">
                                @lang('messaging::app.sms.log.empty')
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin::layouts>