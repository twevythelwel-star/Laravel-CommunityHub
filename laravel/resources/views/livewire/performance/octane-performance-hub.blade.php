<div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-8">
    {{-- Top Real-Time Octane Performance Banner --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 border border-indigo-500/30 shadow-2xl p-6 sm:p-8">
        <div class="absolute -right-20 -top-20 w-72 h-72 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -left-20 -bottom-20 w-72 h-72 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-semibold uppercase tracking-wider mb-3">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
                    Laravel Octane Accelerated
                </div>
                <h1 class="text-3xl sm:text-4xl font-black text-white tracking-tight">High-Performance Runtime Hub</h1>
                <p class="mt-2 text-slate-300 text-sm sm:text-base max-w-2xl">
                    Long-lived PHP application server infrastructure powering sub-millisecond response times across FrankenPHP, Swoole, and RoadRunner.
                </p>
            </div>

            {{-- Telemetry Capsule --}}
            <div class="p-4 rounded-2xl bg-slate-900/90 border border-slate-800 flex flex-wrap gap-4 text-xs font-mono">
                <div>
                    <div class="text-slate-500 uppercase text-[10px]">Active Server</div>
                    <div class="font-bold text-emerald-400 uppercase">{{ $selectedServer }}</div>
                </div>
                <div class="border-l border-slate-800 pl-4">
                    <div class="text-slate-500 uppercase text-[10px]">Memory Used</div>
                    <div class="font-bold text-slate-200">{{ $metrics['memory_used_mb'] }} MB</div>
                </div>
                <div class="border-l border-slate-800 pl-4">
                    <div class="text-slate-500 uppercase text-[10px]">Peak Memory</div>
                    <div class="font-bold text-indigo-400">{{ $metrics['memory_peak_mb'] }} MB</div>
                </div>
                <div class="border-l border-slate-800 pl-4">
                    <div class="text-slate-500 uppercase text-[10px]">Worker Mode</div>
                    <div class="font-bold text-cyan-400">Pre-Warmed</div>
                </div>
            </div>
        </div>

        {{-- Navigation Tabs --}}
        <div class="mt-8 flex flex-wrap gap-2 border-t border-slate-800/80 pt-4">
            <button wire:click="selectTab('servers')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'servers' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Application Servers (FrankenPHP / Swoole / RoadRunner)
            </button>
            <button wire:click="selectTab('concurrency')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'concurrency' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Concurrent Task Benchmarking (Octane::concurrently)
            </button>
            <button wire:click="selectTab('telemetry')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'telemetry' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Memory and State Sanitation
            </button>
            <button wire:click="selectTab('suitability')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'suitability' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Workload Suitability Guide
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

    {{-- TAB 1: Application Servers --}}
    @if ($activeTab === 'servers')
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach ($servers as $key => $server)
                <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border {{ $selectedServer === $key ? 'border-indigo-500 ring-2 ring-indigo-500/20' : 'border-slate-200 dark:border-slate-800' }} shadow-sm flex flex-col justify-between space-y-6">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $selectedServer === $key ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-400' }}">
                                {{ $selectedServer === $key ? 'Active Runtime' : 'Supported' }}
                            </span>
                            <span class="text-xs font-mono text-indigo-400 font-semibold">{{ $server['language'] }}</span>
                        </div>
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $server['name'] }}</h2>
                        <div class="text-xs font-semibold text-emerald-500 mt-1">{{ $server['performance_tier'] }}</div>

                        {{-- Features List --}}
                        <div class="mt-4 space-y-2">
                            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Key Architectural Features:</div>
                            <ul class="space-y-1.5 text-xs text-slate-600 dark:text-slate-300">
                                @foreach ($server['key_features'] as $feature)
                                    <li class="flex items-start gap-2">
                                        <span class="text-indigo-400 font-bold">&check;</span>
                                        <span>{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        {{-- Optimal Use Cases --}}
                        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 text-xs">
                            <span class="text-slate-500 font-semibold">Optimal For:</span>
                            <p class="text-slate-700 dark:text-slate-300 mt-1 leading-relaxed">{{ $server['optimal_use_cases'] }}</p>
                        </div>
                    </div>

                    <div>
                        @if ($selectedServer === $key)
                            <div class="w-full py-2.5 rounded-xl bg-emerald-500/10 text-emerald-400 text-center text-xs font-bold uppercase tracking-wider">
                                Current Primary Server
                            </div>
                        @else
                            <button wire:click="selectServer('{{ $key }}')" class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-center text-xs font-bold uppercase tracking-wider transition-all shadow-sm">
                                Switch to {{ $server['name'] }}
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- TAB 2: Concurrency Benchmarking --}}
    @if ($activeTab === 'concurrency')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Control Panel --}}
            <div class="lg:col-span-1 p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-6">
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Parallel Concurrency Engine</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Execute independent asynchronous tasks simultaneously via <code class="text-indigo-400">Octane::concurrently()</code>.</p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                            Simulated Concurrent Tasks: <span class="text-indigo-400 font-bold">{{ $concurrencyTaskCount }}</span>
                        </label>
                        <input type="range" wire:model.live="concurrencyTaskCount" min="2" max="8" class="w-full accent-indigo-600 cursor-pointer">
                        <div class="flex justify-between text-[10px] text-slate-400 font-mono mt-1">
                            <span>2 Tasks</span>
                            <span>4 Tasks</span>
                            <span>6 Tasks</span>
                            <span>8 Tasks</span>
                        </div>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-xs space-y-2">
                        <div class="font-bold text-slate-900 dark:text-white">How Octane Concurrency Operates:</div>
                        <p class="text-slate-500 dark:text-slate-400 leading-relaxed text-[11px]">
                            Under FrankenPHP or Swoole, tasks execute across isolated worker threads simultaneously. If tasks take 20ms each, running 4 tasks takes ~22ms total instead of 80ms sequentially.
                        </p>
                    </div>

                    <button wire:click="runConcurrencyBenchmark" class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs uppercase tracking-wider shadow-lg transition-all flex items-center justify-center gap-2">
                        <span>Run Concurrent Benchmark</span>
                    </button>
                </div>
            </div>

            {{-- Execution Results --}}
            <div class="lg:col-span-2 p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Thread Execution Telemetry</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Real-time benchmark duration and aggregated thread outputs.</p>
                    </div>
                    @if ($benchmarkResult)
                        <div class="px-3 py-1 rounded-full text-xs font-mono font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                            {{ $benchmarkResult['elapsed_ms'] }} ms Total
                        </div>
                    @endif
                </div>

                @if ($benchmarkResult)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($benchmarkResult['results'] as $key => $thread)
                            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 font-mono text-xs space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-indigo-400">{{ $thread['thread'] }}</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] bg-emerald-500/10 text-emerald-400 font-bold uppercase">{{ $thread['status'] }}</span>
                                </div>
                                <div class="text-slate-500 text-[11px]">
                                    Checksum: <span class="text-slate-300">{{ $thread['digest'] }}</span>
                                </div>
                                <div class="text-slate-500 text-[10px]">
                                    Epoch: {{ $thread['timestamp'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="h-64 flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 text-center p-6 space-y-2">
                        <div class="w-10 h-10 rounded-full bg-indigo-500/10 text-indigo-400 flex items-center justify-center font-bold text-lg">&sim;</div>
                        <div class="text-sm font-bold text-slate-700 dark:text-slate-300">No Benchmark Executed Yet</div>
                        <p class="text-xs text-slate-500 max-w-sm">Click "Run Concurrent Benchmark" to simulate parallel execution across multi-process workers.</p>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- TAB 3: Memory & State Sanitation --}}
    @if ($activeTab === 'telemetry')
        <div class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="text-slate-500 text-xs font-semibold uppercase">Memory Allocated</div>
                    <div class="mt-2 text-2xl font-black text-slate-900 dark:text-white">{{ $metrics['memory_used_mb'] }} MB</div>
                    <div class="mt-1 text-xs text-emerald-500">Live worker memory footprint</div>
                </div>

                <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="text-slate-500 text-xs font-semibold uppercase">Peak Memory</div>
                    <div class="mt-2 text-2xl font-black text-slate-900 dark:text-white">{{ $metrics['memory_peak_mb'] }} MB</div>
                    <div class="mt-1 text-xs text-indigo-400">Under GC threshold ({{ $metrics['garbage_threshold_mb'] }}MB)</div>
                </div>

                <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="text-slate-500 text-xs font-semibold uppercase">Warmed Services</div>
                    <div class="mt-2 text-2xl font-black text-slate-900 dark:text-white">{{ $metrics['warmed_services_count'] }}</div>
                    <div class="mt-1 text-xs text-cyan-400">Pre-boot singletons in RAM</div>
                </div>

                <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="text-slate-500 text-xs font-semibold uppercase">Flushed Listeners</div>
                    <div class="mt-2 text-2xl font-black text-slate-900 dark:text-white">{{ $metrics['flushed_listeners_count'] }}</div>
                    <div class="mt-1 text-xs text-emerald-400">Zero state-leak protection</div>
                </div>
            </div>

            {{-- Memory Tables & In-Memory Caching --}}
            <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Configured In-Memory Cache Tables</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Persistent memory tables stored directly in RAM for sub-millisecond barcode & RFID token access.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 font-mono text-xs">
                    @foreach ($metrics['tables_configured'] as $table)
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 flex justify-between items-center">
                            <span class="font-bold text-indigo-400">{{ $table }}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] bg-indigo-500/10 text-indigo-300 font-bold uppercase">RAM Cache</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- TAB 4: Workload Suitability Guide --}}
    @if ($activeTab === 'suitability')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Beneficial Workloads --}}
            <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-emerald-500/30 shadow-sm space-y-4">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-emerald-400"></span>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">When Long-Lived Workers Excel</h3>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Use Octane when request throughput and low latency deliver direct business value.</p>

                <div class="space-y-4 pt-2">
                    @foreach ($suitability['benefits_long_lived_workers'] as $item)
                        <div class="p-4 rounded-2xl bg-emerald-500/5 border border-emerald-500/20 space-y-1.5">
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-xs text-slate-900 dark:text-white">{{ $item['workload'] }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-400">{{ $item['verdict'] }}</span>
                            </div>
                            <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">{{ $item['reasoning'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Standard Lifecycle Cases --}}
            <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-amber-500/30 shadow-sm space-y-4">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-amber-400"></span>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">When Standard PHP-FPM is Preferred</h3>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Use standard request lifecycle when isolation or code architecture precludes long-lived state.</p>

                <div class="space-y-4 pt-2">
                    @foreach ($suitability['requires_standard_lifecycle'] as $item)
                        <div class="p-4 rounded-2xl bg-amber-500/5 border border-amber-500/20 space-y-1.5">
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-xs text-slate-900 dark:text-white">{{ $item['workload'] }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-400">{{ $item['verdict'] }}</span>
                            </div>
                            <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">{{ $item['reasoning'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
