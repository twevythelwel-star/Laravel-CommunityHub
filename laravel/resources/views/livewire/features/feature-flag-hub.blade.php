<div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-8 animate-fade-in font-sans">
    {{-- Header Banner / Hero Capsule --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 p-6 sm:p-8 border border-slate-800 shadow-2xl text-white">
        <div class="absolute -right-16 -top-16 w-72 h-72 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute right-32 -bottom-20 w-80 h-80 bg-violet-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 mb-3">
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-400 animate-ping"></span>
                    Laravel Pennant Powered
                </div>
                <h1 class="text-3xl sm:text-4xl font-black text-white tracking-tight">Feature Flags &amp; Experimentation Hub</h1>
                <p class="mt-2 text-slate-300 text-sm sm:text-base max-w-2xl">
                    Progressive feature delivery, Canary gradual rollouts, Multi-variant A/B experimentation, Beta opt-ins, and Scoped Tenant/Role controls.
                </p>
            </div>

            {{-- Telemetry Capsule --}}
            <div class="p-4 rounded-2xl bg-slate-900/90 border border-slate-800 flex flex-wrap gap-4 text-xs font-mono">
                <div>
                    <div class="text-slate-500 uppercase text-[10px]">Pennant Store</div>
                    <div class="font-bold text-emerald-400 uppercase">Database</div>
                </div>
                <div class="border-l border-slate-800 pl-4">
                    <div class="text-slate-500 uppercase text-[10px]">Registered Flags</div>
                    <div class="font-bold text-slate-200">{{ $totalCount }} Definitions</div>
                </div>
                <div class="border-l border-slate-800 pl-4">
                    <div class="text-slate-500 uppercase text-[10px]">Experimentation</div>
                    <div class="font-bold text-indigo-400">A/B &amp; Canary</div>
                </div>
                <div class="border-l border-slate-800 pl-4">
                    <div class="text-slate-500 uppercase text-[10px]">Scopes</div>
                    <div class="font-bold text-cyan-400">Tenant / Role / User</div>
                </div>
            </div>
        </div>

        {{-- Navigation Tabs --}}
        <div class="mt-8 flex flex-wrap gap-2 border-t border-slate-800/80 pt-4">
            <button wire:click="selectTab('flags_catalog')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'flags_catalog' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Feature Flags Directory
            </button>
            <button wire:click="selectTab('rollouts_ab')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'rollouts_ab' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                A/B Experiments &amp; Gradual Rollouts
            </button>
            <button wire:click="selectTab('scopes_simulator')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'scopes_simulator' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Resolution Simulator (User / Role / Tenant)
            </button>
            <button wire:click="selectTab('database_store')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'database_store' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Pennant Database Store &amp; Cache
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

    {{-- TAB 1: FEATURE FLAGS DIRECTORY --}}
    @if ($activeTab === 'flags_catalog')
        <div class="space-y-6">
            {{-- Filter & Search Bar --}}
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold text-slate-500">Filter By Type:</span>
                    @foreach ([
                        'all' => 'All Flags',
                        'boolean_global' => 'feature.enabled',
                        'beta_opt_in' => 'beta.features',
                        'ab_testing' => 'A/B testing',
                        'gradual_rollout' => 'gradual rollout',
                        'tenant_specific' => 'tenant-specific',
                        'role_specific' => 'role-specific',
                    ] as $typeKey => $typeLabel)
                        <button wire:click="$set('filterType', '{{ $typeKey }}')" class="px-3 py-1 rounded-lg text-xs font-semibold {{ $filterType === $typeKey ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                            {{ $typeLabel }}
                        </button>
                    @endforeach
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search feature flags..." class="px-3 py-1.5 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-white w-64">
            </div>

            {{-- Flags Catalog Cards Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @forelse ($catalog as $key => $feature)
                    <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between space-y-4">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider
                                    {{ $feature['type'] === 'boolean_global' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' :
                                       ($feature['type'] === 'beta_opt_in' ? 'bg-purple-500/10 text-purple-400 border border-purple-500/20' :
                                       ($feature['type'] === 'ab_testing' ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20' :
                                       ($feature['type'] === 'gradual_rollout' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' :
                                       ($feature['type'] === 'tenant_specific' ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20')))) }}">
                                    {{ $feature['use_case'] }}
                                </span>

                                <span class="text-xs font-mono {{ $feature['current_active'] ? 'text-emerald-500 font-bold' : 'text-slate-400' }}">
                                    {{ $feature['current_active'] ? 'Active (True)' : 'Inactive (False)' }}
                                </span>
                            </div>

                            <div>
                                <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $feature['name'] }}</h3>
                                <div class="text-[11px] font-mono text-indigo-500">{{ $key }}</div>
                            </div>

                            <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">{{ $feature['description'] }}</p>

                            {{-- Metadata badges --}}
                            <div class="flex flex-wrap gap-2 text-[10px] font-mono pt-2">
                                <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                                    Target: <strong class="text-slate-800 dark:text-slate-200 uppercase">{{ $feature['scope_target'] }}</strong>
                                </span>

                                @if (isset($feature['variants']))
                                    <span class="px-2 py-0.5 rounded bg-cyan-500/10 text-cyan-400">
                                        Variants: {{ implode(', ', $feature['variants']) }}
                                    </span>
                                @endif

                                @if (isset($feature['rollout_percentage']))
                                    <span class="px-2 py-0.5 rounded bg-amber-500/10 text-amber-400">
                                        Canary: {{ $feature['rollout_percentage'] }}% of traffic
                                    </span>
                                @endif

                                @if (isset($feature['eligible_roles']))
                                    <span class="px-2 py-0.5 rounded bg-rose-500/10 text-rose-400">
                                        Roles: {{ implode(', ', $feature['eligible_roles']) }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Action Toggle --}}
                        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-between items-center">
                            <span class="text-xs text-slate-500 font-mono">
                                Resolved Value: <strong class="text-slate-800 dark:text-slate-200">{{ is_bool($feature['current_value']) ? ($feature['current_value'] ? 'true' : 'false') : (string) $feature['current_value'] }}</strong>
                            </span>
                            <button wire:click="toggleFeature('{{ $key }}')" class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $feature['current_active'] ? 'bg-rose-50 dark:bg-rose-950/40 text-rose-500 hover:bg-rose-100' : 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-500 hover:bg-emerald-100' }}">
                                {{ $feature['current_active'] ? 'Deactivate' : 'Activate' }}
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="col-span-2 p-8 text-center text-slate-500 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800">
                        No feature flags matching criteria.
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    {{-- TAB 2: A/B TESTING & GRADUAL ROLLOUTS --}}
    @if ($activeTab === 'rollouts_ab')
        <div class="space-y-6">
            <div>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">A/B Testing &amp; Progressive Canary Rollouts</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Multi-variant experiment buckets and statistical percentage rollouts powered by Laravel Pennant.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- A/B Experiment Card --}}
                <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                            A/B Testing Experiment
                        </span>
                        <span class="text-xs font-mono text-slate-400">3 Variants</span>
                    </div>

                    <h3 class="text-base font-bold text-slate-900 dark:text-white">checkout_flow_experiment</h3>
                    <p class="text-xs text-slate-600 dark:text-slate-400">
                        Evaluates conversion speed and resident engagement across 3 checkout experiences:
                    </p>

                    <div class="space-y-3 font-mono text-xs">
                        <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 flex justify-between items-center">
                            <div>
                                <span class="font-bold text-indigo-400">Variant A: 'classic'</span>
                                <div class="text-[11px] text-slate-500 font-sans">Full multi-step summary with ledger account split</div>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] bg-slate-200 dark:bg-slate-700 text-slate-300 font-bold">33.3%</span>
                        </div>

                        <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 flex justify-between items-center">
                            <div>
                                <span class="font-bold text-cyan-400">Variant B: 'streamlined'</span>
                                <div class="text-[11px] text-slate-500 font-sans">Accordion inline checkout with saved cards</div>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] bg-slate-200 dark:bg-slate-700 text-slate-300 font-bold">33.3%</span>
                        </div>

                        <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 flex justify-between items-center">
                            <div>
                                <span class="font-bold text-emerald-400">Variant C: 'express_one_click'</span>
                                <div class="text-[11px] text-slate-500 font-sans">Instant biometric Apple/Google Pay bypass</div>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] bg-slate-200 dark:bg-slate-700 text-slate-300 font-bold">33.4%</span>
                        </div>
                    </div>
                </div>

                {{-- Canary Gradual Rollouts Card --}}
                <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-500/10 text-amber-400 border border-amber-500/20">
                            Gradual Rollout Canaries
                        </span>
                        <span class="text-xs font-mono text-slate-400">Progressive Delivery</span>
                    </div>

                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Percentage-Based Traffic Exposure</h3>
                    <p class="text-xs text-slate-600 dark:text-slate-400">
                        Mitigates deployment risk by delivering features progressively to a deterministic subset of users:
                    </p>

                    <div class="space-y-4 pt-2">
                        <div>
                            <div class="flex justify-between text-xs font-mono mb-1">
                                <span class="font-bold text-slate-800 dark:text-slate-200">new_resident_portal</span>
                                <span class="text-amber-400 font-bold">40% Exposure</span>
                            </div>
                            <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-3 overflow-hidden">
                                <div class="bg-amber-500 h-3 rounded-full" style="width: 40%"></div>
                            </div>
                            <div class="text-[10px] text-slate-500 mt-1">Users with ID modulo 100 &lt; 40 are routed to the new portal UI.</div>
                        </div>

                        <div>
                            <div class="flex justify-between text-xs font-mono mb-1">
                                <span class="font-bold text-slate-800 dark:text-slate-200">biometric_visitor_pass</span>
                                <span class="text-indigo-400 font-bold">25% Exposure</span>
                            </div>
                            <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-3 overflow-hidden">
                                <div class="bg-indigo-500 h-3 rounded-full" style="width: 25%"></div>
                            </div>
                            <div class="text-[10px] text-slate-500 mt-1">25% of checkpoint scanners trial facial pass validation.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- TAB 3: RESOLUTION SIMULATOR --}}
    @if ($activeTab === 'scopes_simulator')
        <div class="space-y-6">
            <div>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">Feature Flag Resolution Simulator</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Simulate how Laravel Pennant evaluates any feature flag for different Users, Roles, or Multi-Tenant Estates.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Simulator Form --}}
                <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Select Feature</label>
                        <select wire:model="simFeature" class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-white">
                            @foreach ($allFeaturesList as $featName)
                                <option value="{{ $featName }}">{{ $featName }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Scope Type</label>
                        <select wire:model="simScopeType" class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-white">
                            <option value="user">User (ID or Email)</option>
                            <option value="role">Role (Admin, Security, Homeowner)</option>
                            <option value="tenant">Tenant / Estate ID</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Scope Value / Identifier</label>
                        <input type="text" wire:model="simScopeIdentifier" placeholder="e.g. 1, admin@communityhub.test, palm-grove, Admin" class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-white font-mono">
                    </div>

                    <button wire:click="runSimulation" class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md transition">
                        Evaluate Resolution
                    </button>
                </div>

                {{-- Simulation Output --}}
                <div class="lg:col-span-2 p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between">
                    @if ($simResult)
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold uppercase text-slate-500 tracking-wider">Evaluation Output</span>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase {{ $simResult['is_active'] ? 'bg-emerald-500/10 text-emerald-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-400' }}">
                                    {{ $simResult['is_active'] ? 'Active (True)' : 'Inactive (False)' }}
                                </span>
                            </div>

                            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 font-mono text-xs space-y-2">
                                <div><span class="text-slate-500">Feature Name:</span> <strong class="text-indigo-400">{{ $simResult['feature'] }}</strong></div>
                                <div><span class="text-slate-500">Use Case:</span> <span class="text-slate-300">{{ $simResult['use_case'] }}</span></div>
                                <div><span class="text-slate-500">Scope Evaluated:</span> <span class="text-cyan-400">{{ $simResult['scope_type'] }} [{{ $simResult['scope_identifier'] }}]</span></div>
                                <div>
                                    <span class="text-slate-500">Resolved Value:</span>
                                    <strong class="text-emerald-400 text-sm">
                                        {{ is_bool($simResult['resolved_value']) ? ($simResult['resolved_value'] ? 'true' : 'false') : (string) $simResult['resolved_value'] }}
                                    </strong>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="h-64 flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 text-center p-6 space-y-2">
                            <div class="w-10 h-10 rounded-full bg-indigo-500/10 text-indigo-400 flex items-center justify-center font-bold text-lg">&sim;</div>
                            <div class="text-sm font-bold text-slate-700 dark:text-slate-300">No Simulation Run Yet</div>
                            <p class="text-xs text-slate-500 max-w-sm">Select a feature and scope parameters to test runtime resolution.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- TAB 4: DATABASE STORE & CACHE --}}
    @if ($activeTab === 'database_store')
        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Pennant Database Storage Engine</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Underlying records stored in the `features` database table with cache purge capabilities.</p>
                </div>

                <button wire:click="purgeStore" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-md transition">
                    Flush / Purge Store Cache
                </button>
            </div>

            <div class="overflow-x-auto rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-400">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-300 uppercase text-[10px] tracking-wider border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3 px-4">Feature Name</th>
                            <th class="py-3 px-4">Scope Class</th>
                            <th class="py-3 px-4">Scope ID</th>
                            <th class="py-3 px-4">Stored Value</th>
                            <th class="py-3 px-4">Updated At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-mono">
                        @forelse ($databaseEntries as $row)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                                <td class="py-3 px-4 font-bold text-indigo-400">{{ $row->name }}</td>
                                <td class="py-3 px-4">{{ $row->scope ?? 'Global' }}</td>
                                <td class="py-3 px-4">{{ $row->scope_id ?? '-' }}</td>
                                <td class="py-3 px-4 text-emerald-400">{{ $row->value }}</td>
                                <td class="py-3 px-4 text-slate-400">{{ $row->updated_at ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-slate-500">
                                    No database override records found. Features resolving dynamically via in-memory/code definitions.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
