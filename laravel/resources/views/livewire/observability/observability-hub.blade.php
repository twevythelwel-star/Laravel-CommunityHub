<div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-8 animate-fade-in font-sans">
    {{-- Header Banner / Hero Capsule --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 p-6 sm:p-8 border border-slate-800 shadow-2xl text-white">
        <div class="absolute -right-16 -top-16 w-72 h-72 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute right-32 -bottom-20 w-80 h-80 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 mb-3">
                    <span class="w-2.5 h-2.5 rounded-full {{ $systemStatus['is_healthy'] ? 'bg-emerald-400 animate-ping' : 'bg-amber-400' }}"></span>
                    Observability &amp; Telemetry Suite
                </div>
                <h1 class="text-3xl sm:text-4xl font-black text-white tracking-tight">Enterprise Observability Hub</h1>
                <p class="mt-2 text-slate-300 text-sm sm:text-base max-w-2xl">
                    Unified operational visibility combining Telescope developer introspection, Pulse application performance, Horizon queue telemetry, and proactive error tracking.
                </p>
            </div>

            {{-- Telemetry Capsule --}}
            <div class="p-4 rounded-2xl bg-slate-900/90 border border-slate-800 flex flex-wrap gap-4 text-xs font-mono">
                <div>
                    <div class="text-slate-500 uppercase text-[10px]">Status</div>
                    <div class="font-bold uppercase {{ $systemStatus['is_healthy'] ? 'text-emerald-400' : 'text-amber-400' }}">
                        {{ $systemStatus['status'] }}
                    </div>
                </div>
                <div class="border-l border-slate-800 pl-4">
                    <div class="text-slate-500 uppercase text-[10px]">Subsystems</div>
                    <div class="font-bold text-slate-200">{{ $systemStatus['subsystem_counts']['healthy'] }} / {{ $systemStatus['subsystem_counts']['total'] }} OK</div>
                </div>
                <div class="border-l border-slate-800 pl-4">
                    <div class="text-slate-500 uppercase text-[10px]">Avg Latency</div>
                    <div class="font-bold text-indigo-400">{{ $performanceMetrics['latency']['average_ms'] }}ms</div>
                </div>
                <div class="border-l border-slate-800 pl-4">
                    <div class="text-slate-500 uppercase text-[10px]">Environment</div>
                    <div class="font-bold text-cyan-400 uppercase">{{ $systemStatus['environment'] }}</div>
                </div>
            </div>
        </div>

        {{-- Navigation Tabs --}}
        <div class="mt-8 flex flex-wrap gap-2 border-t border-slate-800/80 pt-4">
            <button wire:click="selectTab('overview')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'overview' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                System Overview
            </button>
            <button wire:click="selectTab('health')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'health' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Health Checks &amp; Probes
            </button>
            <button wire:click="selectTab('performance')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'performance' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Performance Metrics (Pulse)
            </button>
            <button wire:click="selectTab('logs')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'logs' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Application Logs
            </button>
            <button wire:click="selectTab('audit')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'audit' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Audit Logs
            </button>
            <button wire:click="selectTab('errors')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'errors' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Error Tracking &amp; Exceptions
            </button>
            <button wire:click="selectTab('queues')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'queues' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Queue &amp; Horizon Metrics
            </button>
        </div>
    </div>

    {{-- Feedback Flash --}}
    @if ($feedbackMessage)
        <div class="p-4 rounded-2xl flex items-center justify-between border bg-emerald-500/10 border-emerald-500/30 text-emerald-300 text-sm font-semibold animate-fade-in">
            <div class="flex items-center gap-3">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>{{ $feedbackMessage }}</span>
            </div>
            <button wire:click="$set('feedbackMessage', null)" class="text-xs text-emerald-400 hover:text-white underline">Dismiss</button>
        </div>
    @endif

    {{-- TAB 1: SYSTEM OVERVIEW --}}
    @if ($activeTab === 'overview')
        <div class="space-y-6">
            {{-- KPI Summary Grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="text-slate-500 text-xs font-semibold uppercase tracking-wider">System Verdict</div>
                    <div class="mt-2 flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full {{ $systemStatus['is_healthy'] ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                        <div class="text-2xl font-black text-slate-900 dark:text-white capitalize">{{ str_replace('_', ' ', $systemStatus['status']) }}</div>
                    </div>
                    <div class="mt-1 text-xs text-slate-400">{{ $systemStatus['subsystem_counts']['healthy'] }} of {{ $systemStatus['subsystem_counts']['total'] }} subsystems healthy</div>
                </div>

                <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="text-slate-500 text-xs font-semibold uppercase tracking-wider">DB Latency Ping</div>
                    <div class="mt-2 text-2xl font-black text-indigo-600 dark:text-indigo-400">{{ $healthChecks['database']['latency_ms'] ?? 0 }} ms</div>
                    <div class="mt-1 text-xs text-slate-400">Connection: {{ $healthChecks['database']['connection'] ?? 'default' }}</div>
                </div>

                <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="text-slate-500 text-xs font-semibold uppercase tracking-wider">Pending Queued Jobs</div>
                    <div class="mt-2 text-2xl font-black text-cyan-600 dark:text-cyan-400">{{ $queueMetrics['queue_summary']['total_pending'] ?? 0 }}</div>
                    <div class="mt-1 text-xs text-slate-400">Failed: {{ $queueMetrics['queue_summary']['failed_jobs'] ?? 0 }} | Horizon: {{ $queueMetrics['horizon']['status'] ?? 'inactive' }}</div>
                </div>

                <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="text-slate-500 text-xs font-semibold uppercase tracking-wider">Active Tracked Errors</div>
                    <div class="mt-2 text-2xl font-black text-rose-600 dark:text-rose-400">{{ $errorMetrics['unresolved_count'] }}</div>
                    <div class="mt-1 text-xs text-slate-400">{{ $errorMetrics['critical_count'] }} critical anomalies</div>
                </div>
            </div>

            {{-- 7 Pillars Navigation Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {{-- Health Check Pillar --}}
                <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between space-y-4">
                    <div>
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Health Checks &amp; Probes</h3>
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400">Pillar 1</span>
                        </div>
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Continuous health probes of DB, Redis, filesystem, queues, and WebSocket infrastructure.</p>
                        <div class="mt-4 space-y-2 text-xs font-mono">
                            @foreach ($healthChecks as $name => $c)
                                <div class="flex justify-between items-center py-1 border-b border-slate-100 dark:border-slate-800">
                                    <span class="capitalize text-slate-600 dark:text-slate-400">{{ $name }}</span>
                                    <span class="font-bold {{ ($c['status'] ?? '') === 'healthy' ? 'text-emerald-500' : 'text-amber-500' }}">{{ $c['status'] ?? 'ok' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <button wire:click="selectTab('health')" class="w-full py-2 rounded-xl text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 transition">
                        View Subsystem Probes &rarr;
                    </button>
                </div>

                {{-- Performance Metrics Pillar --}}
                <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between space-y-4">
                    <div>
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Performance Metrics (Pulse)</h3>
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-500/10 text-indigo-400">Pillar 2</span>
                        </div>
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Operational latency percentiles, throughput RPM, and server memory consumption.</p>
                        <div class="mt-4 space-y-2 text-xs font-mono">
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                <span class="text-slate-500">Avg Latency</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ $performanceMetrics['latency']['average_ms'] }} ms</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                <span class="text-slate-500">95th Percentile</span>
                                <span class="font-bold text-indigo-400">{{ $performanceMetrics['latency']['p95_ms'] }} ms</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                <span class="text-slate-500">Memory Footprint</span>
                                <span class="font-bold text-emerald-400">{{ $performanceMetrics['memory']['current_mb'] }} MB</span>
                            </div>
                        </div>
                    </div>
                    <button wire:click="selectTab('performance')" class="w-full py-2 rounded-xl text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 transition">
                        Explore Performance Telemetry &rarr;
                    </button>
                </div>

                {{-- Error Tracking Pillar --}}
                <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between space-y-4">
                    <div>
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">Error Tracking</h3>
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-rose-500/10 text-rose-400">Pillar 3</span>
                        </div>
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Exception fingerprint deduplication, severity rating, and resolution workflow.</p>
                        <div class="mt-4 space-y-2 text-xs font-mono">
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                <span class="text-slate-500">Total Tracked</span>
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ $errorMetrics['total_tracked'] }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                <span class="text-slate-500">Unresolved</span>
                                <span class="font-bold text-rose-500">{{ $errorMetrics['unresolved_count'] }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                <span class="text-slate-500">Critical Alerts</span>
                                <span class="font-bold text-amber-500">{{ $errorMetrics['critical_count'] }}</span>
                            </div>
                        </div>
                    </div>
                    <button wire:click="selectTab('errors')" class="w-full py-2 rounded-xl text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 transition">
                        Manage Exception Tracking &rarr;
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- TAB 2: HEALTH CHECKS --}}
    @if ($activeTab === 'health')
        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Subsystem Health Diagnostics</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Deep runtime probes evaluating responsiveness and network latency across core components.</p>
                </div>
                <button wire:click="runHealthProbes" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md transition flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-white animate-ping"></span>
                    Run Health Probes Now
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($healthChecks as $name => $check)
                    <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $name }} Probe</span>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase {{ ($check['status'] ?? '') === 'healthy' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20' }}">
                                {{ $check['status'] ?? 'unknown' }}
                            </span>
                        </div>
                        <div class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                            {{ $check['details'] ?? 'Component status optimal.' }}
                        </div>
                        @if (isset($check['latency_ms']))
                            <div class="text-xs font-mono text-slate-400 flex justify-between">
                                <span>Round-trip Latency:</span>
                                <span class="font-bold text-indigo-400">{{ $check['latency_ms'] }} ms</span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- TAB 3: PERFORMANCE METRICS --}}
    @if ($activeTab === 'performance')
        <div class="space-y-6">
            <div>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">Performance Telemetry (Pulse / Telescope Inspired)</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Response time percentiles, server memory metrics, and query execution speeds.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="text-slate-500 text-xs font-semibold uppercase">Average Latency</div>
                    <div class="mt-2 text-2xl font-black text-indigo-600 dark:text-indigo-400">{{ $performanceMetrics['latency']['average_ms'] }} ms</div>
                    <div class="mt-1 text-xs text-slate-400">p50: {{ $performanceMetrics['latency']['p50_ms'] }} ms</div>
                </div>

                <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="text-slate-500 text-xs font-semibold uppercase">95th &amp; 99th Percentile</div>
                    <div class="mt-2 text-2xl font-black text-indigo-500">{{ $performanceMetrics['latency']['p95_ms'] }} ms</div>
                    <div class="mt-1 text-xs text-slate-400">p99: {{ $performanceMetrics['latency']['p99_ms'] }} ms</div>
                </div>

                <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="text-slate-500 text-xs font-semibold uppercase">Memory Footprint</div>
                    <div class="mt-2 text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $performanceMetrics['memory']['current_mb'] }} MB</div>
                    <div class="mt-1 text-xs text-slate-400">Peak: {{ $performanceMetrics['memory']['peak_mb'] }} MB (Limit: {{ $performanceMetrics['memory']['limit'] }})</div>
                </div>

                <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="text-slate-500 text-xs font-semibold uppercase">Cache Hit Ratio</div>
                    <div class="mt-2 text-2xl font-black text-cyan-600 dark:text-cyan-400">{{ $performanceMetrics['cache']['hit_ratio_percent'] }}%</div>
                    <div class="mt-1 text-xs text-slate-400">Store Driver: {{ $performanceMetrics['cache']['driver'] }}</div>
                </div>
            </div>

            {{-- Request Breakdown --}}
            <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">API Traffic Status Code Distribution (Last 24 Hours)</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 font-mono text-xs">
                    <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40">
                        <div class="text-emerald-700 dark:text-emerald-400 font-bold text-lg">{{ $performanceMetrics['requests_24h']['status_codes']['success_2xx'] }}</div>
                        <div class="text-slate-500">2xx Success Requests</div>
                    </div>
                    <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/40">
                        <div class="text-amber-700 dark:text-amber-400 font-bold text-lg">{{ $performanceMetrics['requests_24h']['status_codes']['client_error_4xx'] }}</div>
                        <div class="text-slate-500">4xx Client Errors</div>
                    </div>
                    <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-800/40">
                        <div class="text-rose-700 dark:text-rose-400 font-bold text-lg">{{ $performanceMetrics['requests_24h']['status_codes']['server_error_5xx'] }}</div>
                        <div class="text-slate-500">5xx Server Failures</div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- TAB 4: APPLICATION LOGS --}}
    @if ($activeTab === 'logs')
        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Structured Application Logs</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Streamed from framework log channels with real-time level filtering and search.</p>
                </div>

                {{-- Write Log Action --}}
                <div class="flex items-center gap-2">
                    <input type="text" wire:model.defer="testLogMessage" placeholder="Log message..." class="px-3 py-1.5 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-white">
                    <button wire:click="writeTestLog" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md transition">
                        Write Log
                    </button>
                </div>
            </div>

            {{-- Filter Bar --}}
            <div class="flex flex-wrap items-center justify-between gap-4 p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold text-slate-500">Filter Level:</span>
                    @foreach (['' => 'All', 'info' => 'Info', 'notice' => 'Notice', 'warning' => 'Warning', 'error' => 'Error'] as $val => $lbl)
                        <button wire:click="$set('logFilterLevel', '{{ $val }}')" class="px-3 py-1 rounded-lg text-xs font-semibold {{ $logFilterLevel === $val ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                            {{ $lbl }}
                        </button>
                    @endforeach
                </div>
                <input type="text" wire:model.live.debounce.300ms="logSearch" placeholder="Search log text..." class="px-3 py-1.5 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-white w-64">
            </div>

            {{-- Log Entries List --}}
            <div class="space-y-3 font-mono text-xs">
                @forelse ($applicationLogs as $log)
                    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row justify-between items-start gap-3">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                    {{ $log['level'] === 'error' || $log['level'] === 'critical' ? 'bg-rose-500/10 text-rose-400' :
                                       ($log['level'] === 'warning' ? 'bg-amber-500/10 text-amber-400' : 'bg-emerald-500/10 text-emerald-400') }}">
                                    {{ $log['level'] }}
                                </span>
                                <span class="text-slate-400 text-[11px]">{{ $log['timestamp'] }}</span>
                                <span class="text-slate-500 text-[10px]">[{{ $log['channel'] }}]</span>
                            </div>
                            <div class="text-slate-800 dark:text-slate-200 break-all">{{ $log['message'] }}</div>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-slate-500 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800">
                        No log entries matching the selected criteria.
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    {{-- TAB 5: AUDIT LOGS --}}
    @if ($activeTab === 'audit')
        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Enterprise Audit Trail</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Immutable ledger of administrative actions, policy mutations, and security events.</p>
                </div>

                {{-- Write Audit Action --}}
                <div class="flex items-center gap-2">
                    <input type="text" wire:model.defer="auditActionText" placeholder="Audit action description..." class="px-3 py-1.5 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-white w-64">
                    <button wire:click="recordTestAudit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md transition">
                        Record Audit
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-400">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-300 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3 px-4">Action</th>
                            <th class="py-3 px-4">Actor</th>
                            <th class="py-3 px-4">Subject</th>
                            <th class="py-3 px-4">IP Address</th>
                            <th class="py-3 px-4">Occurred At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-mono">
                        @forelse ($auditLogs as $audit)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                                <td class="py-3 px-4 font-bold text-slate-900 dark:text-white font-sans">{{ $audit['action'] }}</td>
                                <td class="py-3 px-4 text-indigo-500 font-sans">{{ $audit['causer_name'] }}</td>
                                <td class="py-3 px-4">{{ $audit['subject_type'] }} #{{ $audit['subject_id'] ?? '-' }}</td>
                                <td class="py-3 px-4">{{ $audit['ip_address'] }}</td>
                                <td class="py-3 px-4 text-slate-400">{{ $audit['created_at'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-500">No audit records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- TAB 6: ERROR TRACKING --}}
    @if ($activeTab === 'errors')
        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Proactive Error Tracking &amp; Exception Fingerprinting</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Deduplicated exception events grouped by fingerprint with stack traces and resolution status.</p>
                </div>

                {{-- Simulate Error Action --}}
                <div class="flex items-center gap-2">
                    <input type="text" wire:model.defer="simulatedErrorMessage" placeholder="Simulated exception text..." class="px-3 py-1.5 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-white w-64">
                    <button wire:click="simulateTrackedError" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-md transition">
                        Simulate Error
                    </button>
                </div>
            </div>

            <div class="space-y-4">
                @forelse ($errorMetrics['errors'] as $error)
                    <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div class="flex items-center gap-3">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase
                                    {{ $error['severity'] === 'critical' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' :
                                       ($error['severity'] === 'error' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20') }}">
                                    {{ $error['severity'] }}
                                </span>
                                <h3 class="text-base font-bold text-slate-900 dark:text-white font-mono">{{ $error['exception_class'] }}</h3>
                            </div>
                            <div class="flex items-center gap-3 text-xs">
                                <span class="px-2 py-0.5 rounded font-mono font-bold {{ $error['status'] === 'resolved' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' }}">
                                    {{ $error['status'] }}
                                </span>
                                <span class="text-slate-400">{{ $error['occurrences_count'] }} occurrences</span>
                                @if ($error['status'] !== 'resolved')
                                    <button wire:click="resolveTrackedError({{ $error['id'] }})" class="px-3 py-1 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white transition">
                                        Mark Resolved
                                    </button>
                                @endif
                            </div>
                        </div>

                        <p class="text-xs text-slate-700 dark:text-slate-300 font-semibold">{{ $error['message'] }}</p>

                        <div class="flex flex-wrap items-center gap-4 text-[11px] font-mono text-slate-400 pt-2 border-t border-slate-100 dark:border-slate-800">
                            <div>Location: <span class="text-slate-300">{{ $error['file'] }}:{{ $error['line'] }}</span></div>
                            <div>Fingerprint: <span class="text-indigo-400">{{ substr($error['fingerprint'], 0, 16) }}...</span></div>
                            <div>Last Seen: <span class="text-slate-300">{{ $error['last_seen_at'] }}</span></div>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-slate-500 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800">
                        Zero unresolved exceptions in ErrorTracker. System operating with zero defects.
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    {{-- TAB 7: QUEUE METRICS --}}
    @if ($activeTab === 'queues')
        <div class="space-y-6">
            <div>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">Queue &amp; Horizon Infrastructure Telemetry</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Queue depths across priority tiers, worker supervisor status, and failed job tallies.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 font-mono">
                @foreach ($queueMetrics['queue_summary']['queues'] as $qName => $qData)
                    <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-indigo-400">Queue [{{ $qName }}]</span>
                            <span class="px-2 py-0.5 rounded text-[10px] bg-slate-100 dark:bg-slate-800 text-slate-400 font-bold uppercase">Priority {{ $qData['priority'] }}</span>
                        </div>
                        <div class="text-3xl font-black text-slate-900 dark:text-white">{{ $qData['count'] }}</div>
                        <div class="text-xs text-slate-500 font-sans">
                            Allocated Workers: <strong class="text-slate-800 dark:text-slate-200">{{ $qData['workers_allocated'] }}</strong>
                        </div>
                        <div class="text-[11px] text-slate-400 font-sans">
                            Workloads: {{ implode(', ', $qData['jobs_handled']) }}
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Horizon Supervisor Summary --}}
            <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Laravel Horizon Redis Supervisor</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Master supervisor coordination, redis ping, and active worker pools.</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase {{ ($queueMetrics['horizon']['status'] ?? '') === 'running' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-400' }}">
                        Horizon: {{ $queueMetrics['horizon']['status'] ?? 'inactive' }}
                    </span>
                    <a href="/horizon" target="_blank" class="px-4 py-2 rounded-xl text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/40 hover:bg-indigo-100 transition">
                        Open Horizon Dashboard &rarr;
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>
