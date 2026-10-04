<div class="space-y-6">
    <!-- Header & Controls -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                <span>Gate Passes & Visitor Clearance</span>
                <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/60 text-blue-700 dark:text-blue-300">
                    {{ $passes->total() }} Total
                </span>
            </h2>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                Issue, inspect, search, filter, and track digital credentials in real time.
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <button type="button"
                    wire:click="openCreateModal"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold shadow-sm shadow-blue-500/20 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all active:scale-95">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Issue Gate Pass</span>
            </button>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 shadow-sm border border-slate-200 dark:border-slate-800 space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <!-- Search Input -->
            <div class="lg:col-span-2 relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Search by holder name, pass ID, property..."
                       class="w-full pl-9 pr-8 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                @if($search !== '')
                    <button type="button" wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                @endif
            </div>

            <!-- Status Filter -->
            <div>
                <select wire:model.live="statusFilter" class="w-full py-2 px-3 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st->value }}">{{ $st->label() }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Category Filter -->
            <div>
                <select wire:model.live="categoryFilter" class="w-full py-2 px-3 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Gate Filter & Reset -->
            <div class="flex items-center gap-2">
                <select wire:model.live="gateFilter" class="flex-1 py-2 px-3 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Gates</option>
                    @foreach($gates as $gt)
                        <option value="{{ $gt->value }}">{{ $gt->label() }}</option>
                    @endforeach
                </select>

                @if($search || $statusFilter || $categoryFilter || $gateFilter)
                    <button type="button"
                            wire:click="resetFilters"
                            title="Reset filters"
                            class="p-2 rounded-xl text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </button>
                @endif
            </div>
        </div>

        <!-- Live Loading Indicator -->
        <div wire:loading.delay class="w-full">
            <div class="h-0.5 w-full bg-blue-100 overflow-hidden rounded">
                <div class="w-full h-full bg-blue-600 animate-pulse"></div>
            </div>
        </div>
    </div>

    <!-- Passes Data Table -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="bg-slate-50/80 dark:bg-slate-800/50 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 select-none">
                    <tr>
                        <th scope="col" class="py-3.5 px-4 cursor-pointer hover:text-blue-600" wire:click="sortBy('pass_id')">
                            <div class="flex items-center gap-1.5">
                                <span>Pass ID</span>
                                @if($sortField === 'pass_id')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="py-3.5 px-4 cursor-pointer hover:text-blue-600" wire:click="sortBy('holder_name')">
                            <div class="flex items-center gap-1.5">
                                <span>Holder / Destination</span>
                                @if($sortField === 'holder_name')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="py-3.5 px-4">Category</th>
                        <th scope="col" class="py-3.5 px-4">Gate & Zone</th>
                        <th scope="col" class="py-3.5 px-4 cursor-pointer hover:text-blue-600" wire:click="sortBy('valid_until')">
                            <div class="flex items-center gap-1.5">
                                <span>Validity</span>
                                @if($sortField === 'valid_until')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="py-3.5 px-4 cursor-pointer hover:text-blue-600" wire:click="sortBy('status')">
                            <div class="flex items-center gap-1.5">
                                <span>Status</span>
                                @if($sortField === 'status')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="py-3.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($passes as $pass)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors group">
                            <!-- Pass ID -->
                            <td class="py-3.5 px-4 font-mono font-semibold text-xs text-blue-600 dark:text-blue-400">
                                <button type="button" wire:click="viewPass({{ $pass->id }})" class="hover:underline flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                    </svg>
                                    {{ $pass->pass_id }}
                                </button>
                            </td>

                            <!-- Holder & Destination -->
                            <td class="py-3.5 px-4">
                                <div class="font-medium text-slate-900 dark:text-white">{{ $pass->holder_name }}</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    {{ $pass->property }}
                                </div>
                            </td>

                            <!-- Category Badge -->
                            <td class="py-3.5 px-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                    {{ $pass->category?->label() ?? $pass->category }}
                                </span>
                            </td>

                            <!-- Gate & Zone -->
                            <td class="py-3.5 px-4">
                                <div class="text-xs font-semibold text-slate-800 dark:text-slate-200">
                                    {{ $pass->designated_gate?->label() ?? 'Any Gate' }}
                                </div>
                                <div class="text-xs text-slate-500 font-mono">{{ $pass->access_zone }}</div>
                            </td>

                            <!-- Validity Window -->
                            <td class="py-3.5 px-4 text-xs">
                                <div class="text-slate-700 dark:text-slate-300">
                                    {{ $pass->valid_until ? $pass->valid_until->format('M d, Y H:i') : 'Indefinite' }}
                                </div>
                                @if($pass->valid_until && $pass->valid_until->isPast())
                                    <span class="text-rose-500 font-medium">Expired</span>
                                @elseif($pass->valid_until)
                                    <span class="text-slate-400">{{ $pass->valid_until->diffForHumans() }}</span>
                                @endif
                            </td>

                            <!-- Status Badge -->
                            <td class="py-3.5 px-4">
                                @php
                                    $st = $pass->status?->value ?? 'ACTIVE';
                                @endphp
                                @if($st === 'ACTIVE')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                    </span>
                                @elseif($st === 'CHECKED_IN')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-950/80 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-ping"></span> On Site
                                    </span>
                                @elseif($st === 'CHECKED_OUT')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                        Checked Out
                                    </span>
                                @elseif($st === 'REVOKED')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950/80 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                        Revoked
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300">
                                        {{ $pass->status?->label() ?? $st }}
                                    </span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="py-3.5 px-4 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    @if($st === 'ACTIVE' || $st === 'ISSUED')
                                        <button type="button"
                                                wire:click="checkIn({{ $pass->id }})"
                                                class="px-2.5 py-1 rounded-lg text-xs font-medium bg-emerald-50 hover:bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:hover:bg-emerald-900/60 dark:text-emerald-300 transition-colors"
                                                title="Record gate entrance">
                                            Check In
                                        </button>
                                    @elseif($st === 'CHECKED_IN')
                                        <button type="button"
                                                wire:click="checkOut({{ $pass->id }})"
                                                class="px-2.5 py-1 rounded-lg text-xs font-medium bg-blue-50 hover:bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:hover:bg-blue-900/60 dark:text-blue-300 transition-colors"
                                                title="Record gate exit">
                                            Check Out
                                        </button>
                                    @endif

                                    @if($st !== 'REVOKED' && $st !== 'EXPIRED')
                                        <button type="button"
                                                wire:click="confirmRevoke({{ $pass->id }})"
                                                class="px-2 py-1 rounded-lg text-xs font-medium text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/50 transition-colors"
                                                title="Revoke pass">
                                            Revoke
                                        </button>
                                    @endif

                                    <button type="button"
                                            wire:click="viewPass({{ $pass->id }})"
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800"
                                            title="View Details & QR">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 px-4 text-center">
                                <div class="max-w-xs mx-auto space-y-2">
                                    <div class="w-10 h-10 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <p class="text-sm font-medium text-slate-900 dark:text-white">No passes match your criteria</p>
                                    <p class="text-xs text-slate-500">Try adjusting your search terms or clearing status filters.</p>
                                    <button type="button" wire:click="resetFilters" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline">Clear all filters</button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        @if($passes->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $passes->links() }}
            </div>
        @endif
    </div>

    <!-- ========================================================================= -->
    <!-- Alpine.js Accessible Modal: Create Gate Pass -->
    <!-- ========================================================================= -->
    <div x-data="{ open: @entangle('showCreateModal') }"
         x-show="open"
         x-cloak
         @keydown.escape.window="if(open) { $wire.closeCreateModal() }"
         class="fixed inset-0 z-50 overflow-y-auto"
         aria-labelledby="modal-create-title"
         role="dialog"
         aria-modal="true">
        <!-- Backdrop Blur -->
        <div x-show="open"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm transition-opacity"
             @click="$wire.closeCreateModal()"></div>

        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div x-show="open"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-900 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200 dark:border-slate-800">

                <form wire:submit.prevent="createPass">
                    <div class="px-6 pt-6 pb-4 border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-center justify-between">
                            <h3 id="modal-create-title" class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Issue New Gate Pass
                            </h3>
                            <button type="button" wire:click="closeCreateModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="p-6 space-y-4">
                        <!-- Visitor / Holder Name -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Holder / Visitor Full Name <span class="text-rose-500">*</span></label>
                            <input type="text"
                                   wire:model="holder_name"
                                   placeholder="e.g. John Doe"
                                   class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                            @error('holder_name') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Category & Gate -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Category</label>
                                <select wire:model="category" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Designated Gate</label>
                                <select wire:model="designated_gate" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    @foreach($gates as $gt)
                                        <option value="{{ $gt->value }}">{{ $gt->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Property Destination & Access Zone -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Property Destination <span class="text-rose-500">*</span></label>
                                <input type="text"
                                       wire:model="property"
                                       placeholder="e.g. Lot 42, Pinecrest Way"
                                       class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                @error('property') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Access Zone</label>
                                <input type="text"
                                       wire:model="access_zone"
                                       placeholder="e.g. ZONE-HOST-RESIDENCE"
                                       class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                @error('access_zone') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Validity Dates -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Valid From</label>
                                <input type="datetime-local"
                                       wire:model="valid_from"
                                       class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                @error('valid_from') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Valid Until</label>
                                <input type="datetime-local"
                                       wire:model="valid_until"
                                       class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                @error('valid_until') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Single Entry Option -->
                        <div class="flex items-center gap-2 pt-2">
                            <input type="checkbox"
                                   id="single_entry"
                                   wire:model="single_entry"
                                   class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                            <label for="single_entry" class="text-xs text-slate-700 dark:text-slate-300 select-none">
                                Single entry only (auto-expires upon exit)
                            </label>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-3">
                        <button type="button"
                                wire:click="closeCreateModal"
                                class="px-4 py-2 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-xl transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-5 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-md shadow-blue-500/20 active:scale-95 transition-all">
                            <span wire:loading.remove wire:target="createPass">Issue Pass</span>
                            <span wire:loading wire:target="createPass" class="flex items-center gap-1.5">
                                <svg class="animate-spin -ml-1 mr-2 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Issuing...
                            </span>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- Alpine.js Accessible Modal: Pass Detail & Digital Badge View -->
    <!-- ========================================================================= -->
    <div x-data="{ open: @entangle('showDetailModal') }"
         x-show="open"
         x-cloak
         @keydown.escape.window="if(open) { $wire.closeDetailModal() }"
         class="fixed inset-0 z-50 overflow-y-auto"
         role="dialog"
         aria-modal="true">
        <div x-show="open"
             class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm"
             @click="$wire.closeDetailModal()"></div>

        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open"
                 class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-900 text-left shadow-2xl sm:w-full sm:max-w-md border border-slate-200 dark:border-slate-800">
                @if($selectedPass)
                    <div class="p-6 text-center space-y-4">
                        <div class="flex items-center justify-between text-xs text-slate-400">
                            <span class="font-mono">Security Gate Clearance</span>
                            <button type="button" wire:click="closeDetailModal" class="hover:text-slate-600 dark:hover:text-slate-200">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <!-- Visual Pass Card / Badge Simulator -->
                        <div class="p-6 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-800 text-white shadow-xl relative overflow-hidden text-left border border-slate-700">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="text-[10px] uppercase font-bold tracking-widest text-blue-400">Community Gate Pass</div>
                                    <div class="text-lg font-bold">{{ $selectedPass->holder_name }}</div>
                                    <div class="text-xs text-slate-300 font-mono mt-0.5">{{ $selectedPass->property }}</div>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-500/20 text-blue-300 border border-blue-400/30">
                                    {{ $selectedPass->category?->label() ?? 'Pass' }}
                                </span>
                            </div>

                            <!-- Simulated QR Matrix -->
                            <div class="mt-6 flex items-center justify-center p-4 bg-white rounded-xl shadow-inner mx-auto w-36 h-36">
                                <div class="text-center">
                                    <svg class="w-24 h-24 mx-auto text-slate-900" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M2 2h8v8H2V2zm2 2v4h4V4H4zm10-2h8v8h-8V2zm2 2v4h4V4h-4zM2 14h8v8H2v-8zm2 2v4h4v-4H4zm14 0h4v4h-4v-4zm-4 4h4v4h-4v-4zm4-4h4v-4h-4v4zm-4-4h4v4h-4v-4zm-4 4h4v4h-4v-4z"/>
                                    </svg>
                                    <span class="block font-mono text-[9px] text-slate-500 font-bold mt-1">{{ $selectedPass->pass_id }}</span>
                                </div>
                            </div>

                            <div class="mt-4 pt-3 border-t border-slate-700/60 flex items-center justify-between text-[11px] text-slate-400">
                                <div>Gate: <span class="text-white font-medium">{{ $selectedPass->designated_gate?->label() ?? 'Any Gate' }}</span></div>
                                <div>Zone: <span class="text-white font-mono">{{ $selectedPass->access_zone }}</span></div>
                            </div>
                        </div>

                        <!-- Alpine Copy Button -->
                        <div x-data="{ copied: false }" class="pt-2">
                            <button type="button"
                                    @click="navigator.clipboard.writeText('{{ $selectedPass->pass_id }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="w-full py-2 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors flex items-center justify-center gap-1.5">
                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                                <span x-show="!copied">Copy Pass Token Code</span>
                                <span x-show="copied" x-cloak class="text-emerald-500 font-bold">Pass ID Copied to Clipboard!</span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- Alpine.js Accessible Modal: Revoke Pass Confirmation -->
    <!-- ========================================================================= -->
    <div x-data="{ open: @entangle('showRevokeModal') }"
         x-show="open"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         role="dialog"
         aria-modal="true">
        <div x-show="open" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" @click="$wire.closeRevokeModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open" class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-900 text-left shadow-2xl sm:w-full sm:max-w-md border border-slate-200 dark:border-slate-800 p-6 space-y-4">
                <div class="w-12 h-12 rounded-full bg-rose-100 dark:bg-rose-950/60 text-rose-600 flex items-center justify-center mx-auto">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div class="text-center">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Revoke Gate Pass</h3>
                    <p class="text-xs text-slate-500 mt-1">This will invalidate the credential immediately. The visitor will be refused at the gates.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Reason for Revocation</label>
                    <input type="text"
                           wire:model="revocationReason"
                           placeholder="e.g. Expired lease or resident request"
                           class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-rose-500">
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" wire:click="closeRevokeModal" class="px-4 py-2 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 rounded-xl">Cancel</button>
                    <button type="button" wire:click="revokePass" class="px-4 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl shadow-md active:scale-95 transition-all">Revoke Now</button>
                </div>
            </div>
        </div>
    </div>
</div>
