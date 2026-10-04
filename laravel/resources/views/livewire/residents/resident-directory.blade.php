<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                <span>Resident & Homeowner Directory</span>
                <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300">
                    {{ $residents->total() }} Registered
                </span>
            </h2>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                Searchable directory of community owners, tenants, and estate staff with credential records.
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <button type="button"
                    wire:click="openCreateModal"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-sm shadow-indigo-500/20 active:scale-95 transition-all">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
                <span>Add Resident</span>
            </button>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 shadow-sm border border-slate-200 dark:border-slate-800 space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <!-- Search Input -->
            <div class="lg:col-span-2 relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Search by name, lot number, email, street..."
                       class="w-full pl-9 pr-8 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                @if($search !== '')
                    <button type="button" wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                @endif
            </div>

            <!-- Role Filter -->
            <div>
                <select wire:model.live="roleFilter" class="w-full py-2 px-3 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Roles</option>
                    @foreach($roles as $r)
                        <option value="{{ $r->value }}">{{ $r->label() }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Status Filter & Reset -->
            <div class="flex items-center gap-2">
                <select wire:model.live="statusFilter" class="flex-1 py-2 px-3 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Statuses</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Deactivated</option>
                </select>

                @if($search || $roleFilter || $statusFilter)
                    <button type="button" wire:click="resetFilters" title="Clear filters" class="p-2 rounded-xl text-slate-500 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </button>
                @endif
            </div>
        </div>

        <!-- Realtime indicator -->
        <div wire:loading.delay class="w-full">
            <div class="h-0.5 w-full bg-indigo-100 overflow-hidden rounded">
                <div class="w-full h-full bg-indigo-600 animate-pulse"></div>
            </div>
        </div>
    </div>

    <!-- Residents Data Table -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="bg-slate-50/80 dark:bg-slate-800/50 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 select-none">
                    <tr>
                        <th scope="col" class="py-3.5 px-4 cursor-pointer hover:text-indigo-600" wire:click="sortBy('name')">
                            <div class="flex items-center gap-1.5">
                                <span>Resident</span>
                                @if($sortField === 'name')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="py-3.5 px-4 cursor-pointer hover:text-indigo-600" wire:click="sortBy('lot')">
                            <div class="flex items-center gap-1.5">
                                <span>Lot & Residence</span>
                                @if($sortField === 'lot')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </div>
                        </th>
                        <th scope="col" class="py-3.5 px-4">Role</th>
                        <th scope="col" class="py-3.5 px-4">Contact</th>
                        <th scope="col" class="py-3.5 px-4 text-center">Active Passes</th>
                        <th scope="col" class="py-3.5 px-4">Status</th>
                        <th scope="col" class="py-3.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($residents as $user)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors group">
                            <!-- Resident Name & Avatar -->
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-500 flex items-center justify-center text-white font-bold text-xs shadow-sm flex-shrink-0">
                                        {{ strtoupper(substr($user->name, 0, 2)) }}
                                    </div>
                                    <div>
                                        <button type="button" wire:click="viewProfile({{ $user->id }})" class="font-semibold text-slate-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 text-left">
                                            {{ $user->name }}
                                        </button>
                                        <div class="text-xs text-slate-400 font-mono">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>

                            <!-- Lot & Residence -->
                            <td class="py-3.5 px-4">
                                <div class="font-medium text-slate-800 dark:text-slate-200">{{ $user->lot ?: 'Unassigned' }}</div>
                                <div class="text-xs text-slate-500">{{ $user->street }}</div>
                            </td>

                            <!-- Role -->
                            <td class="py-3.5 px-4">
                                @php
                                    $roleVal = $user->role?->value ?? (string)$user->role;
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                                    {{ str_contains($roleVal, 'Admin') ? 'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300' :
                                       (str_contains($roleVal, 'Homeowner') ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300' :
                                       'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300') }}">
                                    {{ $user->role?->label() ?? $roleVal }}
                                </span>
                            </td>

                            <!-- Contact -->
                            <td class="py-3.5 px-4 text-xs text-slate-600 dark:text-slate-400 font-mono">
                                {{ $user->phone ?: 'No phone' }}
                            </td>

                            <!-- Active Passes Count -->
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                    {{ $user->gate_passes_count ?? 0 }}
                                </span>
                            </td>

                            <!-- Status -->
                            <td class="py-3.5 px-4">
                                @if($user->status === 'Active')
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                        {{ ucfirst($user->status) }}
                                    </span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="py-3.5 px-4 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <button type="button"
                                            wire:click="toggleUserStatus({{ $user->id }})"
                                            class="px-2 py-1 rounded-lg text-xs font-medium {{ $user->status === 'Active' ? 'text-amber-600 hover:bg-amber-50' : 'text-emerald-600 hover:bg-emerald-50' }} transition-colors"
                                            title="Toggle active status">
                                        {{ $user->status === 'Active' ? 'Deactivate' : 'Activate' }}
                                    </button>

                                    <button type="button"
                                            wire:click="viewProfile({{ $user->id }})"
                                            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800"
                                            title="View resident profile">
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
                                <p class="text-sm font-medium text-slate-900 dark:text-white">No residents match this filter</p>
                                <button type="button" wire:click="resetFilters" class="text-xs text-indigo-600 font-semibold hover:underline mt-1">Reset all filters</button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($residents->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $residents->links() }}
            </div>
        @endif
    </div>

    <!-- ========================================================================= -->
    <!-- Alpine.js Accessible Modal: Register Resident -->
    <!-- ========================================================================= -->
    <div x-data="{ open: @entangle('showCreateModal') }"
         x-show="open"
         x-cloak
         @keydown.escape.window="if(open) { $wire.closeCreateModal() }"
         class="fixed inset-0 z-50 overflow-y-auto"
         role="dialog"
         aria-modal="true">
        <div x-show="open" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" @click="$wire.closeCreateModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open" class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-900 text-left shadow-2xl sm:w-full sm:max-w-lg border border-slate-200 dark:border-slate-800">
                <form wire:submit.prevent="createResident">
                    <div class="px-6 pt-6 pb-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Register New Resident
                        </h3>
                        <button type="button" wire:click="closeCreateModal" class="text-slate-400 hover:text-slate-600">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Full Legal Name <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model="name" placeholder="e.g. Alice Vance" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                            @error('name') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email Address <span class="text-rose-500">*</span></label>
                                <input type="email" wire:model="email" placeholder="alice@example.com" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                                @error('email') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Phone Number</label>
                                <input type="tel" wire:model="phone" placeholder="+1 (555) 019-2834" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                                @error('phone') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Role</label>
                                <select wire:model="role" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                                    @foreach($roles as $r)
                                        <option value="{{ $r->value }}">{{ $r->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Lot / Unit <span class="text-rose-500">*</span></label>
                                <input type="text" wire:model="lot" placeholder="Lot 42" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                                @error('lot') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Street</label>
                                <input type="text" wire:model="street" placeholder="Palm Blvd" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                                @error('street') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-3">
                        <button type="button" wire:click="closeCreateModal" class="px-4 py-2 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 rounded-xl">Cancel</button>
                        <button type="submit" class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md active:scale-95 transition-all">Save Resident</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- Alpine.js Accessible Modal: Resident Profile Drawer -->
    <!-- ========================================================================= -->
    <div x-data="{ open: @entangle('showProfileModal') }"
         x-show="open"
         x-cloak
         @keydown.escape.window="if(open) { $wire.closeProfileModal() }"
         class="fixed inset-0 z-50 overflow-y-auto"
         role="dialog"
         aria-modal="true">
        <div x-show="open" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" @click="$wire.closeProfileModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open" class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-900 text-left shadow-2xl sm:w-full sm:max-w-md border border-slate-200 dark:border-slate-800 p-6 space-y-4">
                @if($selectedUser)
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-xs uppercase font-bold text-slate-400">Resident Dossier</span>
                        <button type="button" wire:click="closeProfileModal" class="text-slate-400 hover:text-slate-600">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center text-white font-extrabold text-lg shadow-md">
                            {{ strtoupper(substr($selectedUser->name, 0, 2)) }}
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ $selectedUser->name }}</h3>
                            <div class="text-xs text-slate-500">{{ $selectedUser->email }}</div>
                            <span class="inline-block mt-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                {{ $selectedUser->role?->label() ?? $selectedUser->role }}
                            </span>
                        </div>
                    </div>

                    <div class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-xl space-y-2 text-xs">
                        <div class="flex justify-between"><span class="text-slate-500">Property:</span> <span class="font-medium text-slate-900 dark:text-white">{{ $selectedUser->lot }} &middot; {{ $selectedUser->street }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Phone:</span> <span class="font-mono text-slate-900 dark:text-white">{{ $selectedUser->phone ?: 'Not provided' }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Account Status:</span> <span class="font-medium text-emerald-600 uppercase">{{ $selectedUser->status }}</span></div>
                    </div>

                    <div>
                        <h4 class="text-xs font-bold uppercase text-slate-400 mb-2">Recent Gate Passes ({{ $selectedUser->gatePasses->count() }})</h4>
                        <div class="space-y-1.5 max-h-40 overflow-y-auto">
                            @forelse($selectedUser->gatePasses as $p)
                                <div class="p-2 rounded-lg bg-slate-50 dark:bg-slate-800/80 flex items-center justify-between text-xs">
                                    <span class="font-mono font-semibold text-blue-600">{{ $p->pass_id }}</span>
                                    <span class="text-slate-500">{{ $p->status?->label() ?? $p->status }}</span>
                                </div>
                            @empty
                                <p class="text-xs text-slate-400 italic">No gate passes recorded yet.</p>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
