<?php

namespace Azimio\Tenancy\Http\Controllers;

use Azimio\Tenancy\Mail\VerifyTenantEmail;
use Azimio\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Webkul\User\Models\User;

/**
 * Email verification for a self-registered company (7.2).
 *
 * A tenant stays `pending` (activated_at NULL) until its registering admin
 * clicks the link. Until then they can sign in but cannot use the CRM, and
 * IdentifyTenant sends them here rather than logging them out with a
 * "suspended" message they would not understand.
 *
 * The link is a Laravel SIGNED url, so it cannot be forged or edited to
 * verify a different account, and it expires.
 */
class VerifyEmailController extends Controller
{
    /**
     * "Check your email" — shown right after registering.
     */
    public function notice(Request $request)
    {
        return view('azimio::verify-notice', [
            'email' => $request->session()->get('azimio_pending_email'),
        ]);
    }

    /**
     * Consume the signed link.
     */
    public function verify(int $id, Request $request)
    {
        // hasValidSignature() is what makes this safe: the id cannot be
        // swapped for someone else's without invalidating the signature.
        if (! $request->hasValidSignature()) {
            return view('azimio::verify-notice', [
                'email'   => null,
                'expired' => true,
            ]);
        }

        $user = User::withoutTenancy()->find($id);

        if (! $user || ! $user->tenant_id) {
            return redirect()->route('admin.session.create');
        }

        $tenant = Tenant::withoutGlobalScopes()->find($user->tenant_id);

        if (! $tenant) {
            return redirect()->route('admin.session.create');
        }

        // Idempotent: clicking an old link again must not break anything,
        // and people do forward these mails to themselves.
        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        if ($tenant->isPending()) {
            $tenant->activate();
        }

        auth()->guard('user')->login($user);

        session()->flash('success', trans('azimio::app.verify.verified', ['company' => $tenant->name]));

        return redirect()->route('admin.dashboard.index');
    }

    /**
     * Send the link again.
     *
     * Throttled on the route. Deliberately gives the same response whether
     * or not the address exists, so this cannot be used to discover which
     * emails are registered.
     */
    public function resend(Request $request)
    {
        $validated = $request->validate(['email' => 'required|email']);

        $user = User::withoutTenancy()
            ->where('email', $validated['email'])
            ->whereNull('email_verified_at')
            ->first();

        if ($user) {
            self::send($user);
        }

        session()->flash('success', trans('azimio::app.verify.resent'));

        return redirect()->route('azimio.verify.notice');
    }

    /**
     * Build and queue the verification mail. Static so registration can
     * call it without constructing the controller.
     */
    public static function send(User $user): void
    {
        try {
            $url = URL::temporarySignedRoute(
                'azimio.verify.verify',
                now()->addDays(7),
                ['id' => $user->id],
            );

            Mail::to($user->email)->queue(new VerifyTenantEmail($user, $url));
        } catch (\Throwable $e) {
            // The account exists and the link can be resent, so a mail
            // outage must not lose the registration.
            Log::error('Tenant verification mail failed: '.$e->getMessage());
        }
    }
}
