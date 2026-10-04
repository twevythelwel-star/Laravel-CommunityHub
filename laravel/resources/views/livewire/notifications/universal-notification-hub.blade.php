<div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-8">
    {{-- Header Banner --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 border border-indigo-500/20 shadow-2xl p-6 sm:p-8">
        <div class="absolute -right-16 -top-16 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -left-16 -bottom-16 w-64 h-64 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 text-xs font-semibold uppercase tracking-wider mb-3">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    Universal Multi-Channel Notifications
                </div>
                <h1 class="text-3xl sm:text-4xl font-black text-white tracking-tight">Notification Command Center</h1>
                <p class="mt-2 text-slate-300 text-sm sm:text-base max-w-2xl">
                    Enterprise multi-provider notification routing engine supporting Database, Email, SMS, WhatsApp, Push, Slack, Teams, Webhooks, and In-App real-time alerts.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <button wire:click="selectTab('dispatch')" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm shadow-lg shadow-indigo-600/30 transition-all">
                    Launch Dispatcher
                </button>
                <button wire:click="selectTab('inbox')" class="relative px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-bold text-sm transition-all">
                    Inbox
                    @if($unreadCount > 0)
                        <span class="absolute -top-1 -right-1 px-2 py-0.5 rounded-full text-xs bg-rose-500 text-white font-black shadow-md">
                            {{ $unreadCount }}
                        </span>
                    @endif
                </button>
            </div>
        </div>

        {{-- Hub Navigation Tabs --}}
        <div class="mt-8 flex flex-wrap gap-2 border-t border-slate-800/80 pt-4">
            <button wire:click="selectTab('catalog')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'catalog' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Channels & Providers
            </button>
            <button wire:click="selectTab('dispatch')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'dispatch' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Universal Dispatch Sandbox
            </button>
            <button wire:click="selectTab('inbox')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'inbox' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                In-App Inbox ({{ $unreadCount }} Unread)
            </button>
            <button wire:click="selectTab('deliveries')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'deliveries' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Delivery Telemetry Log
            </button>
        </div>
    </div>

    {{-- Feedback Alerts --}}
    @if ($feedbackMessage)
        <div class="p-4 rounded-2xl flex items-center justify-between border {{ $feedbackType === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' : 'bg-rose-500/10 border-rose-500/30 text-rose-300' }}">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span class="text-sm font-semibold">{{ $feedbackMessage }}</span>
            </div>
            <button wire:click="$set('feedbackMessage', null)" class="text-xs hover:underline opacity-80">Dismiss</button>
        </div>
    @endif

    {{-- Tab 1: Channels & Providers Catalog --}}
    @if ($activeTab === 'catalog')
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Supported Notification Channels</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">All channels are decoupled and can switch underlying vendor SDKs seamlessly.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($catalog as $key => $item)
                    <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between hover:border-indigo-500/50 transition-all">
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wider bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                                    {{ $item['key'] }}
                                </span>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $item['available'] ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $item['available'] ? 'bg-emerald-400' : 'bg-amber-400' }}"></span>
                                    {{ ucfirst($item['status']) }}
                                </span>
                            </div>

                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ $item['label'] }}</h3>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                Active Provider: <span class="font-mono font-bold text-slate-700 dark:text-slate-200">{{ strtoupper($item['active_provider']) }}</span>
                            </p>
                        </div>

                        {{-- Provider Swapping Controls for Multi-Provider Channels --}}
                        <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800/80">
                            @if ($key === 'email')
                                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Switch Email Provider</label>
                                <select wire:change="updateProvider('email', $event.target.value)" class="w-full text-xs font-semibold rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                                    <option value="mailgun" @selected($channelProviders['email'] === 'mailgun')>Mailgun (API v3)</option>
                                    <option value="sendgrid" @selected($channelProviders['email'] === 'sendgrid')>SendGrid (Web API v3)</option>
                                    <option value="postmark" @selected($channelProviders['email'] === 'postmark')>Postmark Server API</option>
                                    <option value="ses" @selected($channelProviders['email'] === 'ses')>Amazon SES</option>
                                    <option value="laravel" @selected($channelProviders['email'] === 'laravel')>Laravel Default Mailer</option>
                                </select>
                            @elseif ($key === 'push')
                                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Switch Push Provider</label>
                                <select wire:change="updateProvider('push', $event.target.value)" class="w-full text-xs font-semibold rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                                    <option value="firebase" @selected($channelProviders['push'] === 'firebase')>Firebase Cloud Messaging (FCM)</option>
                                    <option value="onesignal" @selected($channelProviders['push'] === 'onesignal')>OneSignal REST API</option>
                                </select>
                            @elseif ($key === 'in_app')
                                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Switch Realtime Broadcast</label>
                                <select wire:change="updateProvider('in_app', $event.target.value)" class="w-full text-xs font-semibold rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200">
                                    <option value="pusher" @selected(($channelProviders['in_app'] ?? '') === 'pusher')>Pusher Channels</option>
                                    <option value="ably" @selected(($channelProviders['in_app'] ?? '') === 'ably')>Ably Realtime</option>
                                </select>
                            @else
                                <div class="flex items-center justify-between text-xs text-slate-400">
                                    <span>Adapter Strategy:</span>
                                    <span class="font-bold text-indigo-400 uppercase">Direct Driver</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Tab 2: Universal Dispatch Sandbox --}}
    @if ($activeTab === 'dispatch')
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            {{-- Form Column --}}
            <div class="lg:col-span-7 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 sm:p-8 shadow-sm space-y-6">
                <div>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Multi-Channel Dispatcher</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Select any combination of channels to broadcast simultaneously.</p>
                </div>

                {{-- Channel Selector Chips --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Destination Channels</label>
                        <button type="button" wire:click="selectAllChannels" class="text-xs text-indigo-500 hover:text-indigo-400 font-bold">Select All</button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['in_app', 'email', 'sms', 'whatsapp', 'push', 'slack', 'teams', 'webhook', 'database'] as $ch)
                            <button
                                type="button"
                                wire:click="toggleChannel('{{ $ch }}')"
                                class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all border {{ in_array($ch, $selectedChannels, true) ? 'bg-indigo-600 border-indigo-500 text-white shadow-md' : 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-slate-400' }}"
                            >
                                {{ ucfirst($ch) }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Recipient Information --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Recipient Name</label>
                        <input type="text" wire:model="recipientName" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Recipient Email</label>
                        <input type="email" wire:model="recipientEmail" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Phone (SMS / WhatsApp)</label>
                        <input type="text" wire:model="recipientPhone" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Webhook URL</label>
                        <input type="url" wire:model="webhookUrl" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white">
                    </div>
                </div>

                {{-- Notification Content --}}
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Notification Title</label>
                        <input type="text" wire:model="title" class="w-full text-sm font-semibold rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Message Body</label>
                        <textarea rows="3" wire:model="body" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white"></textarea>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Priority</label>
                            <select wire:model="priority" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white">
                                <option value="low">Low</option>
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Category</label>
                            <select wire:model="category" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white">
                                <option value="general">General</option>
                                <option value="security">Security</option>
                                <option value="billing">Billing</option>
                                <option value="pass">Visitor Pass</option>
                                <option value="system">System</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 dark:text-slate-400 mb-1">Action URL</label>
                            <input type="text" wire:model="actionUrl" class="w-full text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white">
                        </div>
                    </div>
                </div>

                <button wire:click="triggerDispatch" class="w-full py-3 rounded-xl bg-gradient-to-r from-indigo-600 to-cyan-600 hover:from-indigo-500 hover:to-cyan-500 text-white font-black text-sm shadow-xl shadow-indigo-600/30 transition-all">
                    Broadcast Notification Now
                </button>
            </div>

            {{-- Live Results Column --}}
            <div class="lg:col-span-5 space-y-6">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 sm:p-8 shadow-sm">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Dispatch Execution Output</h3>

                    @if ($lastDispatchResult)
                        <div class="space-y-4">
                            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700">
                                <div class="text-xs text-slate-400">Tracking Reference</div>
                                <div class="font-mono text-xs font-bold text-indigo-400">{{ $lastDispatchResult['tracking_id'] }}</div>
                            </div>

                            <div class="space-y-2">
                                <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Per-Channel Statuses</div>
                                @foreach ($lastDispatchResult['reports'] as $ch => $report)
                                    <div class="p-3 rounded-xl border {{ $report['status'] === 'delivered' ? 'bg-emerald-500/5 border-emerald-500/20' : ($report['status'] === 'skipped' ? 'bg-amber-500/5 border-amber-500/20' : 'bg-rose-500/5 border-rose-500/20') }} flex items-center justify-between">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-bold uppercase text-slate-900 dark:text-white">{{ $ch }}</span>
                                                <span class="text-[10px] px-1.5 py-0.5 rounded font-mono bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-300">{{ $report['provider'] }}</span>
                                            </div>
                                            <div class="text-[11px] font-mono text-slate-400 truncate max-w-[220px]">
                                                ID: {{ $report['message_id'] ?? 'none' }}
                                            </div>
                                        </div>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase {{ $report['status'] === 'delivered' ? 'bg-emerald-500/20 text-emerald-400' : ($report['status'] === 'skipped' ? 'bg-amber-500/20 text-amber-400' : 'bg-rose-500/20 text-rose-400') }}">
                                            {{ $report['status'] }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="text-center py-12 text-slate-400 text-xs">
                            Select channels and trigger a dispatch to view real-time delivery telemetry.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Tab 3: In-App Inbox --}}
    @if ($activeTab === 'inbox')
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 sm:p-8 shadow-sm space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Resident In-App Notification Feed</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Notifications delivered via In-App and Database channels.</p>
                </div>
                @if ($unreadCount > 0)
                    <button wire:click="markAllAsRead" class="px-4 py-2 rounded-xl bg-indigo-50 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400 font-bold text-xs border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 transition-all">
                        Mark All Read ({{ $unreadCount }})
                    </button>
                @endif
            </div>

            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($inboxItems as $item)
                    <div class="py-4 flex items-start justify-between gap-4 {{ is_null($item->read_at) ? 'bg-indigo-500/5 -mx-4 px-4 rounded-xl' : '' }}">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                    {{ $item->category }}
                                </span>
                                @if (is_null($item->read_at))
                                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                                @endif
                                <span class="text-xs font-bold text-slate-900 dark:text-white">{{ $item->title }}</span>
                            </div>
                            <p class="text-xs text-slate-600 dark:text-slate-300">{{ $item->body }}</p>
                            <div class="flex items-center gap-4 text-[10px] text-slate-400">
                                <span>{{ $item->created_at->diffForHumans() }}</span>
                                @if ($item->action_url)
                                    <a href="{{ $item->action_url }}" target="_blank" class="text-indigo-400 hover:underline">Open Link &rarr;</a>
                                @endif
                            </div>
                        </div>

                        <div>
                            @if (is_null($item->read_at))
                                <button wire:click="markAsRead({{ $item->id }})" class="text-xs font-semibold text-slate-400 hover:text-indigo-400 whitespace-nowrap">
                                    Mark Read
                                </button>
                            @else
                                <span class="text-[10px] font-mono text-slate-500">Read</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12 text-slate-400 text-xs">
                        No in-app notifications found.
                    </div>
                @endforelse
            </div>

            <div class="pt-4">
                {{ $inboxItems->links() }}
            </div>
        </div>
    @endif

    {{-- Tab 4: Delivery Telemetry Log --}}
    @if ($activeTab === 'deliveries')
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 sm:p-8 shadow-sm space-y-6">
            <div>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">Delivery Audit Logs</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Comprehensive delivery telemetry tracked in `notification_deliveries`.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-400">
                            <th class="py-3 px-3 font-semibold uppercase">Channel</th>
                            <th class="py-3 px-3 font-semibold uppercase">Provider</th>
                            <th class="py-3 px-3 font-semibold uppercase">Recipient</th>
                            <th class="py-3 px-3 font-semibold uppercase">Status</th>
                            <th class="py-3 px-3 font-semibold uppercase">Provider Message ID</th>
                            <th class="py-3 px-3 font-semibold uppercase">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($deliveries as $del)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="py-3 px-3 font-bold uppercase text-slate-800 dark:text-slate-200">{{ $del->channel }}</td>
                                <td class="py-3 px-3 font-mono text-slate-600 dark:text-slate-400">{{ $del->provider }}</td>
                                <td class="py-3 px-3 font-mono text-slate-600 dark:text-slate-400">{{ $del->recipient }}</td>
                                <td class="py-3 px-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $del->status === 'delivered' ? 'bg-emerald-500/10 text-emerald-400' : ($del->status === 'queued' ? 'bg-blue-500/10 text-blue-400' : 'bg-rose-500/10 text-rose-400') }}">
                                        {{ $del->status }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 font-mono text-slate-500 truncate max-w-[150px]">{{ $del->provider_message_id }}</td>
                                <td class="py-3 px-3 text-slate-400 whitespace-nowrap">{{ $del->created_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400">No delivery logs recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pt-4">
                {{ $deliveries->links() }}
            </div>
        </div>
    @endif
</div>
