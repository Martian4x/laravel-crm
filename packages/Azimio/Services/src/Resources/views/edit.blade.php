<x-admin::layouts>
    <x-slot:title>
        @lang('services::app.edit')
    </x-slot>

    <form method="POST" action="{{ route('admin.services.update', $service->id) }}" class="flex flex-col gap-4">
        @csrf
        @method('PUT')

        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xl font-bold dark:text-gray-300">{{ $service->name }}</div>

            <a href="{{ route('admin.services.index') }}" class="text-sm text-gray-600 dark:text-gray-400">
                @lang('services::app.back')
            </a>
        </div>

        @include('services::form')
    </form>
</x-admin::layouts>
