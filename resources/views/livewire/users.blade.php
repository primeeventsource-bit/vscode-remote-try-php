<div class="p-5">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-xl font-bold">Users</h2>
            <p class="text-xs text-crm-t3 mt-1">{{ $users->count() }} team members</p>
        </div>
        <button wire:click="$set('showAddModal', true)" class="px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition">+ Add User</button>
    </div>

    {{-- Users Table --}}
    <div class="bg-crm-card border border-crm-border rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-crm-border bg-crm-surface">
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Name</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Username</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Password</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Email</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Role</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Comm%</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">$/hr</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Perms</th>
                        <th class="text-left px-4 py-2.5 text-[10px] uppercase tracking-wider text-crm-t3 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        @php
                            $isEditing = isset($editingUser) && $editingUser === $user->id;
                            $permsCount = is_array($user->permissions) ? count($user->permissions) : count(json_decode($user->permissions ?? '[]', true));
                            $roleColor = match($user->role) {
                                'master_admin' => 'bg-red-50 text-red-600',
                                'admin' => 'bg-purple-50 text-purple-600',
                                'closer' => 'bg-blue-50 text-blue-600',
                                'fronter' => 'bg-pink-50 text-pink-600',
                                default => 'bg-gray-100 text-gray-600',
                            };
                        @endphp
                        <tr class="border-b border-crm-border hover:bg-crm-hover transition">
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-[9px] font-bold text-white flex-shrink-0" style="background: {{ $user->color ?? '#6b7280' }}">{{ $user->avatar ?? substr($user->name, 0, 2) }}</div>
                                    <span class="font-semibold">{{ $user->name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-2.5 font-mono text-xs text-crm-t2">{{ $user->username }}</td>
                            <td class="px-4 py-2.5 font-mono text-xs" x-data="{ show: false }">
                                <span x-show="!show" class="text-crm-t3 cursor-pointer" @click="show = true">********</span>
                                <span x-show="show" class="text-crm-t1" @click="show = false" style="display: none;">{{ $user->password }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-xs text-crm-t2">{{ $user->email ?? '--' }}</td>
                            <td class="px-4 py-2.5">
                                @if($isAdmin)
                                    <select id="role-{{ $user->id }}" wire:change="changeRole({{ $user->id }}, $event.target.value)" class="text-[10px] font-semibold px-1.5 py-0.5 rounded border-0 {{ $roleColor }} focus:outline-none cursor-pointer">
                                        @foreach(['fronter', 'closer', 'admin', 'master_admin'] as $role)
                                            <option value="{{ $role }}" {{ $user->role === $role ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $role)) }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded {{ $roleColor }}">{{ ucfirst(str_replace('_', ' ', $user->role)) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 font-mono text-xs text-crm-t2">{{ $user->commission_rate ?? '--' }}%</td>
                            <td class="px-4 py-2.5 font-mono text-xs text-crm-t2">${{ $user->hourly_rate ?? '--' }}</td>
                            <td class="px-4 py-2.5">
                                <span class="text-[8px] font-semibold px-1.5 py-0.5 rounded bg-gray-100 text-crm-t3">{{ $permsCount }} perms</span>
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-1">
                                    <button wire:click="editUser({{ $user->id }})" class="text-[10px] font-semibold px-2 py-1 rounded bg-blue-50 text-blue-600 hover:bg-blue-100 transition">Edit</button>
                                    @if($isMaster)
                                        <button wire:click="removeUser({{ $user->id }})" wire:confirm="Are you sure you want to remove {{ $user->name }}?" class="text-[10px] font-semibold px-2 py-1 rounded bg-red-50 text-red-500 hover:bg-red-100 transition">Remove</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Add User Modal --}}
    @if($showAddModal)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center backdrop-blur-sm" wire:click.self="$set('showAddModal', false)">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 mx-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold">Add User</h3>
                    <button wire:click="$set('showAddModal', false)" class="text-crm-t3 hover:text-crm-t1 text-lg">&times;</button>
                </div>
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="fld-newUser-name" class="text-[10px] text-crm-t3 uppercase tracking-wider">Name</label>
                                <input id="fld-newUser-name" wire:model="newUser.name" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        </div>
                        <div>
                            <label for="fld-newUser-username" class="text-[10px] text-crm-t3 uppercase tracking-wider">Username</label>
                                <input id="fld-newUser-username" wire:model="newUser.username" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="fld-newUser-password" class="text-[10px] text-crm-t3 uppercase tracking-wider">Password</label>
                                <input id="fld-newUser-password" wire:model="newUser.password" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400 font-mono">
                        </div>
                        <div>
                            <label for="fld-newUser-email" class="text-[10px] text-crm-t3 uppercase tracking-wider">Email</label>
                                <input id="fld-newUser-email" wire:model="newUser.email" type="email" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label for="fld-newUser-role" class="text-[10px] text-crm-t3 uppercase tracking-wider">Role</label>
                                <select id="fld-newUser-role" wire:model="newUser.role" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none">
                                <option value="fronter">Fronter</option>
                                <option value="closer">Closer</option>
                                <option value="admin">Admin</option>
                                <option value="master_admin">Master Admin</option>
                            </select>
                        </div>
                        <div>
                            <label for="fld-newUser-avatar" class="text-[10px] text-crm-t3 uppercase tracking-wider">Avatar (2 chars)</label>
                                <input id="fld-newUser-avatar" wire:model="newUser.avatar" type="text" maxlength="2" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400 font-mono text-center">
                        </div>
                        <div>
                            <label for="fld-newUser-color" class="text-[10px] text-crm-t3 uppercase tracking-wider">Color</label>
                                <input id="fld-newUser-color" wire:model="newUser.color" type="color" class="w-full h-[38px] bg-crm-surface border border-crm-border rounded-lg cursor-pointer">
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-5">
                    <button wire:click="$set('showAddModal', false)" class="px-4 py-2 text-sm font-semibold text-crm-t2 bg-crm-card border border-crm-border rounded-lg hover:bg-crm-hover transition">Cancel</button>
                    <button wire:click="saveUser" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Create User</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Edit User Modal --}}
    @if(isset($editingUser) && $editingUser)
        @php $eu = $users->firstWhere('id', $editingUser); @endphp
        @if($eu)
            <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center backdrop-blur-sm" wire:click.self="$set('editingUser', null)">
                <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 mx-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-base font-bold">Edit {{ $eu->name }}</h3>
                        <button wire:click="$set('editingUser', null)" class="text-crm-t3 hover:text-crm-t1 text-lg">&times;</button>
                    </div>
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="fld-editData-name" class="text-[10px] text-crm-t3 uppercase tracking-wider">Name</label>
                                <input id="fld-editData-name" wire:model="editData.name" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                            </div>
                            <div>
                                <label for="fld-editData-username" class="text-[10px] text-crm-t3 uppercase tracking-wider">Username</label>
                                <input id="fld-editData-username" wire:model="editData.username" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="fld-editData-password" class="text-[10px] text-crm-t3 uppercase tracking-wider">Password</label>
                                <input id="fld-editData-password" wire:model="editData.password" type="text" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400 font-mono">
                            </div>
                            <div>
                                <label for="fld-editData-email" class="text-[10px] text-crm-t3 uppercase tracking-wider">Email</label>
                                <input id="fld-editData-email" wire:model="editData.email" type="email" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="fld-editData-avatar" class="text-[10px] text-crm-t3 uppercase tracking-wider">Avatar</label>
                                <input id="fld-editData-avatar" wire:model="editData.avatar" type="text" maxlength="2" class="w-full px-3 py-2 text-sm bg-crm-surface border border-crm-border rounded-lg focus:outline-none focus:border-blue-400 font-mono text-center">
                            </div>
                            <div>
                                <label for="fld-editData-color" class="text-[10px] text-crm-t3 uppercase tracking-wider">Color</label>
                                <input id="fld-editData-color" wire:model="editData.color" type="color" class="w-full h-[38px] bg-crm-surface border border-crm-border rounded-lg cursor-pointer">
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-5">
                        <button wire:click="$set('editingUser', null)" class="px-4 py-2 text-sm font-semibold text-crm-t2 bg-crm-card border border-crm-border rounded-lg hover:bg-crm-hover transition">Cancel</button>
                        <button wire:click="updateUser" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Save Changes</button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
