<div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-8 animate-fade-in font-sans">
    {{-- Header Banner / Hero Capsule --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-rose-950 to-slate-900 p-6 sm:p-8 border border-slate-800 shadow-2xl text-white">
        <div class="absolute -right-16 -top-16 w-72 h-72 bg-rose-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute right-32 -bottom-20 w-80 h-80 bg-red-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-rose-500/20 text-rose-300 border border-rose-500/30 mb-3">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-400 animate-ping"></span>
                    Universal Enterprise PDF Engine
                </div>
                <h1 class="text-3xl sm:text-4xl font-black text-white tracking-tight">Enterprise PDF Generation Architecture</h1>
                <p class="mt-2 text-slate-300 text-sm sm:text-base max-w-2xl">
                    High-fidelity statutory document production supporting Barryvdh DOMPDF and Spatie multi-engine rendering across all 8 enterprise use cases.
                </p>
            </div>

            {{-- Telemetry Capsule --}}
            <div class="p-4 rounded-2xl bg-slate-900/90 border border-slate-800 flex flex-wrap gap-4 text-xs font-mono">
                <div>
                    <div class="text-slate-500 uppercase text-[10px]">Active Driver</div>
                    <div class="font-bold text-rose-400 uppercase">{{ $activeDriver['name'] }}</div>
                </div>
                <div class="border-l border-slate-800 pl-4">
                    <div class="text-slate-500 uppercase text-[10px]">Framework Scope</div>
                    <div class="font-bold text-emerald-400">Laravel 9 - 13</div>
                </div>
                <div class="border-l border-slate-800 pl-4">
                    <div class="text-slate-500 uppercase text-[10px]">Use Cases</div>
                    <div class="font-bold text-slate-200">{{ $totalDocuments }} Core Types</div>
                </div>
                <div class="border-l border-slate-800 pl-4">
                    <div class="text-slate-500 uppercase text-[10px]">Rendering Stack</div>
                    <div class="font-bold text-amber-300">DOMPDF / Spatie</div>
                </div>
            </div>
        </div>

        {{-- Navigation Tabs --}}
        <div class="mt-8 flex flex-wrap gap-2 border-t border-slate-800/80 pt-4">
            <button wire:click="selectTab('catalog')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'catalog' ? 'bg-rose-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Document Showcase (8 Use Cases)
            </button>
            <button wire:click="selectTab('drivers')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'drivers' ? 'bg-rose-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Engine Comparison &amp; Architecture
            </button>
            <button wire:click="selectTab('generator')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'generator' ? 'bg-rose-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Custom Parameter Generator
            </button>
            <button wire:click="selectTab('specifications')" class="px-4 py-2 rounded-xl text-xs font-bold transition-all {{ $activeTab === 'specifications' ? 'bg-rose-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                Compliance &amp; Print Specifications
            </button>
        </div>
    </div>

    {{-- Feedback Flash --}}
    @if ($feedbackMessage)
        <div class="p-4 rounded-2xl flex items-center justify-between border bg-rose-500/10 border-rose-500/30 text-rose-300 text-sm font-semibold animate-fade-in">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>{{ $feedbackMessage }}</span>
            </div>
            <button wire:click="$set('feedbackMessage', null)" class="text-xs text-slate-400 hover:text-white font-mono uppercase">Dismiss</button>
        </div>
    @endif

    {{-- TAB 1: Document Showcase --}}
    @if ($activeTab === 'catalog')
        <div class="space-y-6">
            {{-- Controls Toolbar --}}
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-slate-900/80 p-4 rounded-2xl border border-slate-800">
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Orientation:</span>
                    <button wire:click="$set('filterOrientation', 'all')" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $filterOrientation === 'all' ? 'bg-rose-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">
                        All ({{ count($catalog) }})
                    </button>
                    <button wire:click="$set('filterOrientation', 'portrait')" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $filterOrientation === 'portrait' ? 'bg-rose-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">
                        Portrait
                    </button>
                    <button wire:click="$set('filterOrientation', 'landscape')" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $filterOrientation === 'landscape' ? 'bg-rose-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">
                        Landscape
                    </button>
                </div>

                <div class="w-full sm:w-72 relative">
                    <input type="text" wire:model.live.debounce.250ms="search" placeholder="Search templates, use cases..." class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-rose-500 transition-colors">
                    @if($search)
                        <button wire:click="$set('search', '')" class="absolute right-3 top-2.5 text-xs text-slate-500 hover:text-white">✕</button>
                    @endif
                </div>
            </div>

            {{-- Document Cards Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                @forelse ($catalog as $key => $doc)
                    <div class="group relative rounded-2xl border bg-slate-900/60 p-5 flex flex-col justify-between transition-all duration-200 hover:-translate-y-1 hover:shadow-xl border-slate-800 hover:border-rose-500/50">
                        <div>
                            {{-- Header Capsule --}}
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <span class="px-2.5 py-0.5 rounded-md text-[10px] font-mono font-bold uppercase tracking-wider bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                    {{ $doc['paper_size'] }} • {{ $doc['orientation'] }}
                                </span>
                                <span class="text-[10px] text-slate-500 font-mono">
                                    {{ !empty($doc['security_hash']) ? 'SHA-256' : 'Standard' }}
                                </span>
                            </div>

                            <h3 class="text-base font-bold text-white group-hover:text-rose-400 transition-colors">
                                {{ $doc['name'] }}
                            </h3>
                            <p class="mt-1 text-xs text-slate-400 line-clamp-2">
                                {{ $doc['description'] }}
                            </p>

                            {{-- Metadata Badges --}}
                            <div class="mt-4 space-y-1.5 pt-3 border-t border-slate-800/80 text-[11px]">
                                <div class="flex items-center justify-between text-slate-400">
                                    <span>Target Entity:</span>
                                    <span class="font-medium text-slate-200">{{ $doc['target_entity'] ?? 'Community Member' }}</span>
                                </div>
                                <div class="flex items-center justify-between text-slate-400">
                                    <span>Legal Scope:</span>
                                    <span class="font-medium text-emerald-400">{{ $doc['legal_validity'] ?? 'Statutory Enforceable' }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="mt-5 pt-3 border-t border-slate-800 flex items-center gap-2">
                            <form method="POST" action="{{ route('api.v1.pdf.preview', $key) }}" target="_blank" class="flex-1">
                                @csrf
                                <button type="submit" class="w-full py-2 px-3 rounded-xl text-xs font-bold bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white transition-all text-center flex items-center justify-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Preview
                                </button>
                            </form>

                            <button wire:click="downloadDocument('{{ $key }}')" wire:loading.attr="disabled" class="flex-1 py-2 px-3 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-500 text-white transition-all text-center flex items-center justify-center gap-1.5 shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Download
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full p-8 text-center bg-slate-900/40 rounded-2xl border border-slate-800 text-slate-500">
                        No documents match the current criteria.
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    {{-- TAB 2: Drivers & Architecture Comparison --}}
    @if ($activeTab === 'drivers')
        <div class="space-y-6">
            <div class="bg-slate-900/60 p-6 rounded-3xl border border-slate-800 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-white">Rendering Architecture &amp; Driver Ecosystem</h2>
                        <p class="text-xs text-slate-400 mt-1">
                            Enterprise analysis of Barryvdh DOMPDF versus Spatie Laravel PDF multi-engine options across infrastructure complexity, styling fidelity, and throughput.
                        </p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-mono font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                        Active: {{ $activeDriver['name'] }}
                    </span>
                </div>

                {{-- Driver Comparison Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 pt-4">
                    @foreach ($drivers as $dKey => $driver)
                        <div class="p-5 rounded-2xl border bg-slate-950/80 flex flex-col justify-between {{ $driver['is_installed'] ? 'border-rose-500/60 shadow-lg' : 'border-slate-800' }}">
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-base font-bold text-white">{{ $driver['name'] }}</h3>
                                    @if ($driver['is_installed'])
                                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40">
                                            INSTALLED / ACTIVE
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-800 text-slate-400">
                                            AVAILABLE
                                        </span>
                                    @endif
                                </div>

                                <p class="text-xs text-slate-400 leading-relaxed mb-4">
                                    {{ $driver['description'] }}
                                </p>

                                <div class="space-y-2 text-xs border-t border-slate-800 pt-3">
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Engine Type:</span>
                                        <span class="font-mono text-slate-300">{{ $driver['engine'] }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Laravel Support:</span>
                                        <span class="font-semibold text-rose-300">{{ $driver['laravel_support'] }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">External Node/Binary:</span>
                                        <span class="font-mono {{ $driver['requires_headless_binary'] ? 'text-amber-400 font-bold' : 'text-emerald-400' }}">
                                            {{ $driver['requires_headless_binary'] ? 'Required (Chromium/Daemon)' : 'None (Zero Dependency)' }}
                                        </span>
                                    </div>
                                    <div class="pt-2 border-t border-slate-800/60">
                                        <span class="text-slate-500 text-[11px] block mb-1">Key Strengths:</span>
                                        <p class="text-[11px] text-slate-300 leading-snug">{{ $driver['strengths'] }}</p>
                                    </div>
                                    <div class="pt-1">
                                        <span class="text-slate-500 text-[11px] block mb-1">Trade-offs:</span>
                                        <p class="text-[11px] text-slate-400 leading-snug">{{ $driver['tradeoffs'] }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 pt-3 border-t border-slate-800 text-[11px] font-mono text-slate-400">
                                Recommended for: <span class="text-slate-200">{{ $driver['ideal_for'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- TAB 3: Custom Parameter Generator --}}
    @if ($activeTab === 'generator')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-1 bg-slate-900/60 p-6 rounded-3xl border border-slate-800 space-y-6">
                <div>
                    <h2 class="text-lg font-bold text-white">Dynamic Generation Studio</h2>
                    <p class="text-xs text-slate-400 mt-1">
                        Configure runtime attributes to test layout responsiveness and data binding.
                    </p>
                </div>

                <div class="space-y-4 text-xs">
                    <div>
                        <label class="block text-slate-400 font-semibold mb-1">Document Blueprint</label>
                        <select wire:model.live="customType" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-rose-500">
                            @foreach ($catalog as $key => $doc)
                                <option value="{{ $key }}">{{ $doc['name'] }} ({{ $doc['paper_size'] }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-slate-400 font-semibold mb-1">Recipient / Resident Full Name</label>
                        <input type="text" wire:model.live="recipientName" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-rose-500">
                    </div>

                    <div>
                        <label class="block text-slate-400 font-semibold mb-1">Reference / Document Serial</label>
                        <input type="text" wire:model.live="referenceNumber" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-rose-500">
                    </div>

                    <div>
                        <label class="block text-slate-400 font-semibold mb-1">Amount / Valuation (USD)</label>
                        <input type="number" step="0.01" wire:model.live="amount" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-rose-500">
                    </div>

                    <div>
                        <label class="block text-slate-400 font-semibold mb-1">Audit Endorsement Notes</label>
                        <textarea rows="3" wire:model.live="notes" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-rose-500"></textarea>
                    </div>

                    <button wire:click="downloadCustom" wire:loading.attr="disabled" class="w-full py-3 rounded-xl font-bold bg-rose-600 hover:bg-rose-500 text-white transition-all shadow-lg flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Render &amp; Download Custom PDF
                    </button>
                </div>
            </div>

            <div class="lg:col-span-2 bg-slate-900/60 p-6 rounded-3xl border border-slate-800 flex flex-col justify-between">
                <div>
                    <h3 class="text-base font-bold text-white mb-2">Live Parameter Manifest</h3>
                    <p class="text-xs text-slate-400 mb-4">Real-time JSON payload sent to the DOMPDF rendering pipe.</p>

                    <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800 font-mono text-xs text-rose-300 overflow-x-auto">
                        <pre>{
  "document_type": "{{ $customType }}",
  "active_driver": "Barryvdh\\DomPDF",
  "paper_geometry": {
    "size": "{{ $catalog[$customType]['paper_size'] ?? 'a4' }}",
    "orientation": "{{ $catalog[$customType]['orientation'] ?? 'portrait' }}"
  },
  "runtime_overrides": {
    "reference_no": "{{ $referenceNumber }}",
    "recipient": {
      "name": "{{ $recipientName }}",
      "lot": "Lot 402 - Cedar Ridge"
    },
    "total_amount": {{ (float) $amount }},
    "notes": "{{ $notes }}"
  },
  "timestamp": "{{ date('c') }}"
}</pre>
                    </div>

                    <div class="mt-6 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-xs text-slate-300">
                        <div class="font-bold text-rose-400 mb-1">Queue Integration Guarantee</div>
                        All 8 document blueprints can be dispatched to the background processing queue via <code class="text-white bg-slate-900 px-1 py-0.5 rounded">GeneratePdfReportJob</code>, integrated into Laravel Horizon for high-volume batch statements and monthly invoice runs.
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-800 flex items-center justify-between text-xs text-slate-500 font-mono">
                    <span>DOMPDF CSS3 Compliant</span>
                    <span>UTF-8 Multilingual Safe</span>
                </div>
            </div>
        </div>
    @endif

    {{-- TAB 4: Compliance & Print Specifications --}}
    @if ($activeTab === 'specifications')
        <div class="bg-slate-900/60 p-6 rounded-3xl border border-slate-800 space-y-6">
            <div>
                <h2 class="text-lg font-bold text-white">Enterprise Statutory Document Standards</h2>
                <p class="text-xs text-slate-400 mt-1">
                    System-wide styling rules, paper geometry, and cryptographic standards enforced across all generated PDFs.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-800 text-slate-400 uppercase text-[10px] tracking-wider font-mono">
                            <th class="py-3 px-4">Document Type</th>
                            <th class="py-3 px-4">Paper Format</th>
                            <th class="py-3 px-4">Orientation</th>
                            <th class="py-3 px-4">Margins</th>
                            <th class="py-3 px-4">Security Features</th>
                            <th class="py-3 px-4">Legal Validity</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-300">
                        @foreach ($catalog as $key => $doc)
                            <tr class="hover:bg-slate-800/30 transition-colors">
                                <td class="py-3 px-4 font-bold text-white">
                                    {{ $doc['name'] }}
                                    <div class="text-[10px] text-slate-500 font-mono font-normal">{{ $key }}</div>
                                </td>
                                <td class="py-3 px-4 font-mono uppercase">{{ $doc['paper_size'] }}</td>
                                <td class="py-3 px-4 font-mono capitalize">{{ $doc['orientation'] }}</td>
                                <td class="py-3 px-4 font-mono text-slate-400">10mm - 18mm</td>
                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-mono bg-slate-800 text-slate-300">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        SHA-256 Audit Trail
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-emerald-400 font-medium">{{ $doc['legal_validity'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
