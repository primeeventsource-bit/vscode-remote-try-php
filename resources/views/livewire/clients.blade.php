<div class="p-5">
    <div class="mb-5">
        <h2 class="text-xl font-bold">Clients</h2>
        <p class="text-xs text-crm-t3 mt-1">Charged deals and client management</p>
    </div>

    {{-- Search + Status Tabs --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <input id="fld-search" wire:model.live.debounce.300ms="search" type="text" placeholder="Search clients..." class="flex-1 min-w-[200px] px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
        <div class="flex items-center gap-1 bg-crm-card border border-crm-border rounded-lg p-0.5">
            @foreach(['all' => 'All', 'charged' => 'Charged', 'cb' => 'CB', 'won' => 'Won', 'lost' => 'Lost'] as $key => $label)
                <button wire:click="$set('statusTab', '{{ $key }}')"
                    class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $statusTab === $key ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Revenue Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
        @php
            $chargedWon = $clients->filter(fn($c) => $c->charged === 'yes' && $c->charged_back !== 'yes');
            $cbLost = $clients->filter(fn($c) => $c->charged_back === 'yes' || $c->status === 'lost');
            $chargedTotal = $chargedWon->sum('fee');
            $cbTotal = $cbLost->sum('fee');
        @endphp
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-emerald-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Charged + Won</div>
            <div class="text-2xl font-extrabold text-emerald-500 mt-1">${{ number_format($chargedTotal) }}</div>
            <div class="text-[10px] text-crm-t3 mt-1">{{ $chargedWon->count() }} clients</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-red-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">CB + Lost</div>
            <div class="text-2xl font-extrabold text-red-500 mt-1">${{ number_format($cbTotal) }}</div>
            <div class="text-[10px] text-crm-t3 mt-1">{{ $cbLost->count() }} clients</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-blue-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Net Revenue</div>
            <div class="text-2xl font-extrabold text-blue-500 mt-1">${{ number_format($chargedTotal - $cbTotal) }}</div>
            <div class="text-[10px] text-crm-t3 mt-1">{{ $clients->count() }} total</div>
        </div>
        <div class="bg-crm-card border border-crm-border rounded-lg p-4 border-t-[3px] border-t-purple-500">
            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Avg Deal Size</div>
            <div class="text-2xl font-extrabold text-purple-500 mt-1">${{ $chargedWon->count() > 0 ? number_format($chargedTotal / $chargedWon->count()) : 0 }}</div>
        </div>
    </div>

    <div class="flex gap-4">
        {{-- Client List --}}
        <div class="flex-1">
            <div class="space-y-2">
                @forelse($clients as $client)
                    @php
                        $statusColor = match(true) {
                            $client->charged_back === 'yes' => ['bg-red-50 text-red-500', 'CB'],
                            $client->charged === 'yes' => ['bg-emerald-50 text-emerald-600', 'Charged'],
                            $client->status === 'won' => ['bg-blue-50 text-blue-600', 'Won'],
                            $client->status === 'lost' => ['bg-gray-100 text-gray-500', 'Lost'],
                            default => ['bg-amber-50 text-amber-600', 'Pending'],
                        };
                        $closer = $users->firstWhere('id', $client->closer);
                    @endphp
                    <div wire:click="selectClient({{ $client->id }})"
                         class="bg-crm-card border border-crm-border rounded-lg p-3 cursor-pointer transition {{ (isset($active) && $active && $active->id === $client->id) ? 'border-blue-400 bg-blue-50/50' : 'hover:bg-crm-hover' }}">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-[10px] font-bold text-white flex-shrink-0"
                                 style="background: {{ $closer->color ?? '#6b7280' }}">
                                {{ strtoupper(substr($client->owner_name, 0, 2)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold truncate">{{ $client->owner_name }}</span>
                                    <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded {{ $statusColor[0] }}">{{ $statusColor[1] }}</span>
                                </div>
                                <div class="text-[11px] text-crm-t3 truncate">{{ $client->resort_name }}</div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="text-sm font-bold font-mono text-emerald-500">${{ number_format($client->fee, 2) }}</div>
                                <div class="text-[10px] text-crm-t3">{{ $closer->name ?? '--' }}</div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="bg-crm-card border border-crm-border rounded-lg p-8 text-center">
                        <p class="text-sm text-crm-t3">No clients found</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Detail Panel --}}
        @if($active)
            <div class="w-96 flex-shrink-0 bg-crm-card border border-crm-border rounded-lg p-4 max-h-[75vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-sm font-bold">{{ $active->owner_name }}</h4>
                    <button wire:click="selectClient({{ $active->id }})" class="text-crm-t3 hover:text-crm-t1">&times;</button>
                </div>

                {{-- Contact Info --}}
                <div class="space-y-2 mb-4">
                    @foreach([
                        'Resort' => $active->resort_name,
                        'Fee' => '$'.number_format($active->fee, 2),
                        'Closer' => $users->firstWhere('id', $active->closer)?->name ?? '--',
                        'Fronter' => $users->firstWhere('id', $active->fronter)?->name ?? '--',
                        'Admin' => $users->firstWhere('id', $active->assigned_admin)?->name ?? '--',
                        'Email' => $active->email,
                        'Mailing Address' => $active->mailing_address,
                        'City/State/Zip' => $active->city_state_zip,
                        'Status' => ucfirst(str_replace('_', ' ', $active->status ?? 'pending')),
                        'Charged' => $active->charged === 'yes' ? 'Yes' : 'No',
                        'Charged Back' => $active->charged_back === 'yes' ? 'Yes' : 'No',
                        'Deal Date' => $active->timestamp?->format('n/j/Y') ?? '--',
                        'Charged Date' => $active->charged_date?->format('n/j/Y') ?? '--',
                    ] as $lbl => $val)
                        <div class="flex justify-between text-xs">
                            <span class="text-crm-t3">{{ $lbl }}</span>
                            <span class="font-semibold text-right">{{ $val ?: '--' }}</span>
                        </div>
                    @endforeach
                </div>

                {{-- Phones --}}
                <div class="border-t border-crm-border pt-3 mb-4">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-2 font-semibold">Phone Numbers</div>
                    @if($active->primary_phone)
                        <div class="mb-1" x-data="{ copied: false }">
                            <button type="button" @click="navigator.clipboard.writeText('{{ preg_replace('/[^0-9+]/', '', $active->primary_phone) }}'); copied = true; setTimeout(() => copied = false, 2000)" class="text-blue-600 font-semibold font-mono text-sm hover:underline cursor-pointer" title="Click to copy">📞 {{ $active->primary_phone }}</button>
                            <span class="text-[10px] text-crm-t3">Primary</span>
                            <span x-show="copied" x-cloak x-transition class="text-[9px] text-emerald-600 font-semibold">Copied!</span>
                        </div>
                    @endif
                    @if($active->secondary_phone)
                        <div x-data="{ copied: false }">
                            <button type="button" @click="navigator.clipboard.writeText('{{ preg_replace('/[^0-9+]/', '', $active->secondary_phone) }}'); copied = true; setTimeout(() => copied = false, 2000)" class="text-blue-600 font-semibold font-mono text-sm hover:underline cursor-pointer" title="Click to copy">📞 {{ $active->secondary_phone }}</button>
                            <span class="text-[10px] text-crm-t3">Secondary</span>
                            <span x-show="copied" x-cloak x-transition class="text-[9px] text-emerald-600 font-semibold">Copied!</span>
                        </div>
                    @endif
                </div>

                {{-- Timeshare --}}
                <div class="border-t border-crm-border pt-3 mb-4">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-2 font-semibold">Timeshare Details</div>
                    <div class="space-y-1">
                        @foreach([
                            'Resort City/State' => $active->resort_city_state,
                            'Weeks' => $active->weeks,
                            'Bed/Bath' => $active->bed_bath,
                            'Usage' => $active->usage,
                            'Exchange Group' => $active->exchange_group,
                        ] as $lbl => $val)
                            <div class="flex justify-between text-xs">
                                <span class="text-crm-t3">{{ $lbl }}</span>
                                <span class="font-semibold">{{ $val ?: '--' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Notes --}}
                @if($active->notes)
                    <div class="border-t border-crm-border pt-3 mb-4">
                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-2 font-semibold">Notes</div>
                        <div class="text-xs whitespace-pre-wrap bg-white border border-crm-border rounded p-2">{{ $active->notes }}</div>
                    </div>
                @endif

                {{-- File Upload Area --}}
                <div class="border-t border-crm-border pt-3">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-2 font-semibold">Files</div>
                    @if($active->files && count($active->files))
                        <div class="space-y-1 mb-2">
                            @foreach($active->files as $file)
                                <div class="flex items-center gap-2 text-xs bg-white border border-crm-border rounded p-1.5">
                                    <svg class="w-3.5 h-3.5 text-crm-t3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    <span class="truncate">{{ is_string($file) ? $file : ($file['name'] ?? 'File') }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <div class="border-2 border-dashed border-crm-border rounded-lg p-4 text-center hover:border-blue-400 transition cursor-pointer" wire:click="$dispatch('open-file-upload')">
                        <svg class="w-6 h-6 text-crm-t3 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        <p class="text-[10px] text-crm-t3">Click to upload files</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
