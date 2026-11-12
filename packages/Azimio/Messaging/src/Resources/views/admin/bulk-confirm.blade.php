<x-admin::layouts>
    <x-slot:title>
        @lang('messaging::app.sms.bulk.confirm-title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-gray-300">
                @lang('messaging::app.sms.bulk.confirm-title')
            </div>

            <a href="{{ route('admin.sms.bulk.index') }}" class="text-xs text-blue-600">
                @lang('messaging::app.sms.bulk.back')
            </a>
        </div>

        @if (session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        {{-- The cost, stated plainly before anything is written or charged. --}}
        <div class="grid gap-4 rounded-lg border-2 {{ $plan->hasSufficientCredits() ? 'border-blue-300' : 'border-red-300' }} bg-white p-4 text-sm md:grid-cols-4 dark:bg-gray-900">
            <div>
                <p class="text-xs text-gray-500">@lang('messaging::app.sms.bulk.will-send')</p>
                <p class="text-2xl font-bold dark:text-gray-300">{{ number_format($plan->sendableCount()) }}</p>
            </div>

            <div>
                <p class="text-xs text-gray-500">@lang('messaging::app.sms.compose.segments')</p>
                <p class="text-2xl font-bold dark:text-gray-300">{{ $plan->segments->segments }}</p>
                <p class="text-xs text-gray-500">@lang('messaging::app.sms.bulk.each')</p>
            </div>

            <div>
                <p class="text-xs text-gray-500">@lang('messaging::app.sms.bulk.total-credits')</p>
                <p class="text-2xl font-bold {{ $plan->hasSufficientCredits() ? 'text-blue-700' : 'text-red-700' }}">
                    {{ number_format($plan->creditsRequired) }}
                </p>
            </div>

            <div>
                <p class="text-xs text-gray-500">@lang('messaging::app.sms.settings.credit-balance')</p>
                <p class="text-2xl font-bold dark:text-gray-300">{{ number_format($plan->balance) }}</p>
            </div>
        </div>

        @if (! $plan->hasSufficientCredits())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                @lang('messaging::app.sms.errors.no-platform-credits', [
                    'needed'    => $plan->creditsRequired,
                    'available' => $plan->balance,
                ])

                <a href="{{ route('admin.sms.credits.index') }}" class="font-semibold underline">
                    @lang('messaging::app.requests.new-title')
                </a>
            </div>
        @endif

        @if ($plan->segments->isUnicode())
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                @lang('messaging::app.sms.compose.unicode-warning')
            </div>
        @endif

        @if ($plan->isDeferred())
            <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                @lang('messaging::app.sms.errors.quiet-hours', ['time' => $plan->deferredUntil->format('H:i')])
            </div>
        @endif

        <div class="rounded-md bg-gray-50 p-3 text-sm dark:bg-gray-800 dark:text-gray-300">
            <p class="mb-1 text-xs font-semibold text-gray-500">@lang('messaging::app.sms.compose.message')</p>
            <p class="whitespace-pre-wrap">{{ $body }}</p>
        </div>

        {{-- Everything excluded, and why. Not a silent drop. --}}
        <div class="grid gap-4 md:grid-cols-3">
            @foreach ([
                'duplicate' => 'messaging::app.sms.bulk.excluded-duplicates',
                'opted_out' => 'messaging::app.sms.bulk.excluded-optouts',
                'invalid'   => 'messaging::app.sms.bulk.excluded-invalid',
            ] as $outcome => $label)
                @php($rows = $plan->excluded($outcome))

                <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="mb-2 text-xs font-semibold text-gray-500">
                        @lang($label) — <strong>{{ count($rows) }}</strong>
                    </p>

                    @if ($rows)
                        <ul class="max-h-40 overflow-y-auto text-xs dark:text-gray-400">
                            @foreach (array_slice($rows, 0, 50) as $row)
                                <li class="font-mono">
                                    {{ $row->raw }}
                                    @if ($row->rejection)
                                        <span class="font-sans text-gray-500">— @lang($row->rejection->translationKey())</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        @if (count($rows) > 50)
                            <p class="mt-1 text-xs text-gray-500">
                                @lang('messaging::app.sms.bulk.and-more', ['count' => count($rows) - 50])
                            </p>
                        @endif
                    @else
                        <p class="text-xs text-gray-400">—</p>
                    @endif
                </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('admin.sms.bulk.send') }}">
            @csrf

            <input type="hidden" name="body" value="{{ $body }}" />
            <input type="hidden" name="category" value="{{ $category }}" />
            <input type="hidden" name="name" value="{{ $name }}" />
            <input type="hidden" name="numbers" value="{{ $numbers }}" />
            <input type="hidden" name="confirmed_credits" value="{{ $plan->creditsRequired }}" />

            @foreach ($personIds as $id)
                <input type="hidden" name="person_ids[]" value="{{ $id }}" />
            @endforeach

            @foreach ($leadIds as $id)
                <input type="hidden" name="lead_ids[]" value="{{ $id }}" />
            @endforeach

            <button type="submit"
                @disabled(! $plan->hasSufficientCredits() || ! $plan->hasAnythingToSend())
                class="rounded-md bg-blue-600 px-6 py-3 text-sm font-semibold text-white disabled:opacity-50">
                @lang('messaging::app.sms.bulk.send-now', [
                    'count'   => $plan->sendableCount(),
                    'credits' => $plan->creditsRequired,
                ])
            </button>
        </form>
    </div>
</x-admin::layouts>
