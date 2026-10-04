<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                <span>Security Warnings & Incident Desk</span>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-rose-100 dark:bg-rose-950 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                    {{ $totalAlerts }} Active Notices
                </span>
            </h2>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                Community incident alerts corroborated in real time by verified homeowners and security patrol.
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <button type="button"
                    wire:click="openCreateModal"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold shadow-sm shadow-rose-500/20 active:scale-95 transition-all">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>Broadcast Alert</span>
            </button>
        </div>
    </div>

    <!-- Search Input -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 shadow-sm border border-slate-200 dark:border-slate-800">
        <div class="relative max-w-md">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="Search alerts by headline, author, or keyword..."
                   class="w-full pl-9 pr-8 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-rose-500">
            @if($search !== '')
                <button type="button" wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            @endif
        </div>
    </div>

    <!-- Warnings Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @forelse($warnings as $warning)
            @php
                $userVote = $currentUser ? $warning->responseFor($currentUser) : null;
                $isAuthor = $currentUser && $warning->author_id === $currentUser->id;
            @endphp
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200 dark:border-slate-800 flex flex-col justify-between hover:border-slate-300 dark:hover:border-slate-700 transition-all">
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </span>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white leading-snug">{{ $warning->title }}</h3>
                        </div>
                        @can('manageSecurity')
                        <button type="button"
                                wire:click="deleteWarning({{ $warning->id }})"
                                class="text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                title="Dismiss / Resolve Alert">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                        @endcan
                    </div>

                    <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                        {{ $warning->description }}
                    </p>
                </div>

                <div class="mt-5 pt-4 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div class="text-slate-500 flex items-center gap-1.5">
                        <span class="font-medium text-slate-700 dark:text-slate-300">{{ $warning->author_name }}</span>
                        <span>&middot;</span>
                        <span>{{ $warning->issued_at?->diffForHumans() ?? 'Just now' }}</span>
                    </div>

                    <!-- Confirmation Votes -->
                    <div class="flex items-center gap-2">
                        <!-- Confirm Button -->
                        <button type="button"
                                wire:click="vote({{ $warning->id }}, 'confirmed')"
                                @disabled($isAuthor)
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl font-semibold transition-all text-xs
                                    {{ $userVote === 'confirmed' ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-500/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-emerald-50 hover:text-emerald-700' }}
                                    {{ $isAuthor ? 'opacity-50 cursor-not-allowed' : 'active:scale-95' }}">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span>Confirm ({{ $warning->confirmsCount() }})</span>
                        </button>

                        <!-- Deny Button -->
                        <button type="button"
                                wire:click="vote({{ $warning->id }}, 'denied')"
                                @disabled($isAuthor)
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl font-semibold transition-all text-xs
                                    {{ $userVote === 'denied' ? 'bg-rose-600 text-white shadow-sm shadow-rose-500/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-rose-50 hover:text-rose-700' }}
                                    {{ $isAuthor ? 'opacity-50 cursor-not-allowed' : 'active:scale-95' }}">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            <span>Deny ({{ $warning->deniesCount() }})</span>
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-white dark:bg-slate-900 rounded-2xl p-12 text-center border border-slate-200 dark:border-slate-800">
                <div class="w-12 h-12 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">All Clear — No Active Warnings</h3>
                <p class="text-xs text-slate-500 mt-1">There are no uncorroborated security or safety alerts in the estate at this time.</p>
            </div>
        @endforelse
    </div>

    @if($warnings->hasPages())
        <div class="p-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800">
            {{ $warnings->links() }}
        </div>
    @endif

    <!-- Broadcast Alert Modal -->
    <div x-data="{ open: @entangle('showCreateModal') }"
         x-show="open"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         role="dialog"
         aria-modal="true">
        <div x-show="open" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" @click="$wire.closeCreateModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open" class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-900 text-left shadow-2xl sm:w-full sm:max-w-lg border border-slate-200 dark:border-slate-800">
                <form wire:submit.prevent="createWarning">
                    <div class="px-6 pt-6 pb-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            Broadcast Security Alert
                        </h3>
                        <button type="button" wire:click="closeCreateModal" class="text-slate-400 hover:text-slate-600">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Headline / Title <span class="text-rose-500">*</span></label>
                            <input type="text"
                                   wire:model="title"
                                   placeholder="e.g. Suspicious Vehicle near Gate 2"
                                   class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-rose-500">
                            @error('title') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Detailed Description <span class="text-rose-500">*</span></label>
                            <textarea wire:model="description"
                                      rows="4"
                                      placeholder="Provide concise details: vehicle description, location, time observed, or guidance for neighbors..."
                                      class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-rose-500"></textarea>
                            @error('description') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-3">
                        <button type="button" wire:click="closeCreateModal" class="px-4 py-2 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 rounded-xl">Cancel</button>
                        <button type="submit" class="px-5 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl shadow-md active:scale-95 transition-all">Broadcast Now</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
