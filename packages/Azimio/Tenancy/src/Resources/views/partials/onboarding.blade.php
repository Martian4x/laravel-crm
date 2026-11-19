@php($checklist = app(\Azimio\Tenancy\OnboardingChecklist::class))
@php($tenant = tenant())

@if ($tenant && $checklist->shouldShow($tenant))
    @php($steps = $checklist->steps($tenant))
    @php($done = $checklist->completedCount($tenant))

    {{--
        7.6 — shown until every step is done OR the tenant dismisses it.
        Steps are derived from real data, so a step un-ticks itself if
        whatever satisfied it is later removed.
    --}}
    <div class="mb-5 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-base font-semibold text-blue-900 dark:text-blue-200">
                    @lang('azimio::app.onboarding.title', ['company' => $tenant->name])
                </p>

                <p class="mt-1 text-sm text-blue-800 dark:text-blue-300">
                    @lang('azimio::app.onboarding.progress', [
                        'done'  => $done,
                        'total' => count($steps),
                    ])
                </p>
            </div>

            <form method="POST" action="{{ route('azimio.onboarding.dismiss') }}">
                @csrf

                <button type="submit" class="text-xs font-semibold text-blue-700 underline dark:text-blue-300">
                    @lang('azimio::app.onboarding.dismiss')
                </button>
            </form>
        </div>

        <ul class="mt-4 grid gap-2 md:grid-cols-2">
            @foreach ($steps as $step)
                <li class="flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm dark:bg-gray-900">
                    @if ($step['done'])
                        <span class="text-emerald-600">&check;</span>

                        <span class="text-gray-500 line-through dark:text-gray-500">
                            @lang('azimio::app.onboarding.step-' . $step['key'])
                        </span>
                    @else
                        <span class="text-gray-300">&#9675;</span>

                        <a href="{{ $step['route'] }}" class="font-medium text-blue-700 hover:underline dark:text-blue-300">
                            @lang('azimio::app.onboarding.step-' . $step['key'])
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
