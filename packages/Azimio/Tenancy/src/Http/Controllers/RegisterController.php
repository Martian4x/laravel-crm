<?php

namespace Azimio\Tenancy\Http\Controllers;

use Azimio\Tenancy\Mail\NewTenantRegistered;
use Azimio\Tenancy\TenantOnboarder;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Webkul\User\Models\User;

class RegisterController extends Controller
{
    /**
     * Show the public tenant registration form.
     */
    public function create()
    {
        return view('azimio::register');
    }

    /**
     * Register a company: create the tenant + its admin, log the admin in.
     */
    public function store(TenantOnboarder $onboarder)
    {
        /**
         * Honeypot: the "website" field is hidden from humans; bots that
         * fill it get a silent redirect with no account created.
         */
        if (request()->filled('website')) {
            return redirect()->route('admin.session.create');
        }

        $validated = request()->validate([
            'company_name' => 'required|string|max:255',
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|min:6|confirmed',
            'locale'       => 'required|in:'.implode(',', array_keys(config('app.available_locales'))),
            'terms'        => 'accepted',
        ]);

        $tenant = $onboarder->onboard(
            [
                'name'   => $validated['company_name'],
                'locale' => $validated['locale'],
            ],
            [
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => $validated['password'],
            ]
        );

        $this->notifySuperAdmins($tenant, $validated);

        $admin = User::withoutTenancy()->where('email', $validated['email'])->first();

        auth()->guard('user')->login($admin);

        session()->flash('success', trans('azimio::app.register.welcome', ['company' => $tenant->name]));

        return redirect()->route('admin.dashboard.index');
    }

    /**
     * Tell every platform super admin about the new registration.
     */
    protected function notifySuperAdmins($tenant, array $validated): void
    {
        try {
            $recipients = User::withoutTenancy()
                ->where('is_super_admin', 1)
                ->where('status', 1)
                ->pluck('email')
                ->all();

            if (! empty($recipients)) {
                Mail::to($recipients)->queue(
                    new NewTenantRegistered($tenant, $validated['name'], $validated['email'])
                );
            }
        } catch (\Exception $e) {
            Log::warning('New-tenant notification failed: '.$e->getMessage());
        }
    }
}
