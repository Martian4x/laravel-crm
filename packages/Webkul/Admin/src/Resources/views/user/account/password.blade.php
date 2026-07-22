<x-admin::layouts>
    <!-- Page title -->
    <x-slot:title>
        @lang('admin::app.account.edit.change-password')
    </x-slot>

    {!! view_render_event('admin.user.account.password.form.before', ['user' => $user]) !!}

    <!-- Change Password Form -->
    <x-admin::form
        :action="route('admin.user.account.password.update')"
        method="PUT"
    >
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <div class="text-xl font-bold dark:text-white">
                    @lang('admin::app.account.edit.change-password')
                </div>
            </div>

            <div class="flex items-center gap-x-2.5">
                <!-- Back to account -->
                <a
                    href="{{ route('admin.user.account.edit') }}"
                    class="secondary-button"
                >
                    @lang('admin::app.account.edit.back-btn')
                </a>

                <button
                    type="submit"
                    class="primary-button"
                >
                    @lang('admin::app.account.edit.update-password-btn')
                </button>
            </div>
        </div>

        <!-- Panel -->
        <div class="mt-3.5 flex gap-2.5 max-xl:flex-wrap">
            <div class="flex flex-1 flex-col gap-2">
                <div class="box-shadow rounded bg-white p-4 dark:bg-gray-900">
                    <p class="mb-4 max-w-2xl text-xs text-gray-600 dark:text-gray-300">
                        @lang('admin::app.account.edit.change-password-info')
                    </p>

                    {!! view_render_event('admin.user.current_password.before', ['user' => $user]) !!}

                    <!-- Current Password -->
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label class="required">
                            @lang('admin::app.account.edit.current-password')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="password"
                            name="current_password"
                            rules="required|min:6"
                            class="w-full max-w-md"
                            :label="trans('admin::app.account.edit.current-password')"
                            :placeholder="trans('admin::app.account.edit.current-password')"
                        />

                        <x-admin::form.control-group.error control-name="current_password" />
                    </x-admin::form.control-group>

                    {!! view_render_event('admin.user.current_password.after', ['user' => $user]) !!}

                    {!! view_render_event('admin.user.password.before', ['user' => $user]) !!}

                    <!-- New Password -->
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label class="required">
                            @lang('admin::app.account.edit.password')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="password"
                            name="password"
                            rules="required|min:6"
                            class="w-full max-w-md"
                            ref="password"
                            :label="trans('admin::app.account.edit.password')"
                            :placeholder="trans('admin::app.account.edit.password')"
                        />

                        <x-admin::form.control-group.error control-name="password" />
                    </x-admin::form.control-group>

                    {!! view_render_event('admin.user.password.after', ['user' => $user]) !!}

                    {!! view_render_event('admin.user.confirm-password.before', ['user' => $user]) !!}

                    <!-- Confirm Password -->
                    <x-admin::form.control-group class="!mb-0">
                        <x-admin::form.control-group.label class="required">
                            @lang('admin::app.account.edit.confirm-password')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="password"
                            name="password_confirmation"
                            rules="required|confirmed:@password"
                            class="w-full max-w-md"
                            :label="trans('admin::app.account.edit.confirm-password')"
                            :placeholder="trans('admin::app.account.edit.confirm-password')"
                        />

                        <x-admin::form.control-group.error control-name="password_confirmation" />
                    </x-admin::form.control-group>

                    {!! view_render_event('admin.user.confirm-password.after', ['user' => $user]) !!}
                </div>
            </div>
        </div>
    </x-admin::form>

    {!! view_render_event('admin.user.account.password.form.after', ['user' => $user]) !!}
</x-admin::layouts>
