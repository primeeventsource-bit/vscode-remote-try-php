<div class="p-5" x-data="{ createOpen: $wire.entangle('showCreate') }">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Tasks</h2>
            <p class="text-xs text-crm-t3 mt-1">{{ $tasks->count() }} tasks</p>
        </div>
        <button @click="createOpen = !createOpen" class="px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition">
            <span x-text="createOpen ? 'Cancel' : '+ New Task'"></span>
        </button>
    </div>

    {{-- Create Task Form (collapsible) --}}
    <div x-show="createOpen" x-transition class="bg-crm-card border border-crm-border rounded-lg p-4 mb-4" style="display: none;">
        <div class="text-sm font-semibold mb-3">Create Task</div>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
            <div class="col-span-2 md:col-span-3">
                <label for="fld-newTitle" class="text-[10px] text-crm-t3 uppercase tracking-wider">Title</label>
                                <input id="fld-newTitle" wire:model="newTitle" type="text" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" placeholder="Task title...">
            </div>
            <div>
                <label for="fld-newAssignTo" class="text-[10px] text-crm-t3 uppercase tracking-wider">Assign To</label>
                                <select id="fld-newAssignTo" wire:model="newAssignTo" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none">
                    <option value="">Select user...</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="fld-newType" class="text-[10px] text-crm-t3 uppercase tracking-wider">Type</label>
                                <select id="fld-newType" wire:model="newType" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none">
                    <option value="general">General</option>
                    <option value="call">Call</option>
                    <option value="follow_up">Follow Up</option>
                    <option value="admin">Admin</option>
                    <option value="verification">Verification</option>
                </select>
            </div>
            <div>
                <label for="fld-newPriority" class="text-[10px] text-crm-t3 uppercase tracking-wider">Priority</label>
                                <select id="fld-newPriority" wire:model="newPriority" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none">
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            <div>
                <label for="fld-newDueDate" class="text-[10px] text-crm-t3 uppercase tracking-wider">Due Date</label>
                                <input id="fld-newDueDate" wire:model="newDueDate" type="datetime-local" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
            </div>
            <div>
                <label for="fld-newDescription" class="text-[10px] text-crm-t3 uppercase tracking-wider">Description</label>
                                <input id="fld-newDescription" wire:model="newDescription" type="text" class="w-full px-3 py-2 text-sm bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400" placeholder="Optional description...">
            </div>
        </div>
        <div class="flex justify-end mt-3">
            <button wire:click="createTask" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Create Task</button>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex flex-wrap items-center gap-1 bg-crm-card border border-crm-border rounded-lg p-0.5 mb-4">
        @foreach(['mine' => 'My Tasks', 'open' => 'All Open', 'completed' => 'Completed', 'all' => 'All'] as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')"
                class="px-3 py-1.5 text-xs font-semibold rounded-md transition {{ $tab === $key ? 'bg-white text-blue-600 shadow-sm' : 'text-crm-t3 hover:text-crm-t1' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="flex gap-4">
        {{-- Task List --}}
        <div class="flex-1 space-y-2">
            @forelse($tasks as $task)
                @php
                    $pColor = match($task->priority ?? 'medium') {
                        'urgent' => 'border-l-red-500',
                        'high' => 'border-l-orange-500',
                        'medium' => 'border-l-blue-500',
                        'low' => 'border-l-gray-400',
                        default => 'border-l-gray-400',
                    };
                    $typeIcon = match($task->type ?? 'general') {
                        'call' => '📞',
                        'follow_up' => '🔄',
                        'admin' => '📋',
                        'verification' => '✓',
                        default => '📌',
                    };
                    $assignee = $users->firstWhere('id', $task->assigned_to);
                    $isOverdue = isset($task->due_date) && $task->due_date && \Carbon\Carbon::parse($task->due_date)->isPast() && ($task->status ?? '') !== 'completed';
                @endphp
                <div wire:click="selectTask({{ $task->id }})"
                     class="bg-crm-card border border-crm-border border-l-[3px] {{ $pColor }} rounded-lg p-3 cursor-pointer transition {{ (isset($activeTask) && $activeTask && $activeTask->id === $task->id) ? 'bg-blue-50/50 border-blue-400' : 'hover:bg-crm-hover' }}">
                    <div class="flex items-start gap-3">
                        <span class="text-base mt-0.5">{{ $typeIcon }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold {{ ($task->status ?? '') === 'completed' ? 'line-through text-crm-t3' : '' }}">{{ $task->title }}</span>
                                @if(($task->status ?? '') === 'completed')
                                    <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-600">Done</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-3 mt-1">
                                @if($assignee)
                                    <div class="flex items-center gap-1">
                                        <div class="w-4 h-4 rounded-full flex items-center justify-center text-[7px] font-bold text-white" style="background: {{ $assignee->color ?? '#6b7280' }}">{{ $assignee->avatar ?? substr($assignee->name, 0, 1) }}</div>
                                        <span class="text-[10px] text-crm-t3">{{ $assignee->name }}</span>
                                    </div>
                                @endif
                                @if(isset($task->due_date) && $task->due_date)
                                    <span class="text-[10px] font-mono {{ $isOverdue ? 'text-red-500 font-semibold' : 'text-crm-t3' }}">
                                        Due: {{ \Carbon\Carbon::parse($task->due_date)->format('n/j g:i A') }}
                                    </span>
                                @endif
                                <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-gray-100 text-crm-t3">{{ ucfirst(str_replace('_', ' ', $task->type ?? 'general')) }}</span>
                            </div>
                        </div>
                        <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded {{ match($task->priority ?? 'medium') {
                            'urgent' => 'bg-red-50 text-red-500',
                            'high' => 'bg-orange-50 text-orange-500',
                            'medium' => 'bg-blue-50 text-blue-600',
                            'low' => 'bg-gray-100 text-gray-500',
                            default => 'bg-gray-100 text-gray-500',
                        } }}">{{ ucfirst($task->priority ?? 'medium') }}</span>
                    </div>
                </div>
            @empty
                <div class="bg-crm-card border border-crm-border rounded-lg p-8 text-center">
                    <p class="text-sm text-crm-t3">No tasks found</p>
                </div>
            @endforelse
        </div>

        {{-- Detail Panel --}}
        @if($activeTask)
            <div class="w-80 flex-shrink-0 bg-crm-card border border-crm-border rounded-lg p-4 max-h-[75vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-bold">Task Detail</h4>
                    <button wire:click="selectTask({{ $activeTask->id }})" class="text-crm-t3 hover:text-crm-t1">&times;</button>
                </div>

                <div class="space-y-2 mb-4">
                    <div>
                        <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Title</div>
                        <div class="text-sm font-semibold mt-0.5">{{ $activeTask->title }}</div>
                    </div>
                    @if($activeTask->description ?? null)
                        <div>
                            <div class="text-[10px] text-crm-t3 uppercase tracking-wider">Description</div>
                            <div class="text-sm mt-0.5">{{ $activeTask->description }}</div>
                        </div>
                    @endif
                    @foreach([
                        'Assigned To' => $users->firstWhere('id', $activeTask->assigned_to)?->name ?? '--',
                        'Type' => ucfirst(str_replace('_', ' ', $activeTask->type ?? 'general')),
                        'Priority' => ucfirst($activeTask->priority ?? 'medium'),
                        'Status' => ucfirst($activeTask->status ?? 'open'),
                        'Due Date' => isset($activeTask->due_date) && $activeTask->due_date ? \Carbon\Carbon::parse($activeTask->due_date)->format('n/j/Y g:i A') : '--',
                        'Created' => $activeTask->created_at?->format('n/j/Y g:i A') ?? '--',
                    ] as $lbl => $val)
                        <div class="flex justify-between text-xs">
                            <span class="text-crm-t3">{{ $lbl }}</span>
                            <span class="font-semibold">{{ $val }}</span>
                        </div>
                    @endforeach
                </div>

                {{-- Notes / Activity Log --}}
                <div class="border-t border-crm-border pt-3 mb-3">
                    <div class="text-[10px] text-crm-t3 uppercase tracking-wider mb-2 font-semibold">Notes / Activity</div>
                    @if(isset($activeTask->notes) && $activeTask->notes)
                        @php $taskNotes = is_array($activeTask->notes) ? $activeTask->notes : [['text' => $activeTask->notes, 'at' => now()->toDateTimeString()]]; @endphp
                        <div class="space-y-1.5 mb-2 max-h-40 overflow-y-auto">
                            @foreach($taskNotes as $note)
                                <div class="text-xs bg-white border border-crm-border rounded p-2">
                                    <div>{{ is_array($note) ? ($note['text'] ?? '') : $note }}</div>
                                    @if(is_array($note) && isset($note['at']))
                                        <div class="text-[10px] text-crm-t3 mt-0.5">{{ $note['at'] }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-crm-t3 mb-2">No notes yet</p>
                    @endif
                    <div class="flex gap-1">
                        <input id="fld-newNote" wire:model="newNote" type="text" placeholder="Add note..." class="flex-1 px-2 py-1.5 text-xs bg-white border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        <button wire:click="addNote" class="px-2 py-1.5 text-xs font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Add</button>
                    </div>
                </div>

                {{-- Complete Button --}}
                @if(($activeTask->status ?? '') !== 'completed')
                    <button wire:click="completeTask({{ $activeTask->id }})" class="w-full px-4 py-2 text-sm font-semibold text-white bg-emerald-500 rounded-lg hover:bg-emerald-600 transition">Mark Complete</button>
                @else
                    <button wire:click="reopenTask({{ $activeTask->id }})" class="w-full px-4 py-2 text-sm font-semibold text-crm-t2 bg-crm-card border border-crm-border rounded-lg hover:bg-crm-hover transition">Reopen Task</button>
                @endif
            </div>
        @endif
    </div>
</div>
