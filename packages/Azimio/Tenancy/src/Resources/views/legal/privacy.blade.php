<x-admin::layouts.anonymous>
    <x-slot:title>
        @lang('azimio::app.legal.privacy-title')
    </x-slot>

    <div class="mx-auto max-w-2xl px-6 py-12 text-gray-800 dark:text-gray-300">
        <h1 class="mb-6 text-2xl font-bold dark:text-white">@lang('azimio::app.legal.privacy-title')</h1>

        <div class="flex flex-col gap-4 text-sm leading-6">
            @foreach (trans('azimio::app.legal.privacy-sections') as $section)
                <div>
                    <h2 class="mb-1 font-semibold dark:text-white">{{ $section['heading'] }}</h2>
                    <p>{{ $section['body'] }}</p>
                </div>
            @endforeach
        </div>

        <p class="mt-8 text-xs text-gray-500">@lang('azimio::app.legal.placeholder-note')</p>
    </div>
</x-admin::layouts.anonymous>
