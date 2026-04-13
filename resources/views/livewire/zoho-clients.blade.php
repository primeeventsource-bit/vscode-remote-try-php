<div class="p-5">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Zoho Clients</h2>
            <p class="text-xs text-crm-t3 mt-1">{{ number_format($totalClients) }} total contacts &middot; {{ $isConnected ? 'Zoho Connected' : 'Not Connected' }}</p>
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="openImport" class="px-3 py-1.5 text-xs font-semibold bg-crm-card border border-crm-border rounded-lg text-crm-t2 hover:bg-crm-hover transition">
                Import CSV
            </button>
            @if($isConnected)
                <form action="{{ route('zoho.sync') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Sync Now</button>
                </form>
                <a href="{{ route('zoho.disconnect') }}" class="px-3 py-1.5 text-xs font-semibold bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition">Disconnect</a>
            @else
                <a href="{{ route('zoho.redirect') }}" class="px-3 py-1.5 text-xs font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Connect Zoho</a>
            @endif
        </div>
    </div>

    {{-- Notifications --}}
    @if($syncMessage)
        <div class="mb-4 px-4 py-3 bg-{{ $syncType === 'success' ? 'emerald' : 'red' }}-50 border border-{{ $syncType === 'success' ? 'emerald' : 'red' }}-400 text-{{ $syncType === 'success' ? 'emerald' : 'red' }}-800 text-sm rounded-lg">{{ $syncMessage }}</div>
    @endif
    @if($importResult)
        <div class="mb-4 px-4 py-3 bg-{{ $importType === 'success' ? 'emerald' : 'red' }}-50 border border-{{ $importType === 'success' ? 'emerald' : 'red' }}-400 text-{{ $importType === 'success' ? 'emerald' : 'red' }}-800 text-sm rounded-lg">{{ $importResult }}</div>
    @endif

    {{-- Filters --}}
    <div class="bg-crm-card border border-crm-border rounded-lg p-3 mb-5">
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-[200px]">
                <input id="fld-zoho-search" type="text" wire:model.live.debounce.300ms="search" placeholder="Search name, email, phone, company..."
                    class="w-full px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
            </div>
            <select id="fld-zoho-status" wire:model.live="statusFilter" class="px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                <option value="all">All Statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <select id="fld-zoho-perpage" wire:model.live="perPage" class="px-3 py-1.5 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-crm-border bg-crm-surface">
                        <th class="text-left px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Name</th>
                        <th class="text-left px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Email</th>
                        <th class="text-left px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Phone</th>
                        <th class="text-left px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Company</th>
                        <th class="text-left px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Source</th>
                        <th class="text-left px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Owner</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Status</th>
                        <th class="text-left px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Synced</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clients as $client)
                        <tr class="border-b border-crm-border hover:bg-crm-hover transition cursor-pointer" onclick="window.location='{{ route('zoho-clients.show', $client->id) }}'">
                            <td class="px-3 py-2.5 font-semibold">{{ $client->first_name }} {{ $client->last_name }}</td>
                            <td class="px-3 py-2.5 text-crm-t2">{{ $client->email ?? '--' }}</td>
                            <td class="px-3 py-2.5 text-crm-t2 font-mono text-xs">{{ $client->phone ?? $client->mobile ?? '--' }}</td>
                            <td class="px-3 py-2.5 text-crm-t2">{{ $client->account_name ?? '--' }}</td>
                            <td class="px-3 py-2.5 text-crm-t3 text-xs">{{ $client->lead_source ?? '--' }}</td>
                            <td class="px-3 py-2.5 text-crm-t3 text-xs">{{ $client->contact_owner ?? '--' }}</td>
                            <td class="px-3 py-2.5 text-center">
                                <span class="px-2 py-0.5 text-[9px] font-bold rounded-full {{ $client->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">{{ ucfirst($client->status) }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-crm-t3 text-xs">{{ $client->last_synced_at?->diffForHumans() ?? '--' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-crm-t3 text-sm">No Zoho clients found. Import a CSV or connect Zoho to sync.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($clients, 'links'))
        <div class="p-3 border-t border-crm-border">{{ $clients->links() }}</div>
        @endif
    </div>

    {{-- ─── CSV Import Modal ─── --}}
    @if($showImportModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.6); backdrop-filter:blur(4px)">
        <div class="w-full max-w-lg bg-crm-card border border-crm-border rounded-2xl shadow-2xl">
            <div class="flex items-center justify-between px-5 py-4 border-b border-crm-border">
                <div>
                    <h3 class="text-sm font-bold">Import Zoho CSV</h3>
                    <p class="text-[10px] text-crm-t3 mt-0.5">Zoho CRM > Contacts > Export > CSV</p>
                </div>
                <button wire:click="closeImport" class="text-crm-t3 hover:text-crm-t1 text-lg">&times;</button>
            </div>

            <div class="p-5 space-y-4">
                <div>
                    <label for="fld-zoho-csv" class="block text-[10px] text-crm-t3 uppercase tracking-wider font-semibold mb-1">Select CSV File</label>
                    <input id="fld-zoho-csv" type="file" wire:model="csvFile" accept=".csv,.txt"
                        class="w-full px-3 py-2 text-sm bg-white border-2 border-dashed border-crm-border rounded-lg focus:outline-none focus:border-blue-400 file:mr-3 file:py-1.5 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-blue-600 file:text-white hover:file:bg-blue-700 file:cursor-pointer cursor-pointer">
                    @error('csvFile') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="csvFile" class="text-xs text-blue-500 mt-1 font-semibold">Reading file...</div>
                </div>

                @if(count($importPreview) > 0)
                <div>
                    <p class="text-xs font-semibold text-crm-t3 mb-2">Preview (first 5 of {{ number_format($importTotal) }} rows)</p>
                    <div class="overflow-x-auto rounded-lg border border-crm-border max-h-48">
                        <table class="w-full text-[10px]">
                            <thead>
                                <tr class="bg-crm-surface">
                                    @foreach(array_keys($importPreview[0]) as $col)
                                        <th class="px-2 py-1.5 text-left text-crm-t3 whitespace-nowrap border-b border-crm-border">{{ Str::limit($col, 15) }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($importPreview as $row)
                                    <tr class="border-b border-crm-border">
                                        @foreach($row as $val)
                                            <td class="px-2 py-1 text-crm-t2 whitespace-nowrap">{{ Str::limit($val ?? '--', 18) }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2 p-2 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700">
                        Ready to import <strong>{{ number_format($importTotal) }} contacts</strong>. Existing records matched by Zoho ID will be updated.
                    </div>
                </div>
                @endif

                @if(!$csvFile)
                <div class="p-3 rounded-lg bg-crm-surface border border-crm-border text-xs text-crm-t3">
                    <p class="font-semibold text-crm-t2 mb-1">Expected columns:</p>
                    <p>Contact ID, First Name, Last Name, Email, Phone, Mobile, Account Name, Title, Department, Mailing Street, City, State, Zip, Country, Lead Source, Contact Owner</p>
                </div>
                @endif
            </div>

            <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-crm-border">
                <button wire:click="closeImport" class="px-4 py-1.5 text-xs font-semibold text-crm-t2 bg-crm-surface border border-crm-border rounded-lg hover:bg-crm-hover transition">Cancel</button>
                @if(count($importPreview) > 0)
                <button wire:click="runImport" wire:loading.attr="disabled" wire:target="runImport"
                    class="px-4 py-1.5 text-xs font-bold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition disabled:opacity-50">
                    <span wire:loading.remove wire:target="runImport">Import {{ number_format($importTotal) }} Contacts</span>
                    <span wire:loading wire:target="runImport">Importing...</span>
                </button>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>
