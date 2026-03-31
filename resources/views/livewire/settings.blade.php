<div class="p-5">
    <div class="mb-5">
        <h2 class="text-xl font-bold">Settings</h2>
        <p class="text-xs text-crm-t3 mt-1">Master admin configuration</p>
    </div>

    <div class="max-w-2xl space-y-5">
        {{-- CRM Name --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-sm font-semibold mb-3">CRM Name</div>
            <div class="flex items-center gap-3">
                <input wire:model="crmName" type="text" class="flex-1 px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" placeholder="Prime CRM">
                <button wire:click="saveCrmName" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Save</button>
            </div>
        </div>

        {{-- Theme Selector --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-sm font-semibold mb-3">Theme</div>
            <div class="grid grid-cols-4 gap-3">
                @foreach([
                    ['light', 'Light', '#ffffff', '#f7f8fa', '#111111'],
                    ['dark', 'Dark', '#1a1a2e', '#16213e', '#e0e0e0'],
                    ['blue', 'Blue', '#0f172a', '#1e293b', '#e2e8f0'],
                    ['green', 'Green', '#022c22', '#064e3b', '#d1fae5'],
                ] as [$themeKey, $themeLabel, $bg, $surface, $text])
                    <button wire:click="setTheme('{{ $themeKey }}')"
                        class="p-3 rounded-lg border-2 transition {{ (isset($theme) && $theme === $themeKey) ? 'border-blue-500' : 'border-crm-border hover:border-crm-border-h' }}">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-4 h-4 rounded-full border border-crm-border" style="background: {{ $bg }}"></div>
                            <div class="w-4 h-4 rounded-full border border-crm-border" style="background: {{ $surface }}"></div>
                            <div class="w-4 h-4 rounded-full border border-crm-border" style="background: {{ $text }}"></div>
                        </div>
                        <div class="text-xs font-semibold">{{ $themeLabel }}</div>
                        @if(isset($theme) && $theme === $themeKey)
                            <div class="text-[8px] text-blue-600 font-semibold mt-1">Active</div>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Deal Status Manager --}}
        <div class="bg-crm-card border border-crm-border rounded-lg p-4">
            <div class="text-sm font-semibold mb-3">Deal Statuses</div>

            {{-- Existing Statuses --}}
            <div class="space-y-2 mb-4">
                @if(isset($dealStatuses) && count($dealStatuses))
                    @foreach($dealStatuses as $idx => $status)
                        <div class="flex items-center gap-3 bg-white border border-crm-border rounded-lg p-2.5">
                            <input wire:model="dealStatuses.{{ $idx }}.color" type="color" class="w-8 h-8 rounded cursor-pointer border-0 flex-shrink-0">
                            <div class="flex-1">
                                @if(isset($editingStatus) && $editingStatus === $idx)
                                    <input wire:model="dealStatuses.{{ $idx }}.label" type="text" class="w-full px-2 py-1 text-sm bg-crm-surface border border-crm-border rounded focus:outline-none focus:border-blue-400">
                                @else
                                    <span class="text-sm font-semibold">{{ $status['label'] ?? $status }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-1 flex-shrink-0">
                                @if(isset($editingStatus) && $editingStatus === $idx)
                                    <button wire:click="saveStatus({{ $idx }})" class="text-[10px] font-semibold px-2 py-1 rounded bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition">Save</button>
                                    <button wire:click="$set('editingStatus', null)" class="text-[10px] font-semibold px-2 py-1 rounded bg-gray-100 text-crm-t3 hover:bg-gray-200 transition">Cancel</button>
                                @else
                                    <button wire:click="$set('editingStatus', {{ $idx }})" class="text-[10px] font-semibold px-2 py-1 rounded bg-blue-50 text-blue-600 hover:bg-blue-100 transition">Edit</button>
                                    <button wire:click="removeStatus({{ $idx }})" wire:confirm="Remove this status?" class="text-[10px] font-semibold px-2 py-1 rounded bg-red-50 text-red-500 hover:bg-red-100 transition">Remove</button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @else
                    <p class="text-xs text-crm-t3">No custom statuses defined</p>
                @endif
            </div>

            {{-- Add New Status --}}
            <div class="border-t border-crm-border pt-3">
                <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-2 font-semibold">Add New Status</div>
                <div class="flex items-center gap-3">
                    <input wire:model="newStatus.color" type="color" value="#3b82f6" class="w-8 h-8 rounded cursor-pointer border-0 flex-shrink-0">
                    <input wire:model="newStatus.label" type="text" placeholder="Status name..." class="flex-1 px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    <input wire:model="newStatus.value" type="text" placeholder="status_key" class="w-32 px-3 py-2 text-sm font-mono bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                    <button wire:click="addStatus" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition flex-shrink-0">Add</button>
                </div>
            </div>
        </div>
    </div>
</div>
