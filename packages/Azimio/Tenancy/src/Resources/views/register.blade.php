<x-admin::layouts.anonymous>
    <x-slot:title>
        @lang('azimio::app.register.title')
    </x-slot>

    <div class="flex min-h-[100vh] flex-col items-center justify-center gap-6 py-8">
        <div class="flex flex-col items-center gap-5">
            <!-- Platform logo (tenant is unknown before registration) -->
            <img
                class="w-max"
                src="{{ vite()->asset('images/logo.svg') }}"
                alt="{{ config('app.name') }}"
            />

            <div class="box-shadow flex w-[360px] max-w-full flex-col rounded-md bg-white dark:bg-gray-900">
                <x-admin::form :action="route('azimio.register.store')">
                    <p class="p-4 text-xl font-bold text-gray-800 dark:text-white">
                        @lang('azimio::app.register.title')
                    </p>

                    <div class="border-y p-4 dark:border-gray-800">
                        <!-- Honeypot (hidden from humans) -->
                        <div class="hidden" aria-hidden="true">
                            <input type="text" name="website" tabindex="-1" autocomplete="off" />
                        </div>

                        <!-- Company Name -->
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label class="required">
                                @lang('azimio::app.register.company-name')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="text"
                                name="company_name"
                                rules="required"
                                :value="old('company_name')"
                                :label="trans('azimio::app.register.company-name')"
                                :placeholder="trans('azimio::app.register.company-name')"
                            />

                            <x-admin::form.control-group.error control-name="company_name" />
                        </x-admin::form.control-group>

                        <!-- Your Name -->
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label class="required">
                                @lang('azimio::app.register.your-name')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="text"
                                name="name"
                                rules="required"
                                :value="old('name')"
                                :label="trans('azimio::app.register.your-name')"
                                :placeholder="trans('azimio::app.register.your-name')"
                            />

                            <x-admin::form.control-group.error control-name="name" />
                        </x-admin::form.control-group>

                        <!-- Email -->
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label class="required">
                                @lang('azimio::app.register.email')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="email"
                                name="email"
                                rules="required|email"
                                :value="old('email')"
                                :label="trans('azimio::app.register.email')"
                                placeholder="email@company.co.tz"
                            />

                            <x-admin::form.control-group.error control-name="email" />
                        </x-admin::form.control-group>

                        <!-- Password -->
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label class="required">
                                @lang('azimio::app.register.password')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="password"
                                name="password"
                                rules="required|min:6"
                                ref="password"
                                :label="trans('azimio::app.register.password')"
                                :placeholder="trans('azimio::app.register.password')"
                            />

                            <x-admin::form.control-group.error control-name="password" />
                        </x-admin::form.control-group>

                        <!-- Confirm Password -->
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label class="required">
                                @lang('azimio::app.register.confirm-password')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="password"
                                name="password_confirmation"
                                rules="required|confirmed:@password"
                                :label="trans('azimio::app.register.confirm-password')"
                                :placeholder="trans('azimio::app.register.confirm-password')"
                            />

                            <x-admin::form.control-group.error control-name="password_confirmation" />
                        </x-admin::form.control-group>

                        <!-- Language -->
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label class="required">
                                @lang('azimio::app.register.language')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="select"
                                name="locale"
                                :value="old('locale', 'sw')"
                                rules="required"
                                :label="trans('azimio::app.register.language')"
                            >
                                @foreach (config('app.available_locales') as $code => $title)
                                    <option value="{{ $code }}">{{ $title }}</option>
                                @endforeach
                            </x-admin::form.control-group.control>

                            <x-admin::form.control-group.error control-name="locale" />
                        </x-admin::form.control-group>

                        <!-- Terms -->
                        <x-admin::form.control-group class="!mb-0">
                            <label class="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    name="terms"
                                    value="1"
                                    class="mt-0.5"
                                    @checked(old('terms'))
                                />

                                <span>
                                    @lang('azimio::app.register.accept-terms', [
                                        'terms'   => '<a class="text-brandColor hover:underline" href="'.route('azimio.terms').'" target="_blank">'.trans('azimio::app.legal.terms-title').'</a>',
                                        'privacy' => '<a class="text-brandColor hover:underline" href="'.route('azimio.privacy').'" target="_blank">'.trans('azimio::app.legal.privacy-title').'</a>',
                                    ])
                                </span>
                            </label>

                            @error('terms')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </x-admin::form.control-group>
                    </div>

                    <div class="flex flex-col gap-3 p-4">
                        <button class="primary-button w-full justify-center" type="submit">
                            @lang('azimio::app.register.submit-btn')
                        </button>

                        <a
                            class="text-center text-sm text-brandColor hover:underline"
                            href="{{ route('admin.session.create') }}"
                        >
                            @lang('azimio::app.register.sign-in-link')
                        </a>
                    </div>
                </x-admin::form>
            </div>
        </div>
    </div>
</x-admin::layouts.anonymous>
