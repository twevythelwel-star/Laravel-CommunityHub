@extends('layouts.public')

@section('title', 'Sign in — ' . ($branding->app_name ?? 'Community Hub'))
@section('description', 'Sign in to your Community Hub account to manage gate passes, visitors and community services.')

@section('content')
<div class="relative mx-auto flex min-h-[calc(100vh-12rem)] max-w-lg flex-col justify-center px-4 py-8 sm:py-12">

    <!-- Ambient Background Friendly Community Glow -->
    <div class="pointer-events-none absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 h-[420px] w-[420px] rounded-full bg-gradient-to-tr from-amber-200/25 via-orange-100/15 to-emerald-100/20 blur-[100px] dark:from-stone-800/40 dark:via-amber-950/20 dark:to-stone-900/40"></div>

    <div class="relative z-10 overflow-hidden rounded-2xl border border-border/80 bg-card/90 p-6 shadow-2xl backdrop-blur-xl sm:p-9">

        <!-- Header -->
        <div class="mb-7 text-center">
            <div class="inline-flex items-center gap-2 rounded-full border border-border/70 bg-muted/40 px-3 py-1 text-xs font-medium text-muted-foreground backdrop-blur-md mb-3">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                Community Portal Access
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">Welcome Back</h1>
            <p class="mt-1.5 text-xs sm:text-sm text-muted-foreground">Sign in to access your gated residence ecosystem</p>
        </div>

        @if ($errors->any())
            <div role="alert" class="mb-5 rounded-xl border border-destructive/40 bg-destructive/10 p-3.5 shadow-sm">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-destructive flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <p class="text-xs sm:text-sm font-semibold text-destructive">Authentication Error</p>
                </div>
                <ul class="mt-1.5 space-y-0.5 text-xs text-destructive/90 pl-6 list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('success'))
            <div role="status" class="mb-5 rounded-xl border border-emerald-500/40 bg-emerald-500/10 p-3.5 text-xs sm:text-sm text-emerald-700 dark:text-emerald-300 shadow-sm flex items-center gap-2">
                <svg class="h-4 w-4 text-emerald-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <!-- Form Element -->
        <form method="POST" action="{{ route('login') }}" id="liquid-login-form" class="relative">
            @csrf

            <!-- Stage Container for Liquid Metal Pill / Inputs System -->
            <div id="liquid-stage" class="relative mx-auto w-full max-w-[380px] min-h-[72px] select-none">

                <!-- Dynamic SVG Canvas for stretching fluid neck and snapping droplets -->
                <svg id="liquid-svg" class="pointer-events-none absolute inset-0 h-full w-full overflow-visible" style="z-index: 20;" aria-hidden="true">
                    <defs>
                        <!-- Warm Neutral Cashmere / Pearl Silk Gradient (Light Mode) -->
                        <linearGradient id="liquidMetalGradLight" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#ffffff" stop-opacity="1" />
                            <stop offset="25%" stop-color="#faf7f2" stop-opacity="1" />
                            <stop offset="50%" stop-color="#f0e7dc" stop-opacity="1" />
                            <stop offset="75%" stop-color="#e5d9ca" stop-opacity="1" />
                            <stop offset="100%" stop-color="#cfbfaa" stop-opacity="1" />
                        </linearGradient>

                        <!-- Warm Refined Mineral Stone Gradient (Dark Mode) -->
                        <linearGradient id="liquidMetalGradDark" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#46403a" stop-opacity="1" />
                            <stop offset="30%" stop-color="#38322d" stop-opacity="1" />
                            <stop offset="65%" stop-color="#2a2522" stop-opacity="1" />
                            <stop offset="100%" stop-color="#1f1b19" stop-opacity="1" />
                        </linearGradient>

                        <!-- Neutral Friendly Specular Filter -->
                        <filter id="liquidGlow" x="-50%" y="-50%" width="200%" height="200%">
                            <feGaussianBlur in="SourceAlpha" stdDeviation="2.5" result="blur" />
                            <feOffset in="blur" dx="0" dy="1.5" result="offsetBlur" />
                            <feComponentTransfer in="offsetBlur" result="shadow">
                                <feFuncA type="linear" slope="0.22"/>
                            </feComponentTransfer>
                            <feMerge>
                                <feMergeNode in="shadow" />
                                <feMergeNode in="SourceGraphic" />
                            </feMerge>
                        </filter>
                    </defs>

                    <!-- The stretching fluid bridge -->
                    <path id="liquid-neck-path" fill="url(#liquidMetalGradLight)" filter="url(#liquidGlow)" d="" opacity="0" />

                    <!-- Snapped fluid droplets retracting to pills -->
                    <circle id="liquid-droplet-top" cx="0" cy="0" r="0" fill="url(#liquidMetalGradLight)" filter="url(#liquidGlow)" />
                    <circle id="liquid-droplet-bottom" cx="0" cy="0" r="0" fill="url(#liquidMetalGradLight)" filter="url(#liquidGlow)" />
                </svg>

                <!-- TOP CAPSULE: Starts as warm friendly pill, tears upward into Email input -->
                <div id="capsule-top" class="liquid-capsule relative z-10 w-full cursor-pointer transition-all duration-300">
                    <!-- Warm Neutral Fluid Surface -->
                    <div id="capsule-top-skin" class="liquid-chrome-surface relative flex h-14 w-full items-center justify-between rounded-full px-5 overflow-hidden transition-all duration-300">
                        <!-- Soft Warm Pearl Shimmer -->
                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/70 via-transparent to-stone-400/15 mix-blend-overlay"></div>
                        <div class="pointer-events-none absolute -left-1/2 top-0 h-[200%] w-[50%] -rotate-45 bg-gradient-to-r from-transparent via-amber-100/35 dark:via-white/20 to-transparent chrome-shimmer"></div>

                        <!-- Initial Collapsed Label -->
                        <div id="initial-signin-label" class="absolute inset-0 flex items-center justify-center gap-3">
                            <span class="text-base font-semibold tracking-wide text-stone-800 dark:text-stone-100 drop-shadow-[0_1px_1px_rgba(255,255,255,0.8)] dark:drop-shadow-none">
                                Sign in
                            </span>
                            <div class="flex h-7 w-7 items-center justify-center rounded-full bg-stone-800/10 dark:bg-stone-200/15 text-stone-800 dark:text-stone-100 shadow-sm transition-transform group-hover:translate-x-0.5">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </div>
                        </div>

                        <!-- Revealed Email Field -->
                        <div id="email-field-content" class="relative z-10 flex w-full items-center gap-3 opacity-0 transition-opacity duration-200 pointer-events-none">
                            <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-stone-200/70 dark:bg-stone-800/80 text-stone-600 dark:text-stone-300">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206" />
                                </svg>
                            </div>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                required
                                placeholder="name@community.org"
                                autocomplete="username"
                                class="w-full bg-transparent text-sm font-medium text-stone-900 dark:text-stone-100 placeholder-stone-400 dark:placeholder-stone-500 outline-none"
                            >
                        </div>
                    </div>
                </div>

                <!-- BOTTOM CAPSULE: Tears downward into Password input -->
                <div id="capsule-bottom" class="liquid-capsule relative z-10 mt-3 w-full cursor-pointer opacity-0 pointer-events-none transition-all duration-300">
                    <!-- Warm Neutral Fluid Surface -->
                    <div id="capsule-bottom-skin" class="liquid-chrome-surface relative flex h-14 w-full items-center justify-between rounded-full px-5 overflow-hidden transition-all duration-300">
                        <!-- Soft Warm Pearl Shimmer -->
                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/70 via-transparent to-stone-400/15 mix-blend-overlay"></div>
                        <div class="pointer-events-none absolute -left-1/2 top-0 h-[200%] w-[50%] -rotate-45 bg-gradient-to-r from-transparent via-amber-100/35 dark:via-white/20 to-transparent chrome-shimmer"></div>

                        <!-- Revealed Password Field -->
                        <div id="password-field-content" class="relative z-10 flex w-full items-center gap-3">
                            <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-stone-200/70 dark:bg-stone-800/80 text-stone-600 dark:text-stone-300">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                required
                                placeholder="••••••••"
                                autocomplete="current-password"
                                class="w-full bg-transparent text-sm font-medium text-stone-900 dark:text-stone-100 placeholder-stone-400 dark:placeholder-stone-500 outline-none"
                            >
                            <button
                                type="button"
                                id="toggle-password-visibility"
                                class="flex h-7 w-7 items-center justify-center text-stone-400 hover:text-stone-700 dark:text-stone-500 dark:hover:text-stone-200 transition-colors"
                                aria-label="Toggle password visibility"
                            >
                                <svg id="eye-icon" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <!-- AUXILIARY CONTROLS (Revealed below pills when torn) -->
            <div id="liquid-controls" class="mt-4 space-y-4 opacity-0 pointer-events-none transition-all duration-300 max-h-0 overflow-hidden">
                <div class="flex items-center justify-between px-1 text-xs">
                    <label class="flex items-center gap-2 text-muted-foreground hover:text-foreground cursor-pointer select-none">
                        <input type="checkbox" name="remember" value="1" class="rounded border-input text-primary focus:ring-ring">
                        <span>Remember me</span>
                    </label>
                    <button type="button" data-toggle-reset class="text-muted-foreground hover:text-foreground underline-offset-4 hover:underline">
                        Forgot Password?
                    </button>
                </div>

                <!-- Friendly Community Submission Button -->
                <button
                    type="submit"
                    id="liquid-submit-button"
                    class="group relative flex h-12 w-full items-center justify-center overflow-hidden rounded-full font-semibold text-sm tracking-wide text-white shadow-md hover:shadow-lg transition-all duration-200 active:scale-[0.98] bg-gradient-to-r from-stone-900 via-stone-800 to-stone-900 dark:from-stone-800 dark:via-stone-700 dark:to-stone-800 border border-stone-700/60 dark:border-stone-600/60"
                >
                    <!-- Ambient Sheen Overlay -->
                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/15 via-transparent to-black/20"></div>
                    <div class="pointer-events-none absolute -left-1/2 top-0 h-[200%] w-[50%] -rotate-45 bg-gradient-to-r from-transparent via-amber-200/20 to-transparent group-hover:translate-x-[250%] transition-transform duration-700"></div>

                    <!-- Button Content -->
                    <span id="submit-text" class="relative z-10 flex items-center gap-2 text-stone-100 font-semibold drop-shadow-sm">
                        <span>Access Community</span>
                        <svg class="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </span>
                    <span id="submit-spinner" class="relative z-10 hidden items-center gap-2 text-stone-100 font-semibold">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Authenticating...</span>
                    </span>
                </button>
            </div>
        </form>

        <!-- Reset Password Drawer -->
        <div data-reset-panel hidden class="mt-5 rounded-xl border border-border/70 bg-muted/30 p-4 text-xs sm:text-sm text-muted-foreground shadow-sm">
            <p>
                Password resets are securely handled by community administration. Contact your estate office or security dispatch, and they will issue you a verified single-use access code.
            </p>
        </div>

        @if (config('app.env') !== 'production')
            <!-- Demo Credentials Quick Fill Drawer -->
            <details id="credentials-drawer" class="mt-6 rounded-xl border border-border/60 bg-muted/20 p-3.5 transition-all">
                <summary class="cursor-pointer text-xs sm:text-sm font-medium text-foreground flex items-center justify-between select-none">
                    <span class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                        <span>Quick-Fill Demo Credentials</span>
                    </span>
                    <span class="text-[11px] text-muted-foreground bg-muted/60 px-2 py-0.5 rounded-full">1-Click</span>
                </summary>
                <p class="mt-2 text-[11px] text-muted-foreground">Click any role to autofill credentials instantly (default password: <code class="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] font-semibold text-foreground">ChangeMe!2026</code>):</p>
                <div class="mt-2.5 grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                    @foreach ([
                        ['Alexander Wright', 'System Admin',        'alexander.wright@communityhub.org'],
                        ['Elena Rostova',    'Admin',               'elena.rostova@communityhub.org'],
                        ['Marcus Vance',     'Homeowner',           'marcus.vance@residence.net'],
                        ['Sophia Taylor',    'Temporary Homeowner', 'sophia.taylor@residence.net'],
                        ['Security Dispatch','Security',            'dispatch@apexguard.com'],
                        ['Maria Garcia',     'Staff',               'maria.garcia@communitystaff.org'],
                    ] as [$name, $role, $accountEmail])
                        <button
                            type="button"
                            data-fill="{{ $accountEmail }}"
                            class="flex flex-col items-start rounded-lg border border-border/40 bg-card/60 px-2.5 py-1.5 text-left text-xs hover:border-border hover:bg-muted/50 transition-colors"
                        >
                            <span class="font-medium text-foreground truncate w-full">{{ $name }}</span>
                            <span class="text-[10px] text-muted-foreground">{{ $role }}</span>
                        </button>
                    @endforeach
                </div>
            </details>
        @endif

    </div>

    <!-- Security Footnote -->
    <div class="mt-6 flex flex-wrap items-center justify-center gap-4 text-center text-xs text-muted-foreground">
        <span class="flex items-center gap-1.5">
            <svg class="h-3.5 w-3.5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            Encrypted Estate Network
        </span>
        <span>•</span>
        <a href="{{ route('privacy') }}" class="underline hover:text-foreground">Privacy Policy</a>
    </div>
</div>

<!-- Liquid Surface Styling & Physics Simulation Engine -->
<style>
    /* Warm Neutral Friendly Surface (Cashmere Pearl & Mineral Silk) */
    .liquid-chrome-surface {
        background: linear-gradient(135deg,
            #ffffff 0%,
            #faf7f2 20%,
            #f2ece3 40%,
            #e8ded2 60%,
            #efe8de 80%,
            #faf8f5 100%
        );
        box-shadow:
            0 10px 25px -4px rgba(120, 105, 88, 0.12),
            0 4px 10px -2px rgba(120, 105, 88, 0.08),
            inset 0 1.5px 2px rgba(255, 255, 255, 0.95),
            inset 0 -1.5px 2px rgba(185, 170, 150, 0.25);
        border: 1px solid rgba(220, 208, 192, 0.85);
    }

    .dark .liquid-chrome-surface {
        background: linear-gradient(135deg,
            #2e2926 0%,
            #38322e 25%,
            #433c37 50%,
            #332d29 75%,
            #26221f 100%
        );
        box-shadow:
            0 12px 28px -6px rgba(0, 0, 0, 0.5),
            0 4px 12px -2px rgba(0, 0, 0, 0.3),
            inset 0 1px 1.5px rgba(245, 235, 220, 0.25),
            inset 0 -1.5px 2px rgba(0, 0, 0, 0.6);
        border: 1px solid rgba(168, 150, 130, 0.3);
    }

    /* Active open field look */
    .liquid-capsule.is-open .liquid-chrome-surface {
        background: rgba(255, 255, 255, 0.92);
        border: 1.5px solid rgba(195, 180, 160, 0.75);
        box-shadow:
            0 6px 20px -3px rgba(120, 105, 88, 0.09),
            inset 0 1px 3px rgba(0, 0, 0, 0.03);
    }

    .dark .liquid-capsule.is-open .liquid-chrome-surface {
        background: rgba(33, 29, 26, 0.92);
        border: 1.5px solid rgba(160, 140, 120, 0.45);
        box-shadow:
            0 8px 24px -4px rgba(0, 0, 0, 0.45),
            inset 0 1px 2px rgba(255, 255, 255, 0.08);
    }

    .chrome-shimmer {
        animation: fluidSweep 5s ease-in-out infinite;
    }

    @keyframes fluidSweep {
        0%, 15% { transform: translateX(-150%) rotate(-45deg); }
        45%, 100% { transform: translateX(350%) rotate(-45deg); }
    }
</style>

<script>
    (function () {
        var form             = document.getElementById('liquid-login-form');
        var stage            = document.getElementById('liquid-stage');
        var capsuleTop       = document.getElementById('capsule-top');
        var capsuleBottom    = document.getElementById('capsule-bottom');
        var initialLabel     = document.getElementById('initial-signin-label');
        var emailContent     = document.getElementById('email-field-content');
        var passwordContent  = document.getElementById('password-field-content');
        var controls         = document.getElementById('liquid-controls');
        var emailInput       = document.getElementById('email');
        var passwordInput    = document.getElementById('password');
        var submitButton     = document.getElementById('liquid-submit-button');
        var submitText       = document.getElementById('submit-text');
        var submitSpinner    = document.getElementById('submit-spinner');
        var togglePassBtn    = document.getElementById('toggle-password-visibility');

        var svg              = document.getElementById('liquid-svg');
        var neckPath         = document.getElementById('liquid-neck-path');
        var dropletTop       = document.getElementById('liquid-droplet-top');
        var dropletBottom    = document.getElementById('liquid-droplet-bottom');

        var isTorn = false;
        var isAnimating = false;

        // Auto-expand if server validation errors exist or email has old value
        var hasErrors = {{ $errors->any() ? 'true' : 'false' }};
        var hasOldEmail = "{{ old('email') }}" !== "";

        function snapOpenDirectly() {
            if (isTorn) return;
            isTorn = true;
            capsuleTop.classList.add('is-open');
            capsuleBottom.classList.add('is-open');

            initialLabel.style.display = 'none';
            emailContent.style.opacity = '1';
            emailContent.style.pointerEvents = 'auto';

            capsuleBottom.style.opacity = '1';
            capsuleBottom.style.pointerEvents = 'auto';

            controls.style.opacity = '1';
            controls.style.pointerEvents = 'auto';
            controls.style.maxHeight = '200px';

            neckPath.setAttribute('d', '');
            dropletTop.setAttribute('r', '0');
            dropletBottom.setAttribute('r', '0');
        }

        // Spring-physics liquid tearing animation
        function triggerLiquidTear(onComplete) {
            if (isTorn || isAnimating) {
                if (onComplete) onComplete();
                return;
            }
            isAnimating = true;

            // Fade out the initial "Sign in" label
            initialLabel.style.transition = 'opacity 0.15s ease-out';
            initialLabel.style.opacity = '0';

            // Make bottom capsule visible for animation
            var isDark = document.documentElement.classList.contains('dark');
            var neckFill = isDark ? 'url(#liquidMetalGradDark)' : 'url(#liquidMetalGradLight)';
            neckPath.setAttribute('fill', neckFill);
            dropletTop.setAttribute('fill', neckFill);
            dropletBottom.setAttribute('fill', neckFill);

            capsuleBottom.style.opacity = '1';
            capsuleBottom.style.pointerEvents = 'auto';
            neckPath.setAttribute('opacity', '1');

            // Physical spring parameters (matching Settigation reference)
            var startY = 0;
            var targetDistance = 68; // Final vertical distance between pills
            var currentY = 0;
            var velocity = 0;
            var k = 0.09;       // spring tension
            var damping = 0.68; // spring damping
            var snapped = false;
            var dropletProgress = 0;

            var startTime = performance.now();

            function frame(now) {
                var force = (targetDistance - currentY) * k;
                velocity = (velocity + force) * damping;
                currentY += velocity;

                var rectTop = capsuleTop.getBoundingClientRect();
                var stageRect = stage.getBoundingClientRect();

                // Compute relative coordinates inside SVG stage
                var width = stageRect.width;
                var centerX = width / 2;
                var pillHalfWidth = Math.min(width * 0.42, 140);

                var topPillY = 56; // bottom of top capsule
                var bottomPillY = 56 + currentY; // top of bottom capsule

                capsuleBottom.style.transform = 'translateY(' + (currentY - 12) + 'px)';

                var dist = bottomPillY - topPillY;

                // Neck pinch calculation
                var snapDistance = 46; // Distance at which neck snaps

                if (!snapped && dist < snapDistance) {
                    var factor = Math.max(0, 1 - (dist / snapDistance));
                    var waistWidth = pillHalfWidth * Math.pow(factor, 1.4);

                    // Draw organic viscous liquid neck using cubic bezier curves
                    var pathData = [
                        'M', (centerX - pillHalfWidth), topPillY,
                        'C', (centerX - waistWidth), (topPillY + dist * 0.45),
                             (centerX - waistWidth), (bottomPillY - dist * 0.45),
                             (centerX - pillHalfWidth), bottomPillY,
                        'L', (centerX + pillHalfWidth), bottomPillY,
                        'C', (centerX + waistWidth), (bottomPillY - dist * 0.45),
                             (centerX + waistWidth), (topPillY + dist * 0.45),
                             (centerX + pillHalfWidth), topPillY,
                        'Z'
                    ].join(' ');

                    neckPath.setAttribute('d', pathData);
                } else if (!snapped) {
                    // SNAP EVENT! Liquid bridge breaks into two retracting droplets
                    snapped = true;
                    neckPath.setAttribute('d', '');
                    dropletProgress = 1.0;
                }

                if (snapped && dropletProgress > 0) {
                    dropletProgress -= 0.08;
                    var r = Math.max(0, 7 * dropletProgress);
                    var midY = (topPillY + bottomPillY) / 2;

                    // Droplet 1 springs upward into top pill
                    var topDropY = midY - ((1 - dropletProgress) * (midY - topPillY));
                    dropletTop.setAttribute('cx', centerX);
                    dropletTop.setAttribute('cy', topDropY);
                    dropletTop.setAttribute('r', r);

                    // Droplet 2 springs downward into bottom pill
                    var botDropY = midY + ((1 - dropletProgress) * (bottomPillY - midY));
                    dropletBottom.setAttribute('cx', centerX);
                    dropletBottom.setAttribute('cy', botDropY);
                    dropletBottom.setAttribute('r', r);
                }

                // If spring stabilized and droplets absorbed
                if (Math.abs(targetDistance - currentY) < 0.5 && Math.abs(velocity) < 0.2 && dropletProgress <= 0) {
                    // Finalize separation
                    isTorn = true;
                    isAnimating = false;
                    capsuleBottom.style.transform = '';
                    capsuleTop.classList.add('is-open');
                    capsuleBottom.classList.add('is-open');

                    dropletTop.setAttribute('r', '0');
                    dropletBottom.setAttribute('r', '0');

                    initialLabel.style.display = 'none';
                    emailContent.style.opacity = '1';
                    emailContent.style.pointerEvents = 'auto';

                    controls.style.opacity = '1';
                    controls.style.pointerEvents = 'auto';
                    controls.style.maxHeight = '200px';

                    if (onComplete) onComplete();
                    return;
                }

                requestAnimationFrame(frame);
            }

            requestAnimationFrame(frame);
        }

        // Click on capsule triggers liquid tearing
        capsuleTop.addEventListener('click', function () {
            if (!isTorn) {
                triggerLiquidTear(function () {
                    emailInput.focus();
                });
            }
        });

        // Toggle password visibility
        if (togglePassBtn) {
            togglePassBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    togglePassBtn.innerHTML = '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" /></svg>';
                } else {
                    passwordInput.type = 'password';
                    togglePassBtn.innerHTML = '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>';
                }
            });
        }

        // Quick-Fill Demo credentials
        document.querySelectorAll('[data-fill]').forEach(function (button) {
            button.addEventListener('click', function () {
                var fillEmail = button.dataset.fill;
                var defaultPassword = @json(env('SEED_PASSWORD', 'ChangeMe!2026'));
                if (!isTorn) {
                    triggerLiquidTear(function () {
                        emailInput.value = fillEmail;
                        passwordInput.value = defaultPassword;
                        emailInput.focus();
                    });
                } else {
                    emailInput.value = fillEmail;
                    passwordInput.value = defaultPassword;
                    emailInput.focus();
                }
            });
        });

        // Reset Password panel toggle
        var toggleReset = document.querySelector('[data-toggle-reset]');
        var panelReset  = document.querySelector('[data-reset-panel]');
        if (toggleReset && panelReset) {
            toggleReset.addEventListener('click', function () {
                panelReset.hidden = !panelReset.hidden;
            });
        }

        // Form submission: Set splash trigger flag for post-login luxury gate animation!
        if (form) {
            form.addEventListener('submit', function () {
                try {
                    // Set flag so DashboardLayout triggers <WelcomeAnimation> immediately upon arrival
                    sessionStorage.setItem('play_community_splash', 'true');
                } catch (e) {}

                if (submitButton) {
                    submitButton.disabled = true;
                    submitText.classList.add('hidden');
                    submitSpinner.classList.remove('hidden');
                    submitSpinner.classList.add('flex');
                }
            });
        }

        // If error occurred on previous attempt or old email provided, initialize directly
        if (hasErrors || hasOldEmail) {
            snapOpenDirectly();
        }
    })();
</script>
@endsection
