<div class="bg-slate-900 border border-slate-800 rounded-xl p-4 text-white shadow-lg">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
            <span class="relative flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
            </span>
            <span class="text-sm font-semibold tracking-wide text-slate-200 uppercase">{{ $communityName }}</span>
        </div>
        <button wire:click="refreshStats" type="button" class="text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 px-2.5 py-1 rounded transition border border-slate-700">
            Refresh
        </button>
    </div>
    <div class="grid grid-cols-2 gap-3 text-center">
        <div class="bg-slate-800/60 rounded-lg p-2.5 border border-slate-700/50">
            <div class="text-xl font-bold text-emerald-400">{{ $activePassesCount }}</div>
            <div class="text-xs text-slate-400">Active Passes</div>
        </div>
        <div class="bg-slate-800/60 rounded-lg p-2.5 border border-slate-700/50">
            <div class="text-xl font-bold {{ $activeAlertsCount > 0 ? 'text-amber-400' : 'text-slate-300' }}">{{ $activeAlertsCount }}</div>
            <div class="text-xs text-slate-400">Active Alerts</div>
        </div>
    </div>
</div>
