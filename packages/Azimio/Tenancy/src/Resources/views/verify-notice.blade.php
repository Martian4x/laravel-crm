<x-admin::layouts.anonymous>
    <div class="flex w-full flex-col items-center justify-center">
        <div class="w-full max-w-md rounded-lg border border-gray-200 bg-white p-8 dark:border-gray-800 dark:bg-gray-900">
            @if ($expired ?? false)
                <p class="mb-2 text-xl font-bold text-red-700">
                    @lang('azimio::app.verify.expired-title')
                </p>

                <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">
                    @lang('azimio::app.verify.expired-help')
                </p>
            @else
                <p class="mb-2 text-xl font-bold dark:text-white">
                    @lang('azimio::app.verify.title')
                </p>

                <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">
                    @lang('azimio::app.verify.help', ['email' => $email ?: trans('azimio::app.verify.your-address')])
                </p>
            @endif

            @if (session('success'))
                <div class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('azimio.verify.resend') }}" class="grid gap-3">
                @csrf

                <input type="email" name="email" required
                    value="{{ old('email', $email) }}"
                    placeholder="@lang('azimio::app.verify.email-placeholder')"
                    class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" />

                <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
                    @lang('azimio::app.verify.resend')
                </button>
            </form>

            <p class="mt-6 text-center text-xs">
                <a href="{{ route('admin.session.create') }}" class="text-blue-600">
                    @lang('azimio::app.verify.back-to-login')
                </a>
            </p>
        </div>
    </div>
</x-admin::layouts.anonymous>
