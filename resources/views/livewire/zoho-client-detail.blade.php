<div class="p-5">
    <div class="mb-5">
        <a href="/zoho-clients" class="text-xs text-blue-500 hover:underline">&larr; Back to Zoho Clients</a>
    </div>

    @if($client)
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">{{ $client->first_name }} {{ $client->last_name }}</h2>
            <p class="text-xs text-crm-t3 mt-1">{{ $client->account_name ?? 'No Company' }} &middot; {{ $client->title ?? '' }} &middot; Zoho ID: {{ $client->zoho_id ?? '--' }}</p>
        </div>
        <span class="px-2 py-0.5 text-[9px] font-bold rounded-full {{ $client->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">{{ ucfirst($client->status) }}</span>
    </div>

    {{-- Contact Info --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-xs font-bold mb-3">Contact Information</div>
            <div class="space-y-2 text-xs">
                @foreach([
                    'Email' => $client->email,
                    'Phone' => $client->phone,
                    'Mobile' => $client->mobile,
                    'Title' => $client->title,
                    'Department' => $client->department,
                    'Lead Source' => $client->lead_source,
                    'Contact Owner' => $client->contact_owner,
                ] as $label => $val)
                    <div class="flex justify-between">
                        <span class="text-crm-t3">{{ $label }}</span>
                        <span class="font-semibold text-right">{{ $val ?: '--' }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-xs font-bold mb-3">Mailing Address</div>
            <div class="space-y-2 text-xs">
                @foreach([
                    'Street' => $client->mailing_address,
                    'City' => $client->mailing_city,
                    'State' => $client->mailing_state,
                    'Zip' => $client->mailing_zip,
                    'Country' => $client->mailing_country,
                ] as $label => $val)
                    <div class="flex justify-between">
                        <span class="text-crm-t3">{{ $label }}</span>
                        <span class="font-semibold text-right">{{ $val ?: '--' }}</span>
                    </div>
                @endforeach
                <div class="flex justify-between pt-2 border-t border-crm-border">
                    <span class="text-crm-t3">Last Synced</span>
                    <span class="font-semibold">{{ $client->last_synced_at?->format('M j, Y g:ia') ?? 'Never' }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Deals --}}
    @if($deals->isNotEmpty())
    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden mb-6">
        <div class="p-4 border-b border-crm-border">
            <div class="text-xs font-bold">Zoho Deals ({{ $deals->count() }})</div>
        </div>
        <table class="w-full text-xs">
            <thead>
                <tr class="bg-crm-surface border-b border-crm-border">
                    <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Deal</th>
                    <th class="text-right px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Amount</th>
                    <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Stage</th>
                    <th class="text-left px-3 py-2 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Close Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($deals as $deal)
                <tr class="border-b border-crm-border hover:bg-crm-hover">
                    <td class="px-3 py-2 font-semibold">{{ $deal->deal_name ?? '--' }}</td>
                    <td class="px-3 py-2 text-right font-mono font-bold text-emerald-600">${{ number_format($deal->amount ?? 0, 2) }}</td>
                    <td class="px-3 py-2 text-crm-t3">{{ $deal->stage ?? '--' }}</td>
                    <td class="px-3 py-2 text-crm-t3">{{ $deal->closing_date?->format('n/j/Y') ?? '--' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Internal Notes --}}
    <div class="bg-crm-card border border-crm-border rounded-lg p-4 mb-6">
        <div class="text-xs font-bold mb-3">Notes</div>
        @foreach($clientNotes as $note)
            <div class="p-2 mb-2 bg-crm-surface rounded-lg">
                <div class="text-xs">{{ $note->body }}</div>
                <div class="text-[10px] text-crm-t3 mt-1">{{ $users[$note->user_id]->name ?? 'System' }} &middot; {{ $note->created_at->diffForHumans() }}</div>
            </div>
        @endforeach
        @if($clientNotes->isEmpty())
            <p class="text-xs text-crm-t3 mb-3">No notes yet.</p>
        @endif
        <div class="flex gap-2 mt-2">
            <input id="fld-zoho-note" type="text" wire:model="noteBody" placeholder="Add a note..."
                class="flex-1 px-3 py-1.5 text-xs bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
            <button wire:click="addNote" class="px-3 py-1.5 text-xs font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Add</button>
        </div>
    </div>

    @else
        <div class="bg-crm-card border border-crm-border rounded-lg p-8 text-center">
            <p class="text-sm text-crm-t3">Client not found.</p>
        </div>
    @endif
</div>
