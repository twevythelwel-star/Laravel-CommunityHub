<?php

namespace App\Http\Controllers;

use App\Models\BrandingSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Public entry and policy pages, rendered with Blade.
 *
 * Replaces src/app/page.tsx and src/app/privacy/page.tsx. In the original app
 * "/" was itself the login screen, so that behaviour is kept: a signed-out
 * visitor lands on the sign-in form, a signed-in one goes straight through.
 *
 * Neither page carries application state, so serving them as HTML avoids
 * shipping the React bundle to visitors who have not signed in.
 */
class PublicPageController extends Controller
{
    public function landing(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard.index');
        }

        return view('blade.landing', [
            'branding' => BrandingSetting::current(),
        ]);
    }

    public function privacy(): View
    {
        return view('blade.privacy', [
            'branding' => BrandingSetting::current(),
        ]);
    }
}
