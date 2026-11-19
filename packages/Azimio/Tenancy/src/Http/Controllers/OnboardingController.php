<?php

namespace Azimio\Tenancy\Http\Controllers;

use Azimio\Tenancy\OnboardingChecklist;
use Illuminate\Routing\Controller;

/**
 * Dismissing the first-login checklist (7.6).
 */
class OnboardingController extends Controller
{
    public function dismiss(OnboardingChecklist $checklist)
    {
        $checklist->dismiss();

        return back();
    }
}
