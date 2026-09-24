@extends('layouts.public')

@section('title', 'Digital Guest Pass — ' . $visitor->name)

@section('content')
<div class="mx-auto max-w-lg px-4 py-8">
    <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-xl transition-all">
        {{-- Pass Top Banner --}}
        <div class="bg-gradient-to-r from-primary/90 to-primary p-6 text-primary-foreground text-center relative">
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-white/20 backdrop-blur-sm mb-2">
                <span class="h-2 w-2 rounded-full {{ $statusColor }}"></span>
                {{ ucfirst($visitor->status->value ?? 'Expected') }}
            </div>
            <h1 class="text-2xl font-bold tracking-tight">{{ $visitor->name }}</h1>
            <p class="text-xs opacity-90 mt-1">Official Gate Clearance Permit</p>
        </div>

        {{-- QR Code & Pass Content --}}
        <div class="p-6 text-center space-y-6">
            {{--
              Live gate code: a signed token that changes every few seconds, so a
              screenshot stops working. Rendered on this server; nothing about
              the pass is sent to a third party.
            --}}
            <div class="mx-auto w-56 h-56 p-3 bg-white rounded-xl shadow-inner border border-slate-200 flex flex-col items-center justify-center">
                <img
                    id="gate-code"
                    src="{{ $code['qr'] ?? '' }}"
                    alt="Live gate code"
                    class="w-full h-full object-contain {{ $code['available'] ? '' : 'hidden' }}"
                    width="200"
                    height="200"
                />
                <p id="gate-code-message" class="text-sm text-slate-600 px-2 {{ $code['available'] ? 'hidden' : '' }}">{{ $code['message'] }}</p>
            </div>
            <p class="text-xs text-muted-foreground">Show this live code to the gatehouse scanner. It refreshes automatically; screenshots will not work.</p>

            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-slate-100 dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 text-xs shadow-xs">
                <span class="text-muted-foreground uppercase font-sans font-medium text-[11px] tracking-wide">Offline Gate PIN:</span>
                <span id="gate-code-pin" class="font-mono font-bold text-foreground text-sm tracking-widest">{{ $code['pin'] ?? ($visitor->gatePass?->offline_pin ?? 'N/A') }}</span>
            </div>

            {{-- Pass Details Grid --}}
            <div class="grid grid-cols-2 gap-3 text-left border-y border-border/60 py-4 text-sm">
                <div>
                    <span class="text-xs text-muted-foreground block">Resident Host</span>
                    <span class="font-semibold text-foreground">{{ $visitor->homeowner_name ?? $visitor->homeowner?->name ?? 'Community Resident' }}</span>
                </div>
                <div>
                    <span class="text-xs text-muted-foreground block">Destination Lot</span>
                    <span class="font-semibold text-foreground">{{ $visitor->homeowner?->lot ?? 'Residential Lot' }}</span>
                </div>
                <div>
                    <span class="text-xs text-muted-foreground block">Pass Type</span>
                    <span class="font-medium text-foreground">{{ ucfirst($visitor->type ?? 'Visitor') }}</span>
                </div>
                <div>
                    <span class="text-xs text-muted-foreground block">Vehicle Reg</span>
                    <span class="font-medium text-foreground">{{ $visitor->vehicle ?: 'Pedestrian / Rideshare' }}</span>
                </div>
                <div class="col-span-2">
                    <span class="text-xs text-muted-foreground block">Valid On</span>
                    <span class="font-medium text-foreground">
                        {{ $visitor->expected_at ? $visitor->expected_at->format('M d, Y — g:i A') : 'Scheduled Date' }}
                    </span>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="space-y-2 pt-2">
                @if ($visitor->status === \App\Enums\VisitorStatus::CheckedIn)
                    <div class="p-3.5 bg-blue-500/10 border border-blue-500/30 rounded-xl text-left space-y-1.5 shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-blue-700 dark:text-blue-300 flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full bg-blue-500 animate-pulse"></span>
                                Currently On-Site at {{ $community?->name ?? 'Community Hub' }}
                            </span>
                            <span class="text-[10px] font-mono text-muted-foreground">Since {{ $visitor->checked_in_at?->format('g:i A') ?? 'Arrival' }}</span>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            When departing the estate, present this QR code to the officer at the outbound gate or use the exit fast-lane terminal.
                        </p>
                    </div>
                @endif
                <a 
                    href="{{ route('pdf.visitor-pass', $visitor->share_token) }}" 
                    class="w-full flex items-center justify-center gap-2 rounded-lg bg-primary py-2.5 px-4 text-sm font-semibold text-primary-foreground shadow-sm hover:opacity-95 transition"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                    Download Printable PDF Pass
                </a>

                <a 
                    href="https://www.google.com/maps/search/?api=1&query={{ urlencode(($community?->name ?? 'Community Hub') . ' Main Gate') }}" 
                    target="_blank" 
                    rel="noopener noreferrer"
                    class="w-full flex items-center justify-center gap-2 rounded-lg border border-border bg-background py-2.5 px-4 text-sm font-medium text-foreground hover:bg-muted transition"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
                    Get GPS Gate Directions
                </a>
            </div>

            <div class="rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground text-left">
                <strong>Gatehouse Security Policy:</strong> All visitors must display this pass and show a valid photo ID upon entry. Speed limit on residential perimeter roads is 15 mph.
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var img = document.getElementById('gate-code');
        var message = document.getElementById('gate-code-message');
        var url = @json(route('guest-pass.code', $visitor->share_token));

        function refresh() {
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (code) {
                    if (!code) { return; }
                    if (code.available) {
                        img.src = code.qr;
                        img.classList.remove('hidden');
                        message.classList.add('hidden');
                    } else {
                        img.classList.add('hidden');
                        message.textContent = code.message;
                        message.classList.remove('hidden');
                    }
                })
                .catch(function () {});
        }

        // Codes are aligned to fixed time slots, so poll at a third of the slot
        // length: the code on screen is never more than a few seconds stale.
        setInterval(refresh, {{ max(5, intdiv((int) config('gatepass.window_seconds', 30), 3)) * 1000 }});
    })();
</script>
@endsection
