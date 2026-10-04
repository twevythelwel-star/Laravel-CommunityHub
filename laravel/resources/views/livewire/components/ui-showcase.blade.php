<div class="space-y-10" x-data="{ tab: 'components' }">
    <!-- Header with Sub-Navigation -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-5">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                <span>UI & Frontend Architecture Kit</span>
                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-cyan-100 text-cyan-800 dark:bg-cyan-950 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800">
                    Livewire &middot; Alpine &middot; Flux &middot; Tailwind &middot; Inertia
                </span>
            </h2>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                Interactive components demonstration and multi-stack frontend blueprint.
            </p>
        </div>

        <!-- Tab Switcher -->
        <div class="flex items-center p-1 rounded-xl bg-slate-100 dark:bg-slate-800/80 text-xs font-medium border border-slate-200 dark:border-slate-700">
            <button type="button"
                    @click="tab = 'components'"
                    :class="tab === 'components' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                    class="px-3.5 py-1.5 rounded-lg transition-all">
                Component Showcase
            </button>
            <button type="button"
                    @click="tab = 'matrix'"
                    :class="tab === 'matrix' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                    class="px-3.5 py-1.5 rounded-lg transition-all">
                Frontend Stacks Matrix
            </button>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 1: LIVE COMPONENT SHOWCASE -->
    <!-- ========================================================================= -->
    <div x-show="tab === 'components'" class="space-y-10">

        <!-- 1. Reactive Forms Section -->
        <section class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                        1. Reactive Forms & Real-Time Validation
                    </h3>
                    <p class="text-xs text-slate-500">Livewire handles form submission, instant inline error checking, dirty states, and resets without page reloads.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Interactive Form -->
                <div class="lg:col-span-2 bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800">
                    <form wire:submit.prevent="submitDemoForm" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Full Name -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Full Name <span class="text-rose-500">*</span>
                                </label>
                                <input type="text"
                                       wire:model.live.debounce.300ms="formName"
                                       placeholder="e.g. Eleanor Vance"
                                       class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                @error('formName') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <!-- Email Address -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                    Email Address <span class="text-rose-500">*</span>
                                </label>
                                <input type="email"
                                       wire:model.live.debounce.300ms="formEmail"
                                       placeholder="eleanor@cypressbay.io"
                                       class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                @error('formEmail') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Category Select -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Inquiry Category</label>
                                <select wire:model.live="formCategory" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="General Inquiry">General Inquiry</option>
                                    <option value="Gate Access Issue">Gate Access Issue</option>
                                    <option value="Billing & Assessment">Billing & Assessment</option>
                                    <option value="Facility Booking">Facility Booking</option>
                                </select>
                            </div>

                            <!-- Priority -->
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Priority Level</label>
                                <select wire:model.live="formPriority" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="Low">Low</option>
                                    <option value="Normal">Normal</option>
                                    <option value="High">High</option>
                                    <option value="Urgent">Urgent</option>
                                </select>
                            </div>
                        </div>

                        <!-- Notes Textarea -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Detailed Message</label>
                                <span class="text-[11px] text-slate-400 font-mono">{{ strlen($formNotes) }}/250 chars</span>
                            </div>
                            <textarea wire:model.live="formNotes"
                                      rows="3"
                                      placeholder="Provide context or instructions..."
                                      class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                            @error('formNotes') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Terms Checkbox -->
                        <div class="flex items-center gap-2">
                            <input type="checkbox"
                                   id="formAccepted"
                                   wire:model.live="formAccepted"
                                   class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                            <label for="formAccepted" class="text-xs text-slate-700 dark:text-slate-300 select-none">
                                I confirm this request adheres to Community Bylaws <span class="text-rose-500">*</span>
                            </label>
                        </div>
                        @error('formAccepted') <span class="text-xs text-rose-500 block">{{ $message }}</span> @enderror

                        <!-- Action Buttons -->
                        <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                            <button type="button"
                                    wire:click="resetDemoForm"
                                    class="px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                                Reset Form
                            </button>

                            <button type="submit"
                                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-md shadow-blue-500/25 active:scale-95 transition-all">
                                <span wire:loading.remove wire:target="submitDemoForm">Submit Request</span>
                                <span wire:loading wire:target="submitDemoForm" class="flex items-center gap-1.5">
                                    <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    Processing...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Live State Mirror (Telemetry) -->
                <div class="bg-slate-900 text-slate-200 rounded-2xl p-5 border border-slate-800 flex flex-col justify-between font-mono text-xs shadow-inner">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                            <span class="text-blue-400 font-bold uppercase tracking-wider text-[11px]">Livewire Reactive State</span>
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        </div>
                        <div class="space-y-1.5 text-[11px]">
                            <div><span class="text-slate-400">formName:</span> <span class="text-emerald-300">"{{ $formName }}"</span></div>
                            <div><span class="text-slate-400">formEmail:</span> <span class="text-emerald-300">"{{ $formEmail }}"</span></div>
                            <div><span class="text-slate-400">category:</span> <span class="text-amber-300">"{{ $formCategory }}"</span></div>
                            <div><span class="text-slate-400">priority:</span> <span class="text-cyan-300">"{{ $formPriority }}"</span></div>
                            <div><span class="text-slate-400">terms:</span> <span class="{{ $formAccepted ? 'text-emerald-400' : 'text-rose-400' }}">{{ $formAccepted ? 'true' : 'false' }}</span></div>
                            <div><span class="text-slate-400">submitted:</span> <span class="{{ $isFormSubmitted ? 'text-emerald-400' : 'text-slate-400' }}">{{ $isFormSubmitted ? 'true' : 'false' }}</span></div>
                        </div>
                    </div>

                    @if($isFormSubmitted)
                        <div class="mt-4 p-3 rounded-xl bg-emerald-950/80 border border-emerald-800 text-emerald-300 text-xs">
                            ✓ Form validated and dispatched successfully!
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <!-- 2. Tables & Bulk Selection Section -->
        <section class="space-y-4">
            <div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    2. Tables, Search, Filtering & Bulk Actions
                </h3>
                <p class="text-xs text-slate-500">Real-time table filtering, multi-checkbox selection, and bulk operation dispatching.</p>
            </div>

            <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
                <!-- Search & Bulk Action Toolbar -->
                <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3">
                    <div class="relative w-full sm:w-72">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <input type="text"
                               wire:model.live.debounce.250ms="tableSearch"
                               placeholder="Filter hardware devices..."
                               class="w-full pl-9 pr-3 py-1.5 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>

                    <!-- Bulk Actions -->
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-500">
                            {{ count($selectedRows) }} selected
                        </span>
                        <button type="button"
                                wire:click="performBulkAction('Ping Diagnostics')"
                                @disabled(count($selectedRows) === 0)
                                class="px-3 py-1.5 text-xs font-semibold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 disabled:opacity-40 transition-colors">
                            Ping Selected
                        </button>
                        <button type="button"
                                wire:click="performBulkAction('Restart Daemon')"
                                @disabled(count($selectedRows) === 0)
                                class="px-3 py-1.5 text-xs font-semibold rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white disabled:opacity-40 shadow-sm transition-colors">
                            Restart Daemons
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 uppercase font-semibold text-slate-500 border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th scope="col" class="py-3 px-4 w-10">
                                    <input type="checkbox"
                                           wire:model.live="selectAll"
                                           wire:click="toggleSelectAll"
                                           class="w-4 h-4 rounded text-emerald-600 border-slate-300 focus:ring-emerald-500">
                                </th>
                                <th scope="col" class="py-3 px-4">Node Device</th>
                                <th scope="col" class="py-3 px-4">Hardware Type</th>
                                <th scope="col" class="py-3 px-4">Telemetry Latency</th>
                                <th scope="col" class="py-3 px-4">Health Status</th>
                                <th scope="col" class="py-3 px-4 text-right">Heartbeat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse($sampleItems as $item)
                                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="py-3 px-4">
                                        <input type="checkbox"
                                               wire:model.live="selectedRows"
                                               value="{{ $item['id'] }}"
                                               class="w-4 h-4 rounded text-emerald-600 border-slate-300 focus:ring-emerald-500">
                                    </td>
                                    <td class="py-3 px-4 font-semibold text-slate-900 dark:text-white">
                                        {{ $item['name'] }}
                                    </td>
                                    <td class="py-3 px-4 font-mono text-slate-500">
                                        {{ $item['type'] }}
                                    </td>
                                    <td class="py-3 px-4 font-mono">
                                        {{ $item['latency'] }}
                                    </td>
                                    <td class="py-3 px-4">
                                        @if($item['status'] === 'Operational')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                <span class="w-1 h-1 rounded-full bg-emerald-500"></span> Operational
                                            </span>
                                        @elseif($item['status'] === 'Maintenance')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                                Maintenance
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                                                Warning
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-right text-slate-400">
                                        {{ $item['updated'] }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-slate-400">No hardware nodes matched the query.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- 3. Alpine.js Micro-Interactions Section -->
        <section class="space-y-4">
            <div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    3. Alpine.js Lightweight Browser Micro-Interactions
                </h3>
                <p class="text-xs text-slate-500">Zero round-trip client interactions: clipboard copies, animated dropdowns, accordions, and modals.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Alpine Counter & Sync -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200 dark:border-slate-800 space-y-3">
                    <h4 class="text-xs font-bold uppercase text-slate-400">Counter & Livewire Sync</h4>
                    <div class="text-3xl font-extrabold text-blue-600 dark:text-blue-400 font-mono text-center py-2">
                        {{ $liveCounter }}
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                wire:click="decrementCounter"
                                class="flex-1 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 font-bold text-slate-700 dark:text-slate-300 active:scale-95 transition-all">
                            -1
                        </button>
                        <button type="button"
                                wire:click="incrementCounter"
                                class="flex-1 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 font-bold text-white active:scale-95 transition-all">
                            +1
                        </button>
                    </div>
                </div>

                <!-- Alpine Clipboard Tooltip -->
                <div x-data="{ copied: false }" class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200 dark:border-slate-800 space-y-3 flex flex-col justify-between">
                    <div>
                        <h4 class="text-xs font-bold uppercase text-slate-400">Clipboard Copy</h4>
                        <p class="text-xs text-slate-600 dark:text-slate-300 mt-1">Copy secure token with zero server trip.</p>
                    </div>
                    <button type="button"
                            @click="navigator.clipboard.writeText('CYPRESS-SEC-KEY-9942'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="w-full py-2 px-3 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors flex items-center justify-center gap-2">
                        <span x-show="!copied">Copy Token</span>
                        <span x-show="copied" x-cloak class="text-emerald-500 font-bold">Copied!</span>
                    </button>
                </div>

                <!-- Alpine Dropdown Menu -->
                <div x-data="{ open: false }" class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200 dark:border-slate-800 space-y-3 relative">
                    <h4 class="text-xs font-bold uppercase text-slate-400">Animated Dropdown</h4>
                    <p class="text-xs text-slate-600 dark:text-slate-300">Click-outside & escape key support.</p>
                    <button type="button"
                            @click="open = !open"
                            class="w-full py-2 px-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-xs font-semibold text-slate-800 dark:text-slate-200 flex items-center justify-between">
                        <span>Quick Options</span>
                        <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div x-show="open"
                         x-cloak
                         @click.outside="open = false"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="absolute left-4 right-4 bottom-14 z-20 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 py-1 text-xs">
                        <button type="button" @click="open = false; $dispatch('notify', { message: 'Export initiated!', type: 'success' })" class="w-full text-left px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200">Export CSV</button>
                        <button type="button" @click="open = false; $dispatch('notify', { message: 'Audit triggered!', type: 'info' })" class="w-full text-left px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200">Run Audit Scan</button>
                        <button type="button" @click="open = false; $dispatch('notify', { message: 'Security locked down!', type: 'warning' })" class="w-full text-left px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-700 text-rose-600">Lockdown Gates</button>
                    </div>
                </div>

                <!-- Alpine Modal Trigger -->
                <div x-data="{ open: @entangle('showDemoModal') }" class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200 dark:border-slate-800 space-y-3 flex flex-col justify-between">
                    <div>
                        <h4 class="text-xs font-bold uppercase text-slate-400">Modal Dialog</h4>
                        <p class="text-xs text-slate-600 dark:text-slate-300 mt-1">Accessible dialog with blur backdrop.</p>
                    </div>
                    <button type="button"
                            @click="open = true"
                            class="w-full py-2 px-3 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold shadow-md active:scale-95 transition-all">
                        Open Modal
                    </button>
                </div>
            </div>
        </section>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 2: MULTI-FRONTEND STACKS MATRIX -->
    <!-- ========================================================================= -->
    <div x-show="tab === 'matrix'" x-cloak class="space-y-8">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Frontend Technology Synergy in Community Hub</h3>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                Laravel provides unparalleled architectural flexibility. Below is how each requested frontend paradigm is integrated or positioned in this enterprise project.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 mt-6">
                <!-- Livewire -->
                <div class="p-5 rounded-2xl border border-blue-200 dark:border-blue-900/60 bg-blue-50/50 dark:bg-blue-950/20 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-bold text-blue-900 dark:text-blue-300">Livewire (v4)</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-200 dark:bg-blue-900 text-blue-800 dark:text-blue-200">Active</span>
                    </div>
                    <p class="text-xs text-slate-600 dark:text-slate-300">
                        Server-driven real-time reactivity without writing custom REST or GraphQL APIs. Powering our Operations Hub, Gate Passes, Resident Directory, and Incident Desks.
                    </p>
                    <ul class="text-[11px] text-slate-500 dark:text-slate-400 space-y-1 list-disc list-inside">
                        <li>Reactive forms with instant validation</li>
                        <li>Dynamic tables, sorting & filtering</li>
                        <li>Zero bundle overhead for public viewers</li>
                    </ul>
                </div>

                <!-- Alpine.js -->
                <div class="p-5 rounded-2xl border border-cyan-200 dark:border-cyan-900/60 bg-cyan-50/50 dark:bg-cyan-950/20 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-bold text-cyan-900 dark:text-cyan-300">Alpine.js</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-cyan-200 dark:bg-cyan-900 text-cyan-800 dark:text-cyan-200">Bundled</span>
                    </div>
                    <p class="text-xs text-slate-600 dark:text-slate-300">
                        Rugged, minimal JavaScript framework integrated directly with Livewire for micro-interactions that don't need network latency.
                    </p>
                    <ul class="text-[11px] text-slate-500 dark:text-slate-400 space-y-1 list-disc list-inside">
                        <li>Modal dialogs with Escape key listeners</li>
                        <li>Instant tooltips & clipboard copying</li>
                        <li>Smooth UI transitions & toast notifications</li>
                    </ul>
                </div>

                <!-- Flux UI -->
                <div class="p-5 rounded-2xl border border-purple-200 dark:border-purple-900/60 bg-purple-50/50 dark:bg-purple-950/20 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-bold text-purple-900 dark:text-purple-300">Flux UI</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-200 dark:bg-purple-900 text-purple-800 dark:text-purple-200">Design System</span>
                    </div>
                    <p class="text-xs text-slate-600 dark:text-slate-300">
                        Official, meticulously crafted UI component system designed specifically for Livewire and modern Tailwind styling.
                    </p>
                    <ul class="text-[11px] text-slate-500 dark:text-slate-400 space-y-1 list-disc list-inside">
                        <li>Consistent form inputs, selects, & badges</li>
                        <li>Full keyboard accessibility out-of-the-box</li>
                        <li>Unified design tokens and dark mode</li>
                    </ul>
                </div>

                <!-- Tailwind CSS -->
                <div class="p-5 rounded-2xl border border-indigo-200 dark:border-indigo-900/60 bg-indigo-50/50 dark:bg-indigo-950/20 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-bold text-indigo-900 dark:text-indigo-300">Tailwind CSS (3.4)</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-200 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200">Configured</span>
                    </div>
                    <p class="text-xs text-slate-600 dark:text-slate-300">
                        Shared design token layer shared seamlessly across both our Blade/Livewire views and our Inertia/React application.
                    </p>
                    <ul class="text-[11px] text-slate-500 dark:text-slate-400 space-y-1 list-disc list-inside">
                        <li>CSS custom property tokens (--primary, --background)</li>
                        <li>High-contrast accessible color scales (WCAG AAA)</li>
                        <li>Zero style duplication</li>
                    </ul>
                </div>

                <!-- Inertia + React -->
                <div class="p-5 rounded-2xl border border-sky-200 dark:border-sky-900/60 bg-sky-50/50 dark:bg-sky-950/20 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-bold text-sky-900 dark:text-sky-300">Inertia + React</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-200 dark:bg-sky-900 text-sky-800 dark:text-sky-200">Primary SPA</span>
                    </div>
                    <p class="text-xs text-slate-600 dark:text-slate-300">
                        Powers the main resident portal (<code class="text-[10px]">/dashboard</code>) with client-side SPA routing, Recharts dashboards, and Leaflet interactive community maps.
                    </p>
                    <ul class="text-[11px] text-slate-500 dark:text-slate-400 space-y-1 list-disc list-inside">
                        <li>Client-side route navigation without full refresh</li>
                        <li>Shared controllers & request validation</li>
                        <li>Full React 18 ecosystem</li>
                    </ul>
                </div>

                <!-- Vue & Svelte Compatibility -->
                <div class="p-5 rounded-2xl border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50/50 dark:bg-emerald-950/20 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-bold text-emerald-900 dark:text-emerald-300">Vue & Svelte</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-200 dark:bg-emerald-900 text-emerald-800 dark:text-emerald-200">Pluggable</span>
                    </div>
                    <p class="text-xs text-slate-600 dark:text-slate-300">
                        Because Laravel + Inertia decouples the server from the client renderer, swapping or adding Vue 3 or Svelte adapters is seamless via <code class="text-[10px]">@inertiajs/vue3</code> or <code class="text-[10px]">@inertiajs/svelte</code>.
                    </p>
                    <ul class="text-[11px] text-slate-500 dark:text-slate-400 space-y-1 list-disc list-inside">
                        <li>Identical controller endpoints for all adapters</li>
                        <li>Micro-frontend widgets or full SPA views</li>
                        <li>Progressive migration paths</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Demo Modal Instance -->
    <div x-data="{ open: @entangle('showDemoModal') }"
         x-show="open"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         role="dialog"
         aria-modal="true">
        <div x-show="open" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" @click="open = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open" class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-900 text-left shadow-2xl sm:w-full sm:max-w-md border border-slate-200 dark:border-slate-800 p-6 space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Livewire + Alpine Modal</h3>
                    <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">{{ $modalContent }}</p>
                <div class="flex justify-end pt-2">
                    <button type="button" @click="open = false" class="px-4 py-2 text-xs font-semibold bg-blue-600 text-white rounded-xl shadow-md">Got It</button>
                </div>
            </div>
        </div>
    </div>
</div>
