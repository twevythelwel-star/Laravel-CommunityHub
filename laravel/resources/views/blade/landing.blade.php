@extends('layouts.public')

@section('title', 'Sign in — ' . ($branding->app_name ?? 'Community Hub'))
@section('description', 'Sign in to your Community Hub account to manage gate passes, visitors and community services.')

@section('content')
<div class="mx-auto flex min-h-[calc(100vh-14rem)] max-w-md flex-col justify-center px-4 py-12">

    <div class="rounded-xl border border-border/60 bg-card p-6 shadow-sm sm:p-8">

        <div class="mb-6 space-y-1.5">
            <h1 class="text-2xl font-bold tracking-tight">Welcome Back</h1>
            <p class="text-sm text-muted-foreground">Enter your credentials to access your account</p>
        </div>

        @if ($errors->any())
            <div role="alert" class="mb-5 rounded-lg border border-destructive/40 bg-destructive/10 p-3">
                <p class="text-sm font-semibold text-destructive">Login Failed</p>
                <ul class="mt-1 space-y-0.5 text-sm text-destructive/90">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('success'))
            <div role="status" class="mb-5 rounded-lg border border-emerald-500/40 bg-emerald-500/10 p-3 text-sm text-emerald-700 dark:text-emerald-300">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div class="space-y-1.5">
                <label for="email" class="text-sm font-medium">Email</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring"
                >
                @error('email')
                    <p id="email-error" class="text-sm text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <label for="password" class="text-sm font-medium">Password</label>
                    <button type="button" data-toggle-reset class="text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline">
                        Reset Password
                    </button>
                </div>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring"
                >
            </div>

            <label class="flex items-center gap-2 text-sm text-muted-foreground">
                <input type="checkbox" name="remember" value="1" class="rounded border-input">
                Remember me
            </label>

            <button
                type="submit"
                class="w-full rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground transition hover:opacity-90 focus-visible:ring-2 focus-visible:ring-ring"
                data-submit
            >
                Login
            </button>
        </form>

        {{-- Reset panel, revealed by the Reset Password control above. --}}
        <div data-reset-panel hidden class="mt-5 rounded-lg border border-border/60 bg-muted/40 p-4">
            <p class="text-sm text-muted-foreground">
                Password resets are handled by community administration. Contact your estate office
                and they will issue you a new temporary password.
            </p>
        </div>

        @if (config('app.env') !== 'production')
            {{--
                Demo account list, mirroring the "View Test Credentials" panel on the
                original login page. Hidden outside local and staging environments so a
                production deployment never advertises seeded accounts.
            --}}
            <details class="mt-6 rounded-lg border border-border/60 bg-muted/30 p-4">
                <summary class="cursor-pointer text-sm font-medium">View Test Credentials</summary>
                <p class="mt-2 text-xs text-muted-foreground">Click any account below to fill the form.</p>
                <ul class="mt-3 space-y-1.5">
                    @foreach ([
                        ['Alexander Wright', 'System Admin',         'alexander.wright@communityhub.org'],
                        ['Elena Rostova',    'Admin',                'elena.rostova@communityhub.org'],
                        ['Marcus Vance',     'Homeowner',            'marcus.vance@residence.net'],
                        ['Sophia Taylor',    'Temporary Homeowner',  'sophia.taylor@residence.net'],
                        ['Security Dispatch','Security',             'dispatch@apexguard.com'],
                        ['Maria Garcia',     'Staff',                'maria.garcia@communitystaff.org'],
                    ] as [$name, $role, $accountEmail])
                        <li>
                            <button
                                type="button"
                                data-fill="{{ $accountEmail }}"
                                class="flex w-full items-center justify-between gap-3 rounded-md px-2 py-1.5 text-left text-xs hover:bg-muted"
                            >
                                <span class="font-medium">{{ $name }}</span>
                                <span class="text-muted-foreground">{{ $role }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>

    <p class="mt-6 text-center text-xs text-muted-foreground">
        By signing in you agree to our <a href="{{ route('privacy') }}" class="underline underline-offset-4">Privacy Policy</a>.
    </p>
</div>

<script>
    (function () {
        var form = document.querySelector('form[action="{{ route('login') }}"]');

        // Prefill from the demo credentials list.
        document.querySelectorAll('[data-fill]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.getElementById('email').value = button.dataset.fill;
                document.getElementById('password').focus();
            });
        });

        var toggle = document.querySelector('[data-toggle-reset]');
        var panel  = document.querySelector('[data-reset-panel]');

        if (toggle && panel) {
            toggle.addEventListener('click', function () {
                panel.hidden = !panel.hidden;
            });
        }

        // Disable on submit so a double click cannot post twice.
        if (form) {
            form.addEventListener('submit', function () {
                var submit = form.querySelector('[data-submit]');
                if (submit) {
                    submit.disabled = true;
                    submit.textContent = 'Logging in...';
                }
            });
        }
    })();
</script>
@endsection
