<div class="space-y-8" @if($autoPolling) wire:poll.30s="refreshKpis" @endif>
    <!-- Hero / Control Bar -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-200 dark:border-slate-800">
        <div class="space-y-1.5">
            <div class="flex items-center gap-2.5">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                    Live Operations Suite
                </span>
                <span class="text-xs text-slate-400">&bull;</span>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Cypress Bay Community</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                Community Operations Hub
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-2xl">
                Reactive management platform for gate clearance, digital access credentials, resident directory, and verified community security alerts.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <!-- Polling Telemetry Toggle -->
            <button type="button"
                    wire:click="togglePolling"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold border transition-all
                        {{ $autoPolling ? 'bg-emerald-50 dark:bg-emerald-950/60 border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300' : 'bg-slate-50 dark:bg-slate-800/80 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300' }}"
                    title="Toggle auto-refreshing telemetry">
                <span class="w-2 h-2 rounded-full {{ $autoPolling ? 'bg-emerald-500 animate-ping' : 'bg-slate-400' }}"></span>
                <span>{{ $autoPolling ? 'Live Telemetry (30s)' : 'Auto-Refresh Paused' }}</span>
            </button>

            <!-- Manual Refresh Button -->
            <button type="button"
                    wire:click="refreshKpis"
                    class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-colors"
                    title="Refresh KPI Counters">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
            </button>
        </div>
    </div>

    <!-- 4 KPI Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Active Passes -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200 dark:border-slate-800 flex items-center justify-between hover:border-blue-400 dark:hover:border-blue-700 transition-all cursor-pointer"
             wire:click="setTab('passes')">
            <div class="space-y-1">
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Active Gate Passes</div>
                <div class="text-2xl font-black text-slate-900 dark:text-white">{{ $activePassesCount }}</div>
                <div class="text-[11px] text-blue-600 dark:text-blue-400 font-medium">+{{ $todayPassesIssued }} issued today</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            </div>
        </div>

        <!-- On-Site Visitors -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200 dark:border-slate-800 flex items-center justify-between hover:border-emerald-400 dark:hover:border-emerald-700 transition-all cursor-pointer"
             wire:click="setTab('passes')">
            <div class="space-y-1">
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Visitors On Site</div>
                <div class="text-2xl font-black text-slate-900 dark:text-white">{{ $onSiteVisitorsCount }}</div>
                <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Checked in at gates
                </div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
        </div>

        <!-- Security Warnings -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200 dark:border-slate-800 flex items-center justify-between hover:border-rose-400 dark:hover:border-rose-700 transition-all cursor-pointer"
             wire:click="setTab('warnings')">
            <div class="space-y-1">
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Active Alerts</div>
                <div class="text-2xl font-black text-slate-900 dark:text-white">{{ $activeAlertsCount }}</div>
                <div class="text-[11px] text-rose-600 dark:text-rose-400 font-medium">Community feed</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-rose-50 dark:bg-rose-950 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
        </div>

        <!-- Registered Residents -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200 dark:border-slate-800 flex items-center justify-between hover:border-indigo-400 dark:hover:border-indigo-700 transition-all cursor-pointer"
             wire:click="setTab('directory')">
            <div class="space-y-1">
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    @if(!Auth::check() || Gate::allows('manageUsers'))
                        Resident Directory
                    @else
                        Residents & Staff
                    @endif
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white">{{ $totalResidentsCount }}</div>
                <div class="text-[11px] text-indigo-600 dark:text-indigo-400 font-medium">Verified accounts</div>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-slate-200 dark:border-slate-800 flex items-center gap-2 overflow-x-auto pb-1">
        <button type="button"
                wire:click="setTab('overview')"
                class="px-4 py-2.5 text-xs sm:text-sm font-bold rounded-xl transition-all whitespace-nowrap flex items-center gap-2
                    {{ $activeTab === 'overview' ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/25' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800' }}">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
            <span>Operations Overview</span>
        </button>

        <button type="button"
                wire:click="setTab('passes')"
                class="px-4 py-2.5 text-xs sm:text-sm font-bold rounded-xl transition-all whitespace-nowrap flex items-center gap-2
                    {{ $activeTab === 'passes' ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/25' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800' }}">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            <span>Gate Passes & Visitors</span>
        </button>

        @can('manageUsers')
        <button type="button"
                wire:click="setTab('directory')"
                class="px-4 py-2.5 text-xs sm:text-sm font-bold rounded-xl transition-all whitespace-nowrap flex items-center gap-2
                    {{ $activeTab === 'directory' ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/25' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800' }}">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span>Resident Directory</span>
        </button>
        @endcan

        <button type="button"
                wire:click="setTab('warnings')"
                class="px-4 py-2.5 text-xs sm:text-sm font-bold rounded-xl transition-all whitespace-nowrap flex items-center gap-2
                    {{ $activeTab === 'warnings' ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/25' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800' }}">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>Incident Warnings</span>
        </button>

        <button type="button"
                wire:click="setTab('ui-kit')"
                class="px-4 py-2.5 text-xs sm:text-sm font-bold rounded-xl transition-all whitespace-nowrap flex items-center gap-2
                    {{ $activeTab === 'ui-kit' ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/25' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800' }}">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
            <span>Frontend & UI Kit</span>
        </button>

        <button type="button"
                wire:click="setTab('search')"
                class="px-4 py-2.5 text-xs sm:text-sm font-bold rounded-xl transition-all whitespace-nowrap flex items-center gap-2
                    {{ $activeTab === 'search' ? 'bg-indigo-600 text-white shadow-sm shadow-indigo-500/25' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800' }}">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <span>Universal Search</span>
        </button>
    </div>

    <!-- Active Tab Content -->
    <div>
        @if($activeTab === 'overview')
            <div class="space-y-8">
                <!-- Two-Column Activity Feeds -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Recent Gate Passes -->
                    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-sm border border-slate-200 dark:border-slate-800 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                Recently Issued Gate Passes
                            </h3>
                            <button type="button" wire:click="setTab('passes')" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                                View All &rarr;
                            </button>
                        </div>

                        <div class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse($recentPasses as $p)
                                <div class="py-3 flex items-center justify-between text-xs">
                                    <div class="space-y-0.5">
                                        <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                            <span>{{ $p->holder_name }}</span>
                                            <span class="font-mono text-[10px] text-blue-600 dark:text-blue-400 font-semibold">{{ $p->pass_id }}</span>
                                        </div>
                                        <div class="text-slate-500">{{ $p->property }} &middot; {{ $p->designated_gate?->label() ?? 'Any Gate' }}</div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                        {{ $p->status?->label() ?? $p->status }}
                                    </span>
                                </div>
                            @empty
                                <p class="text-xs text-slate-400 py-4 italic">No passes issued yet.</p>
                            @endforelse
                        </div>
                    </div>

                    <!-- Recent Alerts -->
                    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-sm border border-slate-200 dark:border-slate-800 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                Recent Community Alerts
                            </h3>
                            <button type="button" wire:click="setTab('warnings')" class="text-xs font-semibold text-rose-600 dark:text-rose-400 hover:underline">
                                Incident Desk &rarr;
                            </button>
                        </div>

                        <div class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse($recentAlerts as $alert)
                                <div class="py-3 space-y-1 text-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-slate-900 dark:text-white">{{ $alert->title }}</span>
                                        <span class="text-slate-400 text-[11px]">{{ $alert->issued_at?->diffForHumans() }}</span>
                                    </div>
                                    <p class="text-slate-500 line-clamp-2 leading-relaxed">{{ $alert->description }}</p>
                                    <div class="text-[11px] text-slate-400 pt-0.5">
                                        Reported by {{ $alert->author_name }} &middot; {{ $alert->confirmsCount() }} confirmations
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-slate-400 py-4 italic">No active incident reports in the community.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Integration Hub Banner -->
                <div class="bg-gradient-to-r from-blue-900 via-indigo-900 to-slate-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl relative overflow-hidden flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                    <div class="space-y-2 max-w-xl">
                        <span class="text-[11px] uppercase tracking-widest text-cyan-400 font-bold">Full Architecture Synergy</span>
                        <h3 class="text-xl font-bold">Livewire Server Reactivity + Inertia React SPA</h3>
                        <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                            This Community Hub combines Livewire for fast server-driven forms, tables, and security operations alongside Inertia + React for complex interactive dashboards, client-side routing, and interactive maps.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 flex-shrink-0">
                        <button type="button" wire:click="setTab('ui-kit')" class="px-4 py-2.5 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs shadow-md transition-all">
                            Inspect UI Kit & Matrix
                        </button>
                        <a href="/dashboard" class="px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-medium text-xs border border-white/20 transition-all">
                            Switch to React SPA &rarr;
                        </a>
                    </div>
                </div>
            </div>
        @elseif($activeTab === 'passes')
            <livewire:gate-passes.pass-manager />
        @elseif($activeTab === 'directory')
            <livewire:residents.resident-directory />
        @elseif($activeTab === 'warnings')
            <livewire:warnings.warning-desk />
        @elseif($activeTab === 'ui-kit')
            <livewire:components.ui-showcase />
        @elseif($activeTab === 'search')
            <livewire:universal-search />
        @endif
    </div>
</div>
