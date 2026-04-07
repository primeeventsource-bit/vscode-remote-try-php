{{-- AI Insight Drilldown Drawer —
     Opens when user clicks a row in At-Risk Deals / Hottest Leads.
     Listens for Alpine 'open-drilldown' event. --}}

<div x-data="{
        open: false,
        data: null,
        close() { this.open = false; this.data = null; }
     }"
     @open-drilldown.window="data = $event.detail; open = true"
     @keydown.escape.window="close()">

    {{-- Backdrop --}}
    <div x-show="open" x-transition.opacity @click="close()"
         class="fixed inset-0 bg-black/40 backdrop-blur-sm z-[9998]" style="display:none"></div>

    {{-- Drawer --}}
    <div x-show="open"
         x-transition:enter="transform transition ease-out duration-200"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transform transition ease-in duration-150"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         class="fixed top-0 right-0 h-full w-96 max-w-[90vw] bg-white border-l border-crm-border shadow-2xl z-[9999] flex flex-col"
         style="display:none">

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-crm-border bg-gradient-to-r from-indigo-600 to-blue-600 flex-shrink-0">
            <div>
                <h3 class="text-sm font-bold text-white">AI Insight Details</h3>
                <p class="text-[10px] text-white/70 mt-0.5" x-text="data?.type ? (data.type.charAt(0).toUpperCase() + data.type.slice(1) + ' #' + data.id) : ''"></p>
            </div>
            <button @click="close()" class="w-7 h-7 flex items-center justify-center rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition">&times;</button>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto p-5 space-y-5">

            {{-- Score / Probability --}}
            <div>
                <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold mb-2">Score / Probability</div>
                <div class="flex items-center gap-3">
                    <div class="text-3xl font-extrabold" :class="{
                        'text-emerald-500': data?.score >= 70,
                        'text-amber-500': data?.score >= 40 && data?.score < 70,
                        'text-red-500': data?.score < 40,
                    }" x-text="(data?.score ?? 0) + (data?.type === 'deal' ? '%' : '')"></div>
                    <div class="flex-1">
                        <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500"
                                 :class="{
                                     'bg-emerald-500': data?.score >= 70,
                                     'bg-amber-500': data?.score >= 40 && data?.score < 70,
                                     'bg-red-500': data?.score < 40,
                                 }"
                                 :style="`width: ${data?.score ?? 0}%`"></div>
                        </div>
                    </div>
                    <span class="text-[9px] font-bold px-2 py-0.5 rounded-full uppercase"
                          :class="{
                              'bg-emerald-100 text-emerald-600': ['hot','strong','very_strong'].includes(data?.label),
                              'bg-amber-100 text-amber-600': ['warm','medium','moderate'].includes(data?.label),
                              'bg-red-100 text-red-600': ['cold','weak','at_risk'].includes(data?.label),
                              'bg-gray-100 text-gray-500': !data?.label,
                          }"
                          x-text="data?.label ? data.label.replace('_',' ') : 'N/A'"></span>
                </div>
                <p class="text-[10px] text-crm-t3 mt-2">This score is based on engagement, data completeness, and recent activity.</p>
            </div>

            {{-- Key Reasons --}}
            <div x-show="data?.reasons?.length > 0">
                <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold mb-2">Key Reasons</div>
                <p class="text-[10px] text-crm-t3 mb-1.5">Top signals influencing this result.</p>
                <div class="space-y-1">
                    <template x-for="r in (data?.reasons || [])" :key="r">
                        <div class="flex items-center gap-2 text-[11px] text-emerald-600">
                            <span class="flex-shrink-0">+</span>
                            <span x-text="r"></span>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Risk Signals --}}
            <div x-show="data?.risks?.length > 0">
                <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold mb-2">Risk Factors</div>
                <p class="text-[10px] text-crm-t3 mb-1.5">What may reduce success.</p>
                <div class="space-y-1">
                    <template x-for="r in (data?.risks || [])" :key="r">
                        <div class="flex items-center gap-2 text-[11px] text-red-500">
                            <span class="flex-shrink-0">-</span>
                            <span x-text="r"></span>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Recommended Next Action --}}
            <div x-show="data?.nextAction">
                <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold mb-2">Recommended Next Action</div>
                <p class="text-[10px] text-crm-t3 mb-1.5">Best next step to improve outcome.</p>
                <div class="p-3 rounded-lg bg-blue-50 border border-blue-200">
                    <p class="text-xs text-blue-700 font-semibold" x-text="data?.nextAction"></p>
                </div>
            </div>

            {{-- Confidence --}}
            <div>
                <div class="text-[9px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Confidence Level</div>
                <p class="text-[10px] text-crm-t3">How reliable this prediction is.</p>
                <div class="flex items-center gap-2 mt-1">
                    <div class="h-1.5 flex-1 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-indigo-500 rounded-full" :style="`width: ${data?.confidence ?? 70}%`"></div>
                    </div>
                    <span class="text-[10px] font-bold text-indigo-600" x-text="(data?.confidence ?? 70) + '%'"></span>
                </div>
            </div>
        </div>

        {{-- Footer Actions --}}
        <div class="flex items-center gap-2 px-5 py-3 border-t border-crm-border bg-crm-surface flex-shrink-0">
            <a :href="data?.type === 'deal' ? '/deals' : '/leads'" class="flex-1 px-3 py-2 text-xs font-semibold text-center text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Open Record</a>
            <button @click="close()" class="px-3 py-2 text-xs font-semibold text-crm-t3 bg-gray-100 rounded-lg hover:bg-gray-200 transition">Dismiss</button>
        </div>
    </div>
</div>
