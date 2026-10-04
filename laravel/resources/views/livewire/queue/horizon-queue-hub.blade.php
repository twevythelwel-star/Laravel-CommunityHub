<div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-8">
    {{-- Top Real-Time Queue & Horizon Banner --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 border border-indigo-500/30 shadow-2xl p-6 sm:p-8">
        <div class="absolute -right-20 -top-20 w-72 h-72 bg-purple-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -left-20 -bottom-20 w-72 h-72 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-semibold uppercase tracking-wider mb-3">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
                    Laravel Queues + Horizon Active
                </div>
                <h1 class="text-3xl sm:text-4xl font-black text-white tracking-tight">Queue & Background Processing</h1>
                <p class="mt-2 text-slate-300 text-sm sm:text-base max-w-2xl">
                    Robust multi-pool background queue architecture powered by Redis, supervised by Laravel Horizon across 11 asynchronous domain workloads.
                </p>
            </div>

            {{-- Connection & Status Metrics Capsule --}}
            <div class="p-4 rounded-2xl bg-slate-900/90 border border-slate-800 flex flex-wrap gap-4 text-xs font-mono">
                <div>
                    <div class="text-slate-500 uppercase text-[10px]">Queue Driver</div>
                    <div class="font-bold text-indigo-400 uppercase">{{ $metrics['driver'] }}</div>
                </div>
                <div class="border-l border-slate-800 pl-4">
                    <div class="text-slate-500 uppercase text-[10px]">Redis Transport</div>
                    <div class="font-bold {{ $horizon['redis_connected'] ? 'text-emerald-400' : 'text-amber-400' }}">
                        {{ $horizon['redis_connected'] ? 'Connected' : 'Offline' }}
                    </div>
                </div>
                <div class="border-l border-slate-800 pl-4">
                    <div class="text-slate-500 uppercase text-[10px]">Horizon Supervisor</div>
                    <div class="font-bold text-cyan-400 uppercase">{{ $horizon['status'] }}</div>
                </div>
                <div class="border-l border-slate-800 pl-4">
                    <div class="text-slate-500 uppercase text-[10px]">Horizon UI</div>
                    <a href="/{{ $horizon['ui_path'] }}" target="_blank" class="font-bold text-indigo-400 hover:text-indigo-300 underline inline-flex items-center gap-1">
                        /{{ $horizon['ui_path'] }} &nearr;
                    </a>
                </div>
            </div>
        </div>

        {{-- Navigation Tabs --}}
        <div class="mt-8 flex flex-wrap gap-2 border-t border-slate-800/80 pt-4">
            <button wire:click="selectTab('telemetry')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'telemetry' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Queue Pools & Telemetry
            </button>
            <button wire:click="selectTab('dispatcher')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'dispatcher' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Interactive Job Dispatcher (11 Workloads)
            </button>
            <button wire:click="selectTab('catalog')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'catalog' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Workload Catalog & Schedulers
            </button>
            <button wire:click="selectTab('architecture')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'architecture' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Architecture Pipeline
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

    {{-- TAB 1: Queue Pools & Telemetry --}}
    @if ($activeTab === 'telemetry')
        <div class="space-y-6">
            {{-- Metrics Grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                {{-- High Priority Pool --}}
                <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm relative overflow-hidden">
                    <div class="flex items-center justify-between text-slate-500 text-xs font-semibold uppercase">
                        <span>High Priority Pool</span>
                        <span class="px-2 py-0.5 rounded text-[10px] bg-rose-500/10 text-rose-500 font-bold">CRITICAL</span>
                    </div>
                    <div class="mt-3 text-3xl font-black text-slate-900 dark:text-white">{{ $metrics['queues']['high']['count'] }}</div>
                    <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        Queues: <span class="font-semibold text-rose-400">SMS, Webhooks, Push</span>
                    </div>
                </div>

                {{-- Default Pool --}}
                <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm relative overflow-hidden">
                    <div class="flex items-center justify-between text-slate-500 text-xs font-semibold uppercase">
                        <span>Default Pool</span>
                        <span class="px-2 py-0.5 rounded text-[10px] bg-indigo-500/10 text-indigo-500 font-bold">NORMAL</span>
                    </div>
                    <div class="mt-3 text-3xl font-black text-slate-900 dark:text-white">{{ $metrics['queues']['default']['count'] }}</div>
                    <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        Queues: <span class="font-semibold text-indigo-400">Emails, PDF, Images</span>
                    </div>
                </div>

                {{-- Low Pool --}}
                <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm relative overflow-hidden">
                    <div class="flex items-center justify-between text-slate-500 text-xs font-semibold uppercase">
                        <span>Low / Batch Pool</span>
                        <span class="px-2 py-0.5 rounded text-[10px] bg-cyan-500/10 text-cyan-500 font-bold">BATCH</span>
                    </div>
                    <div class="mt-3 text-3xl font-black text-slate-900 dark:text-white">{{ $metrics['queues']['low']['count'] }}</div>
                    <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        Queues: <span class="font-semibold text-cyan-400">Reports, Exports, AI, Sync</span>
                    </div>
                </div>

                {{-- Failed Jobs --}}
                <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm relative overflow-hidden">
                    <div class="flex items-center justify-between text-slate-500 text-xs font-semibold uppercase">
                        <span>Failed Jobs</span>
                        <span class="w-2 h-2 rounded-full {{ $metrics['failed_jobs'] > 0 ? 'bg-rose-500 animate-ping' : 'bg-emerald-400' }}"></span>
                    </div>
                    <div class="mt-3 text-3xl font-black text-slate-900 dark:text-white">{{ $metrics['failed_jobs'] }}</div>
                    <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        Total Pending: <span class="font-bold text-slate-900 dark:text-white">{{ $metrics['total_pending'] }}</span>
                    </div>
                </div>
            </div>

            {{-- Recent Background Job History Table --}}
            <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Active Session Dispatch Audit Log</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Audit trail of background jobs dispatched during this administrative session.</p>
                    </div>
                    <button wire:click="selectTab('dispatcher')" class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-sm transition-all">
                        + Dispatch New Job
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-500 uppercase bg-slate-50 dark:bg-slate-800/50 text-[10px]">
                            <tr>
                                <th class="p-3">Job ID</th>
                                <th class="p-3">Workload Name</th>
                                <th class="p-3">Pool</th>
                                <th class="p-3">Priority</th>
                                <th class="p-3">Dispatched By</th>
                                <th class="p-3">Time</th>
                                <th class="p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-mono">
                            @foreach ($jobHistory as $item)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                    <td class="p-3 font-bold text-indigo-400">{{ $item['id'] }}</td>
                                    <td class="p-3 font-sans font-medium text-slate-900 dark:text-white">{{ $item['name'] }}</td>
                                    <td class="p-3 uppercase">
                                        <span class="px-2 py-0.5 rounded font-bold text-[10px] {{ $item['queue'] === 'high' ? 'bg-rose-500/10 text-rose-400' : ($item['queue'] === 'default' ? 'bg-indigo-500/10 text-indigo-400' : 'bg-cyan-500/10 text-cyan-400') }}">
                                            {{ $item['queue'] }}
                                        </span>
                                    </td>
                                    <td class="p-3 uppercase text-[10px] text-slate-400">{{ $item['priority'] }}</td>
                                    <td class="p-3 text-slate-600 dark:text-slate-300 font-sans">{{ $item['dispatched_by'] }}</td>
                                    <td class="p-3 text-slate-400">{{ $item['dispatched_at'] }}</td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400">
                                            {{ $item['status'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- TAB 2: Interactive Job Dispatcher --}}
    @if ($activeTab === 'dispatcher')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Dispatcher Controls --}}
            <div class="lg:col-span-1 p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-5">
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Dispatch Background Workload</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Select any of the 11 supported enterprise background workloads.</p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Workload Domain</label>
                        <select wire:model.live="selectedJobType" class="w-full rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-semibold p-2.5">
                            @foreach ($catalog as $key => $job)
                                <option value="{{ $key }}">{{ $job['name'] }} [{{ $job['queue'] }}]</option>
                            @endforeach
                        </select>
                    </div>

                    @php
                        $activeSpec = $catalog[$selectedJobType] ?? [];
                    @endphp
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-2 text-xs">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Target Queue:</span>
                            <span class="font-bold text-indigo-400 uppercase">{{ $activeSpec['queue'] ?? 'default' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Priority Level:</span>
                            <span class="font-bold uppercase text-slate-300">{{ $activeSpec['priority'] ?? 'normal' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Max Retries:</span>
                            <span class="font-bold text-slate-300">{{ $activeSpec['retries'] ?? 3 }} attempts</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Timeout:</span>
                            <span class="font-bold text-slate-300">{{ $activeSpec['timeout_seconds'] ?? 60 }}s</span>
                        </div>
                        <div class="pt-2 border-t border-slate-200 dark:border-slate-700 text-slate-400 text-[11px]">
                            {{ $activeSpec['description'] ?? '' }}
                        </div>
                    </div>

                    <button wire:click="dispatchSelectedJob" class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase tracking-wider shadow-lg transition-all flex items-center justify-center gap-2">
                        <span>Dispatch to {{ strtoupper($activeSpec['queue'] ?? 'default') }} Queue</span>
                    </button>
                </div>
            </div>

            {{-- JSON Payload Editor --}}
            <div class="lg:col-span-2 p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Workload Payload Configuration</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Structured parameters passed to the queued job class.</p>
                    </div>
                    <button wire:click="updateDefaultPayload('{{ $selectedJobType }}')" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 underline">
                        Reset Defaults
                    </button>
                </div>

                <div class="relative">
                    <textarea wire:model="customPayloadJson" rows="12" class="w-full font-mono text-xs rounded-2xl border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-indigo-300 p-4 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>

                <div class="text-[11px] text-slate-400 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span>All payloads are serialized via Laravel's <code class="text-slate-300">SerializesModels</code> trait and dispatched into Redis.</span>
                </div>
            </div>
        </div>
    @endif

    {{-- TAB 3: Workload Catalog --}}
    @if ($activeTab === 'catalog')
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($catalog as $key => $item)
                <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between space-y-4">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $item['queue'] === 'high' ? 'bg-rose-500/10 text-rose-400' : ($item['queue'] === 'default' ? 'bg-indigo-500/10 text-indigo-400' : 'bg-cyan-500/10 text-cyan-400') }}">
                                {{ $item['queue'] }} queue
                            </span>
                            <span class="text-slate-400 text-xs font-mono">Tries: {{ $item['retries'] }}</span>
                        </div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $item['name'] }}</h3>
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            {{ $item['description'] }}
                        </p>
                    </div>

                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs font-mono">
                        <span class="text-slate-400 text-[10px] truncate max-w-[180px]">{{ class_basename($item['class']) }}</span>
                        <button wire:click="$set('selectedJobType', '{{ $key }}'); selectTab('dispatcher')" class="text-indigo-400 hover:text-indigo-300 font-bold font-sans">
                            Configure &rarr;
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- TAB 4: Architecture Pipeline --}}
    @if ($activeTab === 'architecture')
        <div class="p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-8">
            <div>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">Enterprise Queue Architecture Pipeline</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Decoupled, high-throughput asynchronous execution lifecycle.</p>
            </div>

            {{-- Flow Diagram --}}
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 text-center">
                {{-- Step 1 --}}
                <div class="p-6 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 space-y-2">
                    <div class="w-8 h-8 rounded-full bg-indigo-600 text-white font-black flex items-center justify-center mx-auto text-xs">1</div>
                    <div class="font-bold text-slate-900 dark:text-white text-sm">Application</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">Web / REST API / Livewire</div>
                    <div class="text-[10px] font-mono text-indigo-400">Job::dispatch()</div>
                </div>

                {{-- Arrow 1 --}}
                <div class="hidden md:flex items-center justify-center text-slate-400 text-xl font-black">&rarr;</div>

                {{-- Step 2 --}}
                <div class="p-6 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 space-y-2">
                    <div class="w-8 h-8 rounded-full bg-indigo-600 text-white font-black flex items-center justify-center mx-auto text-xs">2</div>
                    <div class="font-bold text-slate-900 dark:text-white text-sm">Laravel Queue</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">Priority Pools</div>
                    <div class="text-[10px] font-mono text-indigo-400">high, default, low</div>
                </div>

                {{-- Arrow 2 --}}
                <div class="hidden md:flex items-center justify-center text-slate-400 text-xl font-black">&rarr;</div>

                {{-- Step 3 --}}
                <div class="p-6 rounded-2xl bg-purple-50 dark:bg-purple-950/40 border border-purple-200 dark:border-purple-800 space-y-2">
                    <div class="w-8 h-8 rounded-full bg-purple-600 text-white font-black flex items-center justify-center mx-auto text-xs">3</div>
                    <div class="font-bold text-slate-900 dark:text-white text-sm">Redis Store</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">In-Memory Persistence</div>
                    <div class="text-[10px] font-mono text-purple-400">Cluster / Standalone</div>
                </div>
            </div>

            {{-- Horizon & Workers Block --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4">
                <div class="p-6 rounded-2xl bg-cyan-50 dark:bg-cyan-950/30 border border-cyan-200 dark:border-cyan-800 space-y-3">
                    <div class="flex items-center gap-2 font-bold text-slate-900 dark:text-white text-sm">
                        <span class="w-2.5 h-2.5 rounded-full bg-cyan-400"></span>
                        <span>4. Laravel Horizon Supervisor</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        Manages master processes, auto-balances queue worker allocation according to workload wait time, tracks throughput, and silences noise.
                    </p>
                    <div class="font-mono text-xs text-cyan-400">php artisan horizon</div>
                </div>

                <div class="p-6 rounded-2xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 space-y-3">
                    <div class="flex items-center gap-2 font-bold text-slate-900 dark:text-white text-sm">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                        <span>5. Scaled Worker Pool</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        Processes payloads asynchronously in parallel threads, guaranteeing sub-second response times for interactive user requests.
                    </p>
                    <div class="font-mono text-xs text-emerald-400">queue:work --queue=high,default,low</div>
                </div>
            </div>
        </div>
    @endif
</div>
