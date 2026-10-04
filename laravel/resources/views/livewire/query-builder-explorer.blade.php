<div class="w-full max-w-6xl mx-auto space-y-6">
    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900/90 backdrop-blur-md p-5 rounded-2xl border border-slate-800 shadow-xl">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                </span>
                <h2 class="text-xl font-bold text-white tracking-tight">API Query Builder Explorer</h2>
                <span class="text-xs px-2.5 py-0.5 rounded-full bg-slate-800 text-slate-400 border border-slate-700 font-mono">Spatie Query Builder</span>
            </div>
            <p class="text-xs text-slate-400 mt-1">Declarative filtering, sorting, relationship includes, sparse fieldsets, and pagination via query parameters.</p>
        </div>

        <!-- Mode Toggle & Doc Link -->
        <div class="flex items-center gap-2">
            <a href="/api/v1/query-meta" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition">
                <svg class="w-3.5 h-3.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                <span>Query Schema JSON</span>
            </a>
        </div>
    </div>

    <!-- Entity Navigation Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 border-b border-slate-800">
        <button wire:click="setEntity('gate_passes')" type="button" class="px-4 py-2 text-xs sm:text-sm font-bold rounded-xl transition flex items-center gap-2 {{ $entity === 'gate_passes' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
            <span>Gate Passes</span>
        </button>
        <button wire:click="setEntity('visitors')" type="button" class="px-4 py-2 text-xs sm:text-sm font-bold rounded-xl transition flex items-center gap-2 {{ $entity === 'visitors' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
            <span>Visitors</span>
        </button>
        <button wire:click="setEntity('transactions')" type="button" class="px-4 py-2 text-xs sm:text-sm font-bold rounded-xl transition flex items-center gap-2 {{ $entity === 'transactions' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
            <span>Transactions</span>
        </button>
        <button wire:click="setEntity('users')" type="button" class="px-4 py-2 text-xs sm:text-sm font-bold rounded-xl transition flex items-center gap-2 {{ $entity === 'users' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
            <span>Users & Residents</span>
        </button>
        <button wire:click="setEntity('warnings')" type="button" class="px-4 py-2 text-xs sm:text-sm font-bold rounded-xl transition flex items-center gap-2 {{ $entity === 'warnings' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
            <span>Warnings</span>
        </button>
    </div>

    <!-- Live Generated Query Preview Codebox -->
    <div class="bg-slate-950 p-3.5 rounded-xl border border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-inner">
        <div class="flex items-center gap-2 overflow-x-auto w-full">
            <span class="px-2 py-0.5 text-[11px] font-mono font-bold rounded bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">GET</span>
            <code class="text-xs font-mono text-indigo-300 break-all">{{ $queryStringPreview }}</code>
        </div>
        <span class="text-[11px] text-slate-500 whitespace-nowrap font-mono">Spatie Protocol</span>
    </div>

    <!-- Filter & Options Controls Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-slate-900/90 backdrop-blur-md p-4 rounded-2xl border border-slate-800 shadow-xl">
        <!-- Filter Keyword -->
        <div class="space-y-1.5">
            <label class="text-xs font-semibold text-slate-300">
                Filter by Keyword (?filter[{{ $entity === 'gate_passes' ? 'holder_name' : ($entity === 'visitors' ? 'name' : ($entity === 'transactions' ? 'purpose' : 'title')) }}])
            </label>
            <input
                wire:model.live.debounce.300ms="filterKeyword"
                type="text"
                placeholder="Type to filter..."
                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
            />
        </div>

        <!-- Sort Control -->
        <div class="space-y-1.5">
            <label class="text-xs font-semibold text-slate-300">Sort Direction (?sort=)</label>
            <select
                wire:model.live="sort"
                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500"
            >
                @foreach($currentMeta['allowed_sorts'] ?? [] as $s)
                    <option value="{{ $s }}">{{ $s }} (Ascending)</option>
                    <option value="-{{ $s }}">-{{ $s }} (Descending)</option>
                @endforeach
            </select>
        </div>

        <!-- Status Filter (if applicable) -->
        <div class="space-y-1.5">
            <label class="text-xs font-semibold text-slate-300">Status Filter (?filter[status]=)</label>
            <input
                wire:model.live.debounce.300ms="filterStatus"
                type="text"
                placeholder="e.g. ACTIVE, settled, checked_in..."
                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
            />
        </div>

        <!-- Allowed Includes Toggles -->
        @if(!empty($currentMeta['allowed_includes']))
            <div class="md:col-span-3 pt-2 border-t border-slate-800/80">
                <div class="text-xs font-semibold text-slate-300 mb-2">Eager Load Relationships (?include=)</div>
                <div class="flex flex-wrap items-center gap-2">
                    @foreach($currentMeta['allowed_includes'] as $inc)
                        <button
                            wire:click="toggleInclude('{{ $inc }}')"
                            type="button"
                            class="px-3 py-1 rounded-lg text-xs font-medium border transition {{ in_array($inc, $selectedIncludes, true) ? 'bg-indigo-600/30 text-indigo-300 border-indigo-500' : 'bg-slate-950 text-slate-400 border-slate-800 hover:text-white' }}"
                        >
                            + {{ $inc }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <!-- Query Results Panel -->
    <div class="bg-slate-900/90 backdrop-blur-md rounded-2xl border border-slate-800 shadow-xl overflow-hidden">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <h3 class="text-sm font-bold text-white">Live Query Output</h3>
                <span class="text-xs px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 border border-slate-700">
                    Total: {{ $results->total() }} records
                </span>
            </div>
            <div class="flex items-center gap-2">
                <button
                    wire:click="$set('viewMode', 'table')"
                    type="button"
                    class="px-2.5 py-1 text-xs rounded-lg transition {{ $viewMode === 'table' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white bg-slate-800' }}"
                >
                    Table
                </button>
                <button
                    wire:click="$set('viewMode', 'json')"
                    type="button"
                    class="px-2.5 py-1 text-xs rounded-lg transition {{ $viewMode === 'json' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white bg-slate-800' }}"
                >
                    JSON
                </button>
            </div>
        </div>

        @if($results->isEmpty())
            <div class="py-12 text-center text-slate-400 text-xs">
                No records matched the current Spatie Query Builder parameters.
            </div>
        @elseif($viewMode === 'json')
            <pre class="p-4 text-xs font-mono text-emerald-400 bg-slate-950 overflow-x-auto max-h-[450px]"><code>{{ json_encode($results->items(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-800 text-slate-400 bg-slate-950/40">
                            <th class="py-3 px-4 font-semibold">ID</th>
                            <th class="py-3 px-4 font-semibold">Primary Field</th>
                            <th class="py-3 px-4 font-semibold">Context / Status</th>
                            <th class="py-3 px-4 font-semibold">Created At</th>
                            <th class="py-3 px-4 font-semibold text-right">Includes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-300">
                        @foreach($results as $item)
                            <tr class="hover:bg-slate-800/30 transition">
                                <td class="py-3 px-4 font-mono text-slate-400">#{{ $item->id }}</td>
                                <td class="py-3 px-4 font-medium text-white">
                                    {{ $item->name ?? $item->holder_name ?? $item->transaction_id ?? $item->title ?? 'N/A' }}
                                </td>
                                <td class="py-3 px-4">
                                    @if(isset($item->status))
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-800 text-slate-300 border border-slate-700">
                                            {{ $item->status instanceof \BackedEnum ? $item->status->value : (string) $item->status }}
                                        </span>
                                    @elseif(isset($item->role))
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-800 text-slate-300 border border-slate-700">
                                            {{ $item->role instanceof \BackedEnum ? $item->role->value : (string) $item->role }}
                                        </span>
                                    @else
                                        <span class="text-slate-500">—</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 font-mono text-slate-400">
                                    {{ $item->created_at?->format('Y-m-d H:i') ?? 'N/A' }}
                                </td>
                                <td class="py-3 px-4 text-right">
                                    @foreach($selectedIncludes as $inc)
                                        @if($item->relationLoaded($inc) && $item->$inc)
                                            <span class="inline-block px-1.5 py-0.5 rounded bg-indigo-500/20 text-indigo-300 font-mono text-[10px]">
                                                {{ $inc }}: {{ $item->$inc->name ?? $item->$inc->id ?? 'loaded' }}
                                            </span>
                                        @endif
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <!-- Pagination Bar -->
        <div class="p-3 border-t border-slate-800 bg-slate-950/40 flex items-center justify-between text-xs text-slate-400">
            <span>Showing {{ $results->firstItem() ?? 0 }} to {{ $results->lastItem() ?? 0 }} of {{ $results->total() }}</span>
            <div class="flex items-center gap-1.5">
                {{ $results->links() }}
            </div>
        </div>
    </div>
</div>
