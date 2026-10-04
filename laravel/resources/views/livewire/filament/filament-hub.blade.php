<div class="space-y-8">
    <!-- Panel Switcher & Navigation Header -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-200 dark:border-slate-800 space-y-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                        Filament 5 Architecture Suite
                    </span>
                    <span class="text-xs text-slate-400">&bull;</span>
                    <span class="text-xs font-medium text-slate-500">Multi-Panel &bull; CRUD &bull; Infolists &bull; RBAC</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    Filament Panel Management
                </h1>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                    Separate internal team admin panels and customer/resident self-service portals with Laravel policy authorization.
                </p>
            </div>

            <!-- Panel Selector Buttons -->
            <div class="flex items-center p-1 rounded-2xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700">
                <button type="button"
                        wire:click="switchPanel('admin')"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2
                            {{ $currentPanel === 'admin' ? 'bg-amber-500 text-slate-950 shadow-md font-extrabold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900' }}">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <span>Admin Panel (/admin)</span>
                </button>

                <button type="button"
                        wire:click="switchPanel('portal')"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2
                            {{ $currentPanel === 'portal' ? 'bg-blue-600 text-white shadow-md font-extrabold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900' }}">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span>Resident Portal (/portal)</span>
                </button>
            </div>
        </div>

        <!-- View Switcher Tabs -->
        <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center gap-2 overflow-x-auto">
            <button type="button" wire:click="setView('dashboard')" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all {{ $currentView === 'dashboard' ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                Widgets & Dashboard
            </button>
            <button type="button" wire:click="setView('passes')" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all {{ $currentView === 'passes' ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                Resource CRUD & Table
            </button>
            <button type="button" wire:click="setView('rbac')" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all {{ $currentView === 'rbac' ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                Authorization & Policies
            </button>
            <button type="button" wire:click="setView('notifications')" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all {{ $currentView === 'notifications' ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                Filament Notifications
            </button>
            <button type="button" wire:click="setView('architecture')" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all {{ $currentView === 'architecture' ? 'bg-slate-900 dark:bg-white text-white dark:text-slate-900' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800' }}">
                Filament 5 Packages
            </button>
        </div>
    </div>

    <!-- Active View Content -->
    <div>
        <!-- 1. DASHBOARD & WIDGETS -->
        @if($currentView === 'dashboard')
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>Filament StatsOverviewWidget</span>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                Panel: {{ ucfirst($currentPanel) }}
                            </span>
                        </h2>
                        <p class="text-xs text-slate-500">Filament dashboard widgets calculating statistics and trends dynamically.</p>
                    </div>
                </div>

                <!-- Stats Overview Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    @foreach($stats as $stat)
                        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200 dark:border-slate-800 space-y-2">
                            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ $stat->getLabel() }}</div>
                            <div class="text-3xl font-extrabold text-slate-900 dark:text-white">{{ $stat->getValue() }}</div>
                            <div class="text-[11px] flex items-center gap-1 font-medium
                                {{ $stat->getColor() === 'success' ? 'text-emerald-600 dark:text-emerald-400' :
                                   ($stat->getColor() === 'danger' ? 'text-rose-600 dark:text-rose-400' :
                                   ($stat->getColor() === 'info' ? 'text-blue-600 dark:text-blue-400' : 'text-indigo-600 dark:text-indigo-400')) }}">
                                <span>&bull;</span>
                                <span>{{ $stat->getDescription() }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Panel Details & Navigation Groups -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800 space-y-4">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Active Panel Configuration</h3>
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800"><span class="text-slate-500">Panel ID:</span> <span class="font-mono font-bold">{{ $currentPanel }}</span></div>
                            <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800"><span class="text-slate-500">Route Path:</span> <span class="font-mono text-blue-600">/{{ $currentPanel }}</span></div>
                            <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800"><span class="text-slate-500">Authentication Guard:</span> <span class="font-mono">web (session)</span></div>
                            <div class="flex justify-between py-1.5"><span class="text-slate-500">Target Audience:</span> <span class="font-semibold">{{ $currentPanel === 'admin' ? 'Internal Security & Estate Admin' : 'Homeowners & Renters' }}</span></div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800 space-y-4">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Navigation Groups in {{ ucfirst($currentPanel) }}</h3>
                        <div class="space-y-2 text-xs">
                            @if($currentPanel === 'admin')
                                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 flex items-center justify-between">
                                    <span class="font-medium text-slate-800 dark:text-slate-200">1. Access & Gate Operations</span>
                                    <span class="text-[11px] text-slate-400 font-mono">GatePassResource, Visitors</span>
                                </div>
                                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 flex items-center justify-between">
                                    <span class="font-medium text-slate-800 dark:text-slate-200">2. Community & Residents</span>
                                    <span class="text-[11px] text-slate-400 font-mono">ResidentResource, Staff</span>
                                </div>
                                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 flex items-center justify-between">
                                    <span class="font-medium text-slate-800 dark:text-slate-200">3. Safety & Security</span>
                                    <span class="text-[11px] text-slate-400 font-mono">WarningResource</span>
                                </div>
                            @else
                                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 flex items-center justify-between">
                                    <span class="font-medium text-slate-800 dark:text-slate-200">1. My Gate Access</span>
                                    <span class="text-[11px] text-slate-400 font-mono">MyPassesResource</span>
                                </div>
                                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 flex items-center justify-between">
                                    <span class="font-medium text-slate-800 dark:text-slate-200">2. Estate Notices</span>
                                    <span class="text-[11px] text-slate-400 font-mono">CommunityNotices</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

        <!-- 2. RESOURCE CRUD, FORMS, TABLES, ACTIONS -->
        @elseif($currentView === 'passes')
            <div class="space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>GatePassResource &middot; Filament Tables & Actions</span>
                        </h2>
                        <p class="text-xs text-slate-500">Demonstrating TextColumn, BadgeColumn, SelectFilter, and Row Actions (Check In, Check Out, Revoke).</p>
                    </div>

                    <button type="button"
                            wire:click="openCreateModal"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs shadow-sm active:scale-95 transition-all">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>New GatePass (Form Schema)</span>
                    </button>
                </div>

                <!-- Table Filters & Search -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 shadow-sm border border-slate-200 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="Search pass ID, holder, property..."
                           class="py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">

                    <select wire:model.live="statusFilter" class="py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                        <option value="">All Statuses (SelectFilter)</option>
                        <option value="ACTIVE">Active</option>
                        <option value="CHECKED_IN">Checked In</option>
                        <option value="CHECKED_OUT">Checked Out</option>
                        <option value="REVOKED">Revoked</option>
                    </select>

                    <select wire:model.live="categoryFilter" class="py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                        <option value="">All Categories (SelectFilter)</option>
                        <option value="VISITOR">Visitor</option>
                        <option value="CONTRACTOR">Contractor</option>
                        <option value="HOMEOWNER">Homeowner</option>
                    </select>
                </div>

                <!-- Filament Table View -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                            <thead class="bg-slate-50 dark:bg-slate-800/50 uppercase font-semibold text-slate-500 border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th class="py-3 px-4">Pass ID (TextColumn)</th>
                                    <th class="py-3 px-4">Holder (TextColumn)</th>
                                    <th class="py-3 px-4">Property (TextColumn)</th>
                                    <th class="py-3 px-4">Status (BadgeColumn)</th>
                                    <th class="py-3 px-4">Gate</th>
                                    <th class="py-3 px-4 text-right">Filament Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse($passes as $p)
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                                        <td class="py-3 px-4 font-mono font-bold text-amber-600 dark:text-amber-400">
                                            {{ $p->pass_id }}
                                        </td>
                                        <td class="py-3 px-4 font-semibold text-slate-900 dark:text-white">
                                            {{ $p->holder_name }}
                                        </td>
                                        <td class="py-3 px-4 text-slate-500">
                                            {{ $p->property }}
                                        </td>
                                        <td class="py-3 px-4">
                                            @php $st = $p->status?->value ?? 'ACTIVE'; @endphp
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold
                                                {{ $st === 'ACTIVE' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' :
                                                   ($st === 'CHECKED_IN' ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300' :
                                                   ($st === 'REVOKED' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' : 'bg-slate-100 text-slate-700')) }}">
                                                {{ $p->status?->label() ?? $st }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-slate-500">
                                            {{ $p->designated_gate?->label() ?? 'Any' }}
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <div class="inline-flex items-center gap-1.5">
                                                <button type="button" wire:click="viewInfolist({{ $p->id }})" class="p-1 rounded text-slate-400 hover:text-slate-600 hover:bg-slate-100" title="View Infolist">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                </button>

                                                @if($st === 'ACTIVE')
                                                    <button type="button" wire:click="triggerAction('checkIn', {{ $p->id }})" class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 hover:bg-emerald-100">
                                                        Check In
                                                    </button>
                                                @elseif($st === 'CHECKED_IN')
                                                    <button type="button" wire:click="triggerAction('checkOut', {{ $p->id }})" class="px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-50 text-blue-700 hover:bg-blue-100">
                                                        Check Out
                                                    </button>
                                                @endif

                                                @if($st !== 'REVOKED')
                                                    <button type="button" wire:click="triggerAction('revoke', {{ $p->id }})" class="px-2 py-0.5 rounded text-[11px] font-semibold text-rose-600 hover:bg-rose-50">
                                                        Revoke
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="py-6 text-center text-slate-400">No records found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <!-- 3. AUTHORIZATION & POLICIES SIMULATOR -->
        @elseif($currentView === 'rbac')
            <div class="space-y-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Authorization & Laravel Policies Integration</h2>
                    <p class="text-xs text-slate-500">Filament integrates with Laravel Policies and verifies user role access before mounting panels.</p>
                </div>

                <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800 space-y-5">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Simulate User Persona</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Select Persona Role</label>
                            <select wire:model.live="simulatedRole" class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                                @foreach($userRoles as $role)
                                    <option value="{{ $role->value }}">{{ $role->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Account State</label>
                            <select wire:model.live="simulatedStatus" class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                                <option value="active">Active (Good Standing)</option>
                                <option value="deactivated">Deactivated (Suspended)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Evaluation Matrix -->
                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="p-4 rounded-xl border {{ $canAccessAdmin ? 'border-emerald-300 bg-emerald-50/50 dark:bg-emerald-950/20' : 'border-rose-300 bg-rose-50/50 dark:bg-rose-950/20' }} space-y-1">
                            <div class="text-xs font-bold uppercase text-slate-500">Admin Panel Access (/admin)</div>
                            <div class="text-lg font-black {{ $canAccessAdmin ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">
                                {{ $canAccessAdmin ? '✓ GRANTED' : '✗ DENIED' }}
                            </div>
                            <p class="text-[11px] text-slate-500">Requires SystemAdmin, Admin, or Security role in active status.</p>
                        </div>

                        <div class="p-4 rounded-xl border {{ $canAccessPortal ? 'border-emerald-300 bg-emerald-50/50 dark:bg-emerald-950/20' : 'border-rose-300 bg-rose-50/50 dark:bg-rose-950/20' }} space-y-1">
                            <div class="text-xs font-bold uppercase text-slate-500">Resident Portal Access (/portal)</div>
                            <div class="text-lg font-black {{ $canAccessPortal ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">
                                {{ $canAccessPortal ? '✓ GRANTED' : '✗ DENIED' }}
                            </div>
                            <p class="text-[11px] text-slate-500">Requires Homeowner or Temporary Homeowner role in active status.</p>
                        </div>
                    </div>
                </div>
            </div>

        <!-- 4. FILAMENT NOTIFICATIONS SANDBOX -->
        @elseif($currentView === 'notifications')
            <div class="space-y-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Filament Notifications System</h2>
                    <p class="text-xs text-slate-500">Standalone fluent notifications callable from anywhere in backend operations.</p>
                </div>

                <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800 space-y-4">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Dispatch Live Filament Notification</h3>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" wire:click="sendSampleNotification('success')" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm">
                            Success Notification
                        </button>
                        <button type="button" wire:click="sendSampleNotification('warning')" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 text-xs font-bold shadow-sm">
                            Warning Notification
                        </button>
                        <button type="button" wire:click="sendSampleNotification('error')" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-sm">
                            Danger Notification
                        </button>
                    </div>

                    <div class="p-4 rounded-xl bg-slate-900 text-slate-200 font-mono text-xs mt-4">
                        <code>
                            Notification::make()<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;->title('Visitor Cleared')<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;->body('Gate 01 barrier opened.')<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;->success()<br>
                            &nbsp;&nbsp;&nbsp;&nbsp;->send();
                        </code>
                    </div>
                </div>
            </div>

        <!-- 5. FILAMENT 5 PACKAGES MATRIX -->
        @elseif($currentView === 'architecture')
            <div class="space-y-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Current Filament 5 Modular Packages</h2>
                    <p class="text-xs text-slate-500">Filament 5 separates functionality into discrete, independently installable packages.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1">
                        <span class="font-mono text-xs font-bold text-amber-600">filament/actions</span>
                        <p class="text-xs text-slate-500">Modal actions, header buttons, slide-overs, and bulk actions.</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1">
                        <span class="font-mono text-xs font-bold text-amber-600">filament/forms</span>
                        <p class="text-xs text-slate-500">Reactive inputs, validation, wizard steps, tabs, and repeaters.</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1">
                        <span class="font-mono text-xs font-bold text-amber-600">filament/infolists</span>
                        <p class="text-xs text-slate-500">Read-only records display schemas, entries, and badges.</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1">
                        <span class="font-mono text-xs font-bold text-amber-600">filament/notifications</span>
                        <p class="text-xs text-slate-500">Toast notification dispatcher with session and livewire broadcasts.</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1">
                        <span class="font-mono text-xs font-bold text-amber-600">filament/schemas</span>
                        <p class="text-xs text-slate-500">Unified layout foundation for tabs, grids, and collapsible sections.</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1">
                        <span class="font-mono text-xs font-bold text-amber-600">filament/support</span>
                        <p class="text-xs text-slate-500">Shared icons, colors, blade utilities, and asset bundles.</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1">
                        <span class="font-mono text-xs font-bold text-amber-600">filament/tables</span>
                        <p class="text-xs text-slate-500">Sortable columns, search, pagination, and multi-filters.</p>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-1">
                        <span class="font-mono text-xs font-bold text-amber-600">filament/widgets</span>
                        <p class="text-xs text-slate-500">Dashboard metrics, StatsOverview, and chart components.</p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Create GatePass Modal (Filament Form Schema Simulation) -->
    <div x-data="{ open: @entangle('showCreateModal') }" x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div x-show="open" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" @click="$wire.closeCreateModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open" class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-900 text-left shadow-2xl sm:w-full sm:max-w-lg border border-slate-200 dark:border-slate-800">
                <form wire:submit.prevent="savePass">
                    <div class="px-6 pt-6 pb-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Filament Form Schema &middot; GatePassResource</h3>
                        <button type="button" wire:click="closeCreateModal" class="text-slate-400 hover:text-slate-600"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Holder / Visitor Full Name <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model="formHolderName" placeholder="e.g. Commander Shepard" class="w-full px-3.5 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Pass Category</label>
                                <select wire:model="formCategory" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                                    <option value="VISITOR">Visitor</option>
                                    <option value="CONTRACTOR">Contractor</option>
                                    <option value="HOMEOWNER">Homeowner</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Designated Gate</label>
                                <select wire:model="formGate" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                                    <option value="GATE-01">Gate 01 (Main)</option>
                                    <option value="GATE-02">Gate 02 (Service)</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Destination Property <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model="formProperty" placeholder="Lot 10, Palm Grove" class="w-full px-3.5 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white">
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-3">
                        <button type="button" wire:click="closeCreateModal" class="px-4 py-2 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 rounded-xl">Cancel</button>
                        <button type="submit" class="px-5 py-2 text-xs font-bold text-slate-950 bg-amber-500 hover:bg-amber-600 rounded-xl shadow-md">Create Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Infolist View Modal -->
    <div x-data="{ open: @entangle('showInfolistModal') }" x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div x-show="open" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" @click="$wire.closeInfolist()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="open" class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-900 text-left shadow-2xl sm:w-full sm:max-w-md border border-slate-200 dark:border-slate-800 p-6 space-y-4">
                @if($infolistRecord)
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                        <span class="text-xs font-bold uppercase text-amber-600">Filament Infolist Schema</span>
                        <button type="button" wire:click="closeInfolist" class="text-slate-400 hover:text-slate-600"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                    </div>
                    <div class="space-y-3 text-xs">
                        <div class="flex justify-between"><span class="text-slate-500">Security Token:</span> <span class="font-mono font-bold text-amber-600">{{ $infolistRecord->pass_id }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Authorized Holder:</span> <span class="font-bold text-slate-900 dark:text-white">{{ $infolistRecord->holder_name }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Destination:</span> <span>{{ $infolistRecord->property }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">State:</span> <span class="font-bold uppercase text-emerald-600">{{ $infolistRecord->status?->value ?? $infolistRecord->status }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Gate:</span> <span>{{ $infolistRecord->designated_gate?->label() ?? 'Any' }}</span></div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
