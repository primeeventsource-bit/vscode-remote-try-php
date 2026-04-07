<div class="p-5">
    <div class="mb-5">
        <h2 class="text-xl font-bold">Training & Help Center</h2>
        <p class="text-xs text-crm-t3 mt-1">Learn the CRM, track your progress, and access resources</p>
    </div>

    {{-- Section Nav --}}
    <div class="flex flex-wrap items-center gap-1 bg-crm-card border border-crm-border rounded-lg p-0.5 mb-5">
        @php
            $sections = ['my_training' => 'My Training'];
            if ($isAdmin) {
                $sections['guide_builder'] = 'Guide Builder';
                $sections['team_progress'] = 'Team Progress';
            }
            $sections['help'] = 'Help & Resources';
        @endphp
        @foreach($sections as $key => $label)
            <button wire:click="$set('section', '{{ $key }}')"
                class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $section === $key ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ═══════════════════════════════════════════════
         MY TRAINING — Step-by-Step Viewer
    ═══════════════════════════════════════════════ --}}
    @if($section === 'my_training')
        @if($progress['total'] > 0)
            {{-- Progress bar --}}
            <div class="bg-crm-card border border-crm-border rounded-xl p-5 mb-5">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <div class="text-base font-bold">{{ $progress['flow']->name ?? 'Onboarding' }}</div>
                        <div class="text-xs text-crm-t3 mt-0.5">{{ $progress['completed'] }} of {{ $progress['total'] }} steps completed</div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-2xl font-extrabold {{ $progress['is_complete'] ? 'text-emerald-500' : 'text-blue-500' }}">{{ $progress['pct'] }}%</span>
                        @if($progress['is_complete'])
                            <span class="text-[9px] font-bold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600">Complete</span>
                        @else
                            <button wire:click="startTraining" class="px-3 py-1.5 text-xs font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                {{ $progress['completed'] > 0 ? 'Continue Training' : 'Start Training' }}
                            </button>
                        @endif
                    </div>
                </div>
                <div class="h-3 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-700 {{ $progress['is_complete'] ? 'bg-emerald-500' : 'bg-blue-500' }}" style="width: {{ $progress['pct'] }}%"></div>
                </div>
                {{-- Step dots --}}
                <div class="flex items-center gap-1 mt-3">
                    @foreach($progress['steps'] as $i => $step)
                        <button wire:click="goToStep({{ $i }})" title="{{ $step['title'] }}"
                            class="w-6 h-6 rounded-full flex items-center justify-center text-[8px] font-bold transition hover:scale-110
                            {{ $step['status'] === 'completed' ? 'bg-emerald-100 text-emerald-600' : ($step['status'] === 'skipped' ? 'bg-gray-100 text-gray-400' : ($activeStepIndex === $i ? 'bg-blue-600 text-white ring-2 ring-blue-300' : 'bg-gray-50 text-crm-t3 border border-crm-border')) }}">
                            @if($step['status'] === 'completed')
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            @else
                                {{ $i + 1 }}
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Step-by-Step Viewer --}}
            @if($activeStepIndex !== null && isset($progress['steps'][$activeStepIndex]))
                @php $currentStep = $progress['steps'][$activeStepIndex]; @endphp
                <div class="bg-white border border-crm-border rounded-xl shadow-sm overflow-hidden mb-5">
                    {{-- Step Header --}}
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-crm-border px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl {{ $currentStep['status'] === 'completed' ? 'bg-emerald-100' : 'bg-blue-100' }}">
                                @if($currentStep['status'] === 'completed')
                                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                @else
                                    {{ $currentStep['icon'] }}
                                @endif
                            </div>
                            <div>
                                <div class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Step {{ $activeStepIndex + 1 }} of {{ $progress['total'] }}</div>
                                <h3 class="text-lg font-bold text-crm-t1">{{ $currentStep['title'] }}</h3>
                            </div>
                            @if($currentStep['step_type'] !== 'info')
                                <span class="ml-auto text-[9px] font-bold px-2 py-0.5 rounded-full
                                    {{ $currentStep['step_type'] === 'action' ? 'bg-amber-50 text-amber-600' : ($currentStep['step_type'] === 'screenshot' ? 'bg-purple-50 text-purple-600' : 'bg-blue-50 text-blue-600') }}">
                                    {{ ucfirst($currentStep['step_type']) }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Step Content --}}
                    <div class="p-6">
                        {{-- Description --}}
                        <p class="text-sm text-crm-t2 leading-relaxed mb-4">{{ $currentStep['description'] }}</p>

                        {{-- Screenshot/Image --}}
                        @if($currentStep['image_path'])
                            <div class="mb-4 rounded-lg overflow-hidden border border-crm-border bg-gray-50"
                                 x-data="{ lightbox: false }">
                                <img src="{{ asset('storage/' . $currentStep['image_path']) }}"
                                     alt="{{ $currentStep['image_caption'] ?? $currentStep['title'] }}"
                                     class="w-full max-h-96 object-contain cursor-pointer hover:opacity-90 transition"
                                     @click="lightbox = true">
                                @if($currentStep['image_caption'])
                                    <div class="px-3 py-2 text-[10px] text-crm-t3 bg-white border-t border-crm-border">{{ $currentStep['image_caption'] }}</div>
                                @endif
                                {{-- Lightbox --}}
                                <div x-show="lightbox" x-transition.opacity @click="lightbox = false"
                                     class="fixed inset-0 bg-black/80 z-[99999] flex items-center justify-center p-8 cursor-pointer" style="display:none">
                                    <img src="{{ asset('storage/' . $currentStep['image_path']) }}" class="max-w-full max-h-full rounded-lg shadow-2xl">
                                </div>
                            </div>
                        @endif

                        {{-- Tip box --}}
                        @if($currentStep['tip_text'])
                            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                                <div class="flex items-start gap-2">
                                    <span class="text-amber-500 text-sm flex-shrink-0">💡</span>
                                    <p class="text-xs text-amber-800">{{ $currentStep['tip_text'] }}</p>
                                </div>
                            </div>
                        @endif

                        {{-- Target route link --}}
                        @if($currentStep['target_route'])
                            <a href="{{ $currentStep['target_route'] }}" class="inline-flex items-center gap-1.5 text-xs text-blue-600 hover:text-blue-700 font-semibold mb-4">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                Open {{ str_replace('/', '', $currentStep['target_route']) }}
                            </a>
                        @endif

                        {{-- Element targeting hint --}}
                        @if($currentStep['step_type'] === 'action' && $currentStep['target_selector'])
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                                <p class="text-xs text-blue-800"><span class="font-bold">Action required:</span> {{ $currentStep['action_event'] === 'click' ? 'Click the highlighted element to continue.' : 'Complete the action described above.' }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- Step Navigation --}}
                    <div class="border-t border-crm-border px-6 py-4 bg-gray-50 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            @if($activeStepIndex > 0)
                                <button wire:click="goToStep({{ $activeStepIndex - 1 }})" class="px-4 py-2 text-xs font-semibold text-crm-t2 bg-white border border-crm-border rounded-lg hover:bg-crm-hover transition">
                                    &larr; Previous
                                </button>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            @if($currentStep['status'] === 'not_started')
                                <button wire:click="skipStep({{ $currentStep['id'] }})" class="px-4 py-2 text-xs font-semibold text-crm-t3 bg-gray-100 rounded-lg hover:bg-gray-200 transition">Skip</button>
                                <button wire:click="completeStep({{ $currentStep['id'] }})" class="px-4 py-2 text-xs font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">
                                    Mark Complete &rarr;
                                </button>
                            @elseif($currentStep['status'] === 'skipped')
                                <button wire:click="completeStep({{ $currentStep['id'] }})" class="px-4 py-2 text-xs font-semibold text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition">Complete This Step</button>
                            @endif
                            @if($activeStepIndex < $progress['total'] - 1)
                                <button wire:click="goToStep({{ $activeStepIndex + 1 }})" class="px-4 py-2 text-xs font-semibold text-crm-t2 bg-white border border-crm-border rounded-lg hover:bg-crm-hover transition">
                                    Next &rarr;
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- Step List (collapsed view when no active step or always visible) --}}
            <div class="space-y-2">
                @foreach($progress['steps'] as $i => $step)
                    <div wire:click="goToStep({{ $i }})" class="bg-crm-card border border-crm-border rounded-lg p-3.5 cursor-pointer transition hover:shadow-sm
                        {{ $activeStepIndex === $i ? 'ring-2 ring-blue-400 bg-blue-50/30' : '' }}
                        {{ $step['status'] === 'completed' ? 'bg-emerald-50/30' : ($step['status'] === 'skipped' ? 'bg-gray-50' : '') }}">
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-sm
                                {{ $step['status'] === 'completed' ? 'bg-emerald-100 text-emerald-600' : ($step['status'] === 'skipped' ? 'bg-gray-100 text-gray-400' : 'bg-blue-50 text-blue-600') }}">
                                @if($step['status'] === 'completed')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                @else
                                    {{ $step['icon'] }}
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold {{ $step['status'] === 'completed' ? 'text-emerald-700' : ($step['status'] === 'skipped' ? 'text-crm-t3 line-through' : '') }}">
                                        Step {{ $i + 1 }}: {{ $step['title'] }}
                                    </span>
                                    @if($step['step_type'] !== 'info')
                                        <span class="text-[7px] font-bold px-1 py-0.5 rounded uppercase
                                            {{ $step['step_type'] === 'action' ? 'bg-amber-50 text-amber-500' : ($step['step_type'] === 'screenshot' ? 'bg-purple-50 text-purple-500' : 'bg-blue-50 text-blue-500') }}">
                                            {{ $step['step_type'] }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-[11px] text-crm-t3 mt-0.5 truncate">{{ $step['description'] }}</p>
                            </div>
                            <div class="flex-shrink-0">
                                @if($step['status'] === 'not_started' && $activeStepIndex !== $i)
                                    <span class="text-[9px] text-crm-t3">Click to view</span>
                                @elseif($step['status'] === 'skipped')
                                    <span class="text-[8px] font-bold px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">Skipped</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Reset --}}
            <div class="mt-4 text-center">
                <button wire:click="resetMyOnboarding" wire:confirm="Reset all your training progress?" class="text-xs text-crm-t3 hover:text-red-500 transition">Reset my training progress</button>
            </div>
        @else
            <div class="bg-crm-card border border-crm-border rounded-lg p-8 text-center">
                <div class="text-3xl mb-3">📚</div>
                <p class="text-sm text-crm-t3">No training guide available for your role yet.</p>
                <p class="text-xs text-crm-t3 mt-1">Contact your admin to set up a training guide for <strong>{{ ucfirst(str_replace('_', ' ', auth()->user()->role)) }}</strong>.</p>
            </div>
        @endif
    @endif

    {{-- ═══════════════════════════════════════════════
         GUIDE BUILDER (Admin/Master Admin)
    ═══════════════════════════════════════════════ --}}
    @if($section === 'guide_builder' && $isAdmin)
        @if($editingFlow)
            {{-- ── Step Manager for a specific guide ── --}}
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <button wire:click="closeStepEditor" class="text-xs text-blue-600 hover:underline">&larr; Back to Guides</button>
                    <h3 class="text-base font-bold mt-1">{{ $editingFlow->name }} — Steps</h3>
                    <p class="text-[10px] text-crm-t3">Role: {{ ucfirst(str_replace('_', ' ', $editingFlow->role)) }} &middot; {{ $editingFlow->steps->count() }} steps</p>
                </div>
                <button wire:click="openNewStep" class="px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition">+ Add Step</button>
            </div>

            {{-- Steps list --}}
            <div class="space-y-2">
                @forelse($editingFlow->steps as $step)
                    <div class="bg-crm-card border border-crm-border rounded-lg p-4 {{ !$step->is_enabled ? 'opacity-50' : '' }}">
                        <div class="flex items-start gap-3">
                            {{-- Reorder --}}
                            <div class="flex flex-col gap-0.5 flex-shrink-0 pt-1">
                                <button wire:click="moveStepUp({{ $step->id }})" class="w-5 h-5 flex items-center justify-center rounded bg-gray-100 hover:bg-gray-200 text-[10px] text-crm-t3">&#9650;</button>
                                <button wire:click="moveStepDown({{ $step->id }})" class="w-5 h-5 flex items-center justify-center rounded bg-gray-100 hover:bg-gray-200 text-[10px] text-crm-t3">&#9660;</button>
                            </div>
                            {{-- Step info --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-sm">{{ $step->icon ?? '📌' }}</span>
                                    <span class="text-sm font-semibold">{{ $step->title }}</span>
                                    <span class="text-[8px] font-bold px-1.5 py-0.5 rounded uppercase
                                        {{ $step->step_type === 'action' ? 'bg-amber-50 text-amber-600' : ($step->step_type === 'screenshot' ? 'bg-purple-50 text-purple-600' : ($step->step_type === 'info' ? 'bg-gray-100 text-gray-600' : 'bg-blue-50 text-blue-600')) }}">
                                        {{ $step->step_type ?? 'tooltip' }}
                                    </span>
                                    @if(!$step->is_enabled)
                                        <span class="text-[8px] font-bold px-1.5 py-0.5 rounded bg-red-50 text-red-500">Disabled</span>
                                    @endif
                                </div>
                                <p class="text-[11px] text-crm-t3">{{ \Illuminate\Support\Str::limit($step->description, 100) }}</p>
                                <div class="flex flex-wrap gap-2 mt-1.5 text-[9px] text-crm-t3">
                                    @if($step->target_selector)
                                        <span class="px-1.5 py-0.5 bg-gray-50 border border-crm-border rounded font-mono">{{ $step->target_selector }}</span>
                                    @endif
                                    @if($step->target_route)
                                        <span class="px-1.5 py-0.5 bg-blue-50 border border-blue-100 rounded">Route: {{ $step->target_route }}</span>
                                    @endif
                                    @if($step->image_path)
                                        <span class="px-1.5 py-0.5 bg-purple-50 border border-purple-100 rounded">Has Image</span>
                                    @endif
                                </div>
                            </div>
                            {{-- Actions --}}
                            <div class="flex items-center gap-1 flex-shrink-0">
                                <button wire:click="toggleStep({{ $step->id }})" class="px-2 py-1 text-[10px] font-semibold rounded {{ $step->is_enabled ? 'bg-emerald-50 text-emerald-600' : 'bg-gray-100 text-gray-500' }} hover:opacity-80 transition">
                                    {{ $step->is_enabled ? 'On' : 'Off' }}
                                </button>
                                <button wire:click="editStep({{ $step->id }})" class="px-2 py-1 text-[10px] font-semibold bg-blue-50 text-blue-600 rounded hover:bg-blue-100 transition">Edit</button>
                                <button wire:click="deleteStep({{ $step->id }})" wire:confirm="Delete this step?" class="px-2 py-1 text-[10px] font-semibold bg-red-50 text-red-500 rounded hover:bg-red-100 transition">Delete</button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="bg-crm-card border border-crm-border rounded-lg p-8 text-center">
                        <p class="text-sm text-crm-t3">No steps yet. Click "Add Step" to start building this guide.</p>
                    </div>
                @endforelse
            </div>
        @else
            {{-- ── Guide List ── --}}
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold">Training Guides</h3>
                <button wire:click="openNewGuide" class="px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition">+ New Guide</button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @forelse($allFlows as $flow)
                    <div class="bg-crm-card border border-crm-border rounded-xl p-5">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h4 class="text-sm font-bold">{{ $flow->name }}</h4>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-blue-50 text-blue-600 uppercase">{{ str_replace('_', ' ', $flow->role) }}</span>
                                    <span class="text-[9px] font-bold px-2 py-0.5 rounded-full {{ $flow->is_active ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-500' }}">
                                        {{ $flow->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                    @if($flow->is_published ?? true)
                                        <span class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-green-50 text-green-600">Published</span>
                                    @else
                                        <span class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">Draft</span>
                                    @endif
                                </div>
                            </div>
                            <div class="text-lg font-extrabold text-crm-t3">{{ $flow->steps->count() }}</div>
                        </div>
                        @if($flow->description)
                            <p class="text-[11px] text-crm-t3 mb-3">{{ \Illuminate\Support\Str::limit($flow->description, 120) }}</p>
                        @endif
                        <div class="flex items-center gap-2">
                            <button wire:click="manageSteps({{ $flow->id }})" class="px-3 py-1.5 text-[10px] font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Manage Steps</button>
                            <button wire:click="editGuide({{ $flow->id }})" class="px-3 py-1.5 text-[10px] font-semibold bg-white border border-crm-border rounded-lg hover:bg-crm-hover transition">Edit Guide</button>
                            @if($isMaster)
                                <button wire:click="deleteGuide({{ $flow->id }})" wire:confirm="Delete this guide and all its steps?" class="px-3 py-1.5 text-[10px] font-semibold text-red-500 bg-red-50 rounded-lg hover:bg-red-100 transition">Delete</button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-2 bg-crm-card border border-crm-border rounded-lg p-8 text-center">
                        <div class="text-3xl mb-3">📝</div>
                        <p class="text-sm text-crm-t3">No training guides yet. Create one to get started.</p>
                    </div>
                @endforelse
            </div>
        @endif
    @endif

    {{-- ═══════════════════════════════════════════════
         TEAM PROGRESS (Admin/Master Admin)
    ═══════════════════════════════════════════════ --}}
    @if($section === 'team_progress' && $isAdmin)
        <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-crm-border bg-crm-surface">
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">User</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Role</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Progress</th>
                        <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Status</th>
                        @if($isMaster)
                            <th class="text-center px-3 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($allUsersProgress as $up)
                        <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-[9px] font-bold text-white" style="background: {{ $up['color'] ?? '#6b7280' }}">{{ $up['avatar'] ?? '--' }}</div>
                                    <span class="font-semibold">{{ $up['name'] }}</span>
                                </div>
                            </td>
                            <td class="text-center px-3 py-2.5 text-xs text-crm-t3">{{ ucfirst(str_replace('_', ' ', $up['role'])) }}</td>
                            <td class="text-center px-3 py-2.5">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="w-20 h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full {{ $up['is_complete'] ? 'bg-emerald-500' : 'bg-blue-500' }}" style="width: {{ $up['pct'] }}%"></div>
                                    </div>
                                    <span class="text-[10px] font-mono font-bold">{{ $up['pct'] }}%</span>
                                </div>
                            </td>
                            <td class="text-center px-3 py-2.5">
                                <span class="text-[8px] font-bold px-1.5 py-0.5 rounded {{ $up['is_complete'] ? 'bg-emerald-50 text-emerald-600' : ($up['pct'] > 0 ? 'bg-amber-50 text-amber-600' : 'bg-gray-100 text-gray-500') }}">
                                    {{ $up['is_complete'] ? 'Complete' : ($up['pct'] > 0 ? 'In Progress' : 'Not Started') }}
                                </span>
                            </td>
                            @if($isMaster)
                                <td class="text-center px-3 py-2.5">
                                    <button wire:click="resetUserOnboarding({{ $up['user_id'] }})" wire:confirm="Reset training for {{ $up['name'] }}?" class="text-[10px] text-red-500 hover:text-red-600 font-semibold">Reset</button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════
         HELP & RESOURCES
    ═══════════════════════════════════════════════ --}}
    @if($section === 'help')
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach([
                ['icon' => '📋', 'title' => 'Leads', 'desc' => 'Import, assign, and transfer leads to closers. Use dispositions to track status.', 'route' => '/leads'],
                ['icon' => '📊', 'title' => 'Deals & Pipeline', 'desc' => 'Convert leads to deals. Track through verification and charging.', 'route' => '/deals'],
                ['icon' => '💰', 'title' => 'Payroll & Commissions', 'desc' => 'Commission = Fee - SNR% - VD%. Closer gets their % of the payable amount.', 'route' => '/payroll'],
                ['icon' => '⚠️', 'title' => 'Chargebacks', 'desc' => 'Upload evidence, track required documents, and submit defense packages.', 'route' => '/clients'],
                ['icon' => '💬', 'title' => 'Chat', 'desc' => 'Direct messages, group chats, video/audio calls, GIFs, and file sharing.', 'route' => '/chat'],
                ['icon' => '📄', 'title' => 'Documents & Sheets', 'desc' => 'Create, edit, and share documents and spreadsheets with your team.', 'route' => '/documents'],
                ['icon' => '⚙️', 'title' => 'Settings', 'desc' => 'Configure CRM behavior, payroll rates, chat, uploads, and permissions.', 'route' => '/settings'],
                ['icon' => '👥', 'title' => 'User Management', 'desc' => 'Create accounts, assign roles, and manage team access.', 'route' => '/users'],
                ['icon' => '📈', 'title' => 'Statistics', 'desc' => 'Pipeline performance by fronter, closer, and admin with date filters.', 'route' => '/stats'],
            ] as $card)
                <a href="{{ $card['route'] }}" class="bg-crm-card border border-crm-border rounded-lg p-4 hover:bg-crm-hover transition">
                    <div class="text-2xl mb-2">{{ $card['icon'] }}</div>
                    <div class="text-sm font-bold mb-1">{{ $card['title'] }}</div>
                    <p class="text-xs text-crm-t3">{{ $card['desc'] }}</p>
                </a>
            @endforeach
        </div>

        {{-- Payroll Formula Help --}}
        <div class="mt-6 bg-crm-card border border-crm-border rounded-lg p-5">
            <div class="text-sm font-bold mb-3">Payroll Formula Explained</div>
            <div class="text-xs text-crm-t2 space-y-2">
                <div><span class="font-bold text-crm-t1">Step 1:</span> Payable = Deal Fee - SNR Deduction - VD Deduction (if applicable)</div>
                <div><span class="font-bold text-crm-t1">Step 2:</span> Closer Commission = Payable x Closer %</div>
                <div><span class="font-bold text-crm-t1">Step 3:</span> Fronter Commission = Payable x Fronter %</div>
                <div class="pt-2 border-t border-crm-border">
                    <span class="font-bold">Example:</span> $5,000 deal, 3% SNR, 5% VD<br>
                    Payable = $5,000 - $150 - $250 = $4,600<br>
                    Closer (40%) = $1,840 | Fronter (10%) = $460
                </div>
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════
         MODALS
    ═══════════════════════════════════════════════ --}}

    {{-- Guide Create/Edit Modal --}}
    @if($showGuideModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center backdrop-blur-sm" wire:click.self="$set('showGuideModal', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 mx-4">
                <h3 class="text-base font-bold mb-4">{{ $editingGuide ? 'Edit Guide' : 'New Training Guide' }}</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Guide Name</label>
                        <input wire:model="guideForm.name" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" placeholder="e.g. Fronter Training Guide">
                        @error('guideForm.name') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Assign to Role</label>
                        <select wire:model="guideForm.role" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none">
                            @foreach($availableRoles as $role)
                                <option value="{{ $role }}">{{ ucfirst(str_replace('_', ' ', $role)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Description</label>
                        <textarea wire:model="guideForm.description" rows="2" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" placeholder="Brief description..."></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center gap-2 text-xs">
                            <input type="checkbox" wire:model="guideForm.is_active" class="rounded border-crm-border"> Active
                        </label>
                        <label class="flex items-center gap-2 text-xs">
                            <input type="checkbox" wire:model="guideForm.is_published" class="rounded border-crm-border"> Published
                        </label>
                        <label class="flex items-center gap-2 text-xs">
                            <input type="checkbox" wire:model="guideForm.auto_start_on_first_login" class="rounded border-crm-border"> Auto-start on first login
                        </label>
                        <label class="flex items-center gap-2 text-xs">
                            <input type="checkbox" wire:model="guideForm.allow_skip" class="rounded border-crm-border"> Allow skip
                        </label>
                        <label class="flex items-center gap-2 text-xs">
                            <input type="checkbox" wire:model="guideForm.lock_ui_during_training" class="rounded border-crm-border"> Lock UI during training
                        </label>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 mt-5">
                    <button wire:click="$set('showGuideModal', false)" class="px-4 py-2 text-xs font-semibold text-crm-t3 bg-gray-100 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                    <button wire:click="saveGuide" class="px-4 py-2 text-xs font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">{{ $editingGuide ? 'Update Guide' : 'Create Guide' }}</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Step Create/Edit Modal --}}
    @if($showStepModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center backdrop-blur-sm overflow-y-auto py-8" wire:click.self="$set('showStepModal', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl p-6 mx-4 my-auto">
                <h3 class="text-base font-bold mb-4">{{ $editingStep ? 'Edit Step' : 'Add Training Step' }}</h3>
                <div class="space-y-3 max-h-[65vh] overflow-y-auto pr-1">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Step Title</label>
                            <input wire:model="stepForm.title" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" placeholder="e.g. Open the Leads page">
                            @error('stepForm.title') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Step Type</label>
                            <select wire:model="stepForm.step_type" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none">
                                <option value="tooltip">Tooltip — Highlight + Instruction</option>
                                <option value="action">Action — Require User Click</option>
                                <option value="info">Info — Modal Explanation</option>
                                <option value="screenshot">Screenshot — Image Guide</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Description / Instructions</label>
                        <textarea wire:model="stepForm.description" rows="3" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" placeholder="Explain what the user should do or learn..."></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Target Selector (CSS)</label>
                            <input wire:model="stepForm.target_selector" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none font-mono text-[11px]" placeholder='e.g. [data-training="leads-tab"]'>
                        </div>
                        <div>
                            <label class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Target Route</label>
                            <input wire:model="stepForm.target_route" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none" placeholder="e.g. /leads">
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Tooltip Position</label>
                            <select wire:model="stepForm.tooltip_position" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none">
                                <option value="top">Top</option>
                                <option value="bottom">Bottom</option>
                                <option value="left">Left</option>
                                <option value="right">Right</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Action Event</label>
                            <select wire:model="stepForm.action_event" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none">
                                <option value="">None</option>
                                <option value="click">Click</option>
                                <option value="input">Input / Type</option>
                                <option value="navigate">Navigate to Route</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Icon</label>
                            <input wire:model="stepForm.icon" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none" placeholder="📌">
                        </div>
                    </div>
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Tip / Pro-Tip Text (optional)</label>
                        <input wire:model="stepForm.tip_text" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none" placeholder="Quick tip shown in a highlight box">
                    </div>
                    {{-- Image Upload --}}
                    <div>
                        <label class="text-[10px] text-crm-t3 uppercase tracking-wider font-semibold">Screenshot / Image</label>
                        <input type="file" wire:model="stepImage" accept="image/*" class="w-full text-xs text-crm-t3 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-semibold file:bg-blue-50 file:text-blue-600 hover:file:bg-blue-100">
                        @error('stepImage') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        @if($stepImage)
                            <div class="mt-2 rounded-lg border border-crm-border overflow-hidden max-h-40">
                                <img src="{{ $stepImage->temporaryUrl() }}" class="w-full max-h-40 object-contain bg-gray-50">
                            </div>
                        @endif
                        <input wire:model="stepImageCaption" type="text" class="w-full px-3 py-1.5 text-xs mt-1 bg-crm-surface border border-crm-border rounded-lg focus:outline-none" placeholder="Image caption (optional)">
                    </div>
                    {{-- Toggles --}}
                    <div class="grid grid-cols-3 gap-3 pt-2 border-t border-crm-border">
                        <label class="flex items-center gap-2 text-xs"><input type="checkbox" wire:model="stepForm.is_required" class="rounded border-crm-border"> Required</label>
                        <label class="flex items-center gap-2 text-xs"><input type="checkbox" wire:model="stepForm.is_enabled" class="rounded border-crm-border"> Enabled</label>
                        <label class="flex items-center gap-2 text-xs"><input type="checkbox" wire:model="stepForm.highlight_element" class="rounded border-crm-border"> Highlight</label>
                        <label class="flex items-center gap-2 text-xs"><input type="checkbox" wire:model="stepForm.dim_background" class="rounded border-crm-border"> Dim Background</label>
                        <label class="flex items-center gap-2 text-xs"><input type="checkbox" wire:model="stepForm.auto_scroll" class="rounded border-crm-border"> Auto Scroll</label>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 mt-5">
                    <button wire:click="$set('showStepModal', false)" class="px-4 py-2 text-xs font-semibold text-crm-t3 bg-gray-100 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                    <button wire:click="saveStep" class="px-4 py-2 text-xs font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">{{ $editingStep ? 'Update Step' : 'Add Step' }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
