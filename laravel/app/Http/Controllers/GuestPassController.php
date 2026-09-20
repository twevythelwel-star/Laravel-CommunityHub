<?php

namespace App\Http\Controllers;

use App\Enums\VisitorStatus;
use App\Models\BrandingSetting;
use App\Models\Community;
use App\Models\Visitor;
use Illuminate\View\View;

class GuestPassController extends Controller
{
    /**
     * Display the public, mobile-first guest pass.
     */
    public function show(string $token): View
    {
        $visitor = Visitor::with('homeowner')->where('share_token', $token)->firstOrFail();
        $community = Community::first();

        $isExpired = $visitor->expired_at !== null ||
            ($visitor->expected_at && $visitor->expected_at->addHours(12)->isPast() && $visitor->status === VisitorStatus::Expected);

        $statusColor = match ($visitor->status) {
            VisitorStatus::CheckedIn => 'bg-emerald-500',
            VisitorStatus::CheckedOut => 'bg-slate-500',
            VisitorStatus::Expected => $isExpired ? 'bg-rose-500' : 'bg-primary',
        };

        return view('blade.guest-pass', [
            'visitor' => $visitor,
            'community' => $community,
            'branding' => BrandingSetting::current(),
            'isExpired' => $isExpired,
            'statusColor' => $statusColor,
        ]);
    }
}
