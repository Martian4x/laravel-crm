<x-admin::layouts>
    <x-slot:title>
        @lang('messaging::app.sms.compose.title')
    </x-slot>

    <div class="flex flex-col gap-4"
        x-data="smsCompose({
            previewUrl: '{{ route('admin.sms.compose.preview') }}',
            type: @js($recipientType),
            id: @js($recipientId),
        })">

        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="text-xl font-bold dark:text-gray-300">
                @lang('messaging::app.sms.compose.title')

                @if ($recipientLabel)
                    <span class="text-sm font-normal text-gray-500">— {{ $recipientLabel }}</span>
                @endif
            </div>

            <a href="{{ route('admin.sms.settings.index') }}" class="text-xs text-blue-600">
                @lang('messaging::app.sms.settings.title')
            </a>
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

        @unless ($hasProfile)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                @lang('messaging::app.sms.settings.no-profile')
            </div>
        @endunless

        <form method="POST" action="{{ route('admin.sms.compose.send') }}"
            class="grid gap-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            @csrf

            <input type="hidden" name="type" value="{{ $recipientType }}" />
            <input type="hidden" name="id" value="{{ $recipientId }}" />

            <label class="text-xs font-semibold text-gray-500">
                @lang('messaging::app.sms.compose.to')

                @if ($numbers)
                    <select name="number" x-model="number" @change="refresh()"
                        class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                        @foreach ($numbers as $number)
                            <option value="{{ $number }}" @selected($prefillNumber === $number)>{{ $number }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="text" name="number" x-model="number" @input.debounce.400ms="refresh()"
                        required placeholder="0712 345 678" value="{{ old('number', $prefillNumber) }}"
                        class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />
                @endif
            </label>

            <template x-if="numberError">
                <p class="rounded-md bg-red-50 px-3 py-2 text-xs text-red-700" x-text="numberError"></p>
            </template>

            <template x-if="optedOut">
                <p class="rounded-md bg-red-50 px-3 py-2 text-xs text-red-700">
                    @lang('messaging::app.sms.errors.opted-out')
                </p>
            </template>

            @if ($templates->isNotEmpty())
                <label class="text-xs font-semibold text-gray-500">
                    @lang('messaging::app.sms.compose.template')

                    <select x-model="templateId" @change="applyTemplate()"
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

                <textarea name="body" rows="5" required x-model="body" @input.debounce.400ms="refresh()"
                    class="mt-1 w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">{{ old('body') }}</textarea>
            </label>

            <div class="flex flex-wrap gap-2">
                @foreach ($mergeFields as $field => $label)
                    {{-- Built in PHP: writing the braces inline would nest
                         them inside Blade's own echo tags. --}}
                    @php($token = '{'.'{'.$field.'}'.'}')

                    <button type="button" @click="insert('{{ $token }}')"
                        class="rounded-full border border-gray-300 px-3 py-1 text-xs dark:border-gray-700 dark:text-gray-300">
                        {{ $field }}
                    </button>
                @endforeach
            </div>

            <div class="rounded-md bg-gray-50 p-3 text-sm dark:bg-gray-800">
                <p class="mb-1 text-xs font-semibold text-gray-500">
                    @lang('messaging::app.sms.compose.preview')
                </p>

                <p class="whitespace-pre-wrap dark:text-gray-300" x-text="preview || '—'"></p>
            </div>

            <div class="flex flex-wrap items-center gap-4 text-xs text-gray-600 dark:text-gray-400">
                <span>
                    @lang('messaging::app.sms.compose.characters'): <strong x-text="length"></strong>
                </span>

                <span>
                    @lang('messaging::app.sms.compose.segments'): <strong x-text="segments"></strong>
                </span>

                <template x-if="usesCredits">
                    <span>
                        @lang('messaging::app.sms.compose.credits'): <strong x-text="credits"></strong>
                        <span class="text-gray-400">/ <span x-text="balance"></span></span>
                    </span>
                </template>

                <template x-if="isUnicode">
                    <span class="rounded-full bg-amber-100 px-2 py-1 text-amber-800">
                        @lang('messaging::app.sms.compose.unicode-warning')
                    </span>
                </template>
            </div>

            <div>
                <button type="submit" :disabled="optedOut || !!numberError"
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                    @lang('messaging::app.sms.compose.send')
                </button>
            </div>
        </form>
    </div>

    @pushOnce('scripts')
        <script>
            /**
             * The counter asks the SERVER for segments and credits instead
             * of reimplementing GSM-7 detection here. Decision 8.0.1 bills
             * per segment, so this number has to match what the pipeline
             * charges, and a second copy of the rules in JavaScript is
             * exactly what would drift away from it.
             */
            function smsCompose(config) {
                return {
                    body: '',
                    number: '',
                    templateId: '',
                    preview: '',
                    length: 0,
                    segments: 1,
                    credits: 0,
                    balance: 0,
                    usesCredits: false,
                    isUnicode: false,
                    optedOut: false,
                    numberError: null,

                    init() {
                        this.body = this.$el.querySelector('[name=body]')?.value ?? '';
                        this.number = this.$el.querySelector('[name=number]')?.value ?? '';
                        this.refresh();
                    },

                    applyTemplate() {
                        const option = this.$el.querySelector('option[value="' + this.templateId + '"]');

                        if (option && option.dataset.body) {
                            this.body = option.dataset.body;
                            this.refresh();
                        }
                    },

                    insert(token) {
                        this.body = (this.body ?? '') + token;
                        this.refresh();
                    },

                    async refresh() {
                        const token = document.querySelector('meta[name=csrf-token]');

                        const response = await fetch(config.previewUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token ? token.content : '',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                body: this.body,
                                number: this.number,
                                type: config.type,
                                id: config.id,
                            }),
                        });

                        if (! response.ok) {
                            return;
                        }

                        const data = await response.json();

                        this.preview = data.preview;
                        this.length = data.length;
                        this.segments = data.segments;
                        this.credits = data.credits;
                        this.balance = data.balance;
                        this.usesCredits = data.uses_credits;
                        this.isUnicode = data.is_unicode;
                        this.optedOut = data.opted_out;
                        this.numberError = data.number_error;
                    },
                };
            }
        </script>
    @endPushOnce
</x-admin::layouts>
