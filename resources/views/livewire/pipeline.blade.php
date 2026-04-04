<div class="p-5">
    <div class="mb-5">
        <h2 class="text-xl font-bold">Pipeline</h2>
        <p class="text-xs text-crm-t3 mt-1">
            @if($isAdmin)
                Deals requiring admin attention
            @else
                Your upcoming callbacks
            @endif
        </p>
    </div>

    @if($isAdmin)
        {{-- Admin: Pending Deals --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 mb-5">
            <div class="text-sm font-semibold mb-3">Pending Admin Review / In Verification</div>
            @if($pendingDeals->count())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-crm-border">
                                <th class="text-left px-4 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Owner</th>
                                <th class="text-left px-4 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Resort</th>
                                <th class="text-left px-4 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Fee</th>
                                <th class="text-left px-4 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Fronter</th>
                                <th class="text-left px-4 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Closer</th>
                                <th class="text-left px-4 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Status</th>
                                <th class="text-left px-4 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingDeals as $deal)
                                <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                                    <td class="px-4 py-2.5 font-semibold">{{ $deal->owner_name }}</td>
                                    <td class="px-4 py-2.5 text-crm-t2">{{ $deal->resort_name }}</td>
                                    <td class="px-4 py-2.5 font-mono font-bold text-emerald-500">${{ number_format($deal->fee, 2) }}</td>
                                    <td class="px-4 py-2.5 text-crm-t2">{{ $users->firstWhere('id', $deal->fronter)?->name ?? '--' }}</td>
                                    <td class="px-4 py-2.5 text-crm-t2">{{ $users->firstWhere('id', $deal->closer)?->name ?? '--' }}</td>
                                    <td class="px-4 py-2.5">
                                        @php
                                            $sColor = match($deal->status) {
                                                'pending_admin' => 'bg-amber-50 text-amber-600',
                                                'in_verification' => 'bg-blue-50 text-blue-600',
                                                default => 'bg-gray-50 text-gray-600',
                                            };
                                        @endphp
                                        <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded {{ $sColor }}">{{ str_replace('_', ' ', ucwords($deal->status, '_')) }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-crm-t3 text-xs">{{ $deal->timestamp?->format('n/j/Y') ?? '--' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-crm-t3 text-center py-6">No pending deals</p>
            @endif
        </div>
    @else
        {{-- Fronter/Closer: Callback Leads --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-sm font-semibold mb-3">Callback Leads</div>
            @if($callbackLeads->count())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-crm-border">
                                <th class="text-left px-4 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Owner Name</th>
                                <th class="text-left px-4 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Resort</th>
                                <th class="text-left px-4 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Phone 1</th>
                                <th class="text-left px-4 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Callback Date</th>
                                <th class="text-left px-4 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Disposition</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($callbackLeads as $lead)
                                @php
                                    $isPast = $lead->callback_date ?? null && $lead->callback_date ?? null->isPast();
                                @endphp
                                <tr class="border-b border-crm-border hover:bg-crm-hover transition {{ $isPast ? 'bg-red-50/50' : '' }}">
                                    <td class="px-4 py-2.5 font-semibold">{{ $lead->owner_name }}</td>
                                    <td class="px-4 py-2.5 text-crm-t2">{{ $lead->resort }}</td>
                                    <td class="px-4 py-2.5">
                                        @if($lead->phone1)
                                            <span x-data="{ copied: false }" class="inline-flex items-center gap-1">
                                                <button type="button" @click.stop="navigator.clipboard.writeText('{{ preg_replace('/[^0-9+]/', '', $lead->phone1) }}'); copied = true; setTimeout(() => copied = false, 2000)" class="text-blue-600 font-semibold font-mono hover:underline cursor-pointer" title="Click to copy">📞 {{ $lead->phone1 }}</button>
                                                <span x-show="copied" x-cloak x-transition class="text-[9px] text-emerald-600 font-semibold">Copied!</span>
                                            </span>
                                        @else
                                            <span class="text-crm-t3">--</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 font-mono text-xs {{ $isPast ? 'text-red-500 font-semibold' : 'text-crm-t2' }}">
                                        {{ $lead->callback_date?->format('n/j/Y g:i A') ?? '--' }}
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-amber-50 text-amber-600">{{ $lead->disposition }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-crm-t3 text-center py-6">No callbacks scheduled</p>
            @endif
        </div>
    @endif
</div>
