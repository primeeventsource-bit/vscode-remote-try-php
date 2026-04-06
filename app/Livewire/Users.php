<?php
namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Users')]
class Users extends Component
{
    public bool $showAddModal = false;
    public ?int $editingUser = null;
    public array $newUser = ['name' => '', 'email' => '', 'username' => '', 'password' => '', 'role' => 'fronter', 'avatar' => '', 'color' => '#3b82f6'];
    public array $editData = ['name' => '', 'email' => '', 'username' => '', 'password' => '', 'avatar' => '', 'color' => '#3b82f6'];

    public function saveUser()
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('master_admin', 'admin')) return;

        $this->validate([
            'newUser.name' => 'required|string|max:255',
            'newUser.username' => 'required|string|max:100|unique:users,username',
            'newUser.password' => 'required|string|min:8',
            'newUser.email' => 'required|email|max:255',
            'newUser.role' => 'required|string|in:master_admin,admin,fronter,fronter_panama,closer,closer_panama,agent',
        ]);
        $roleDefaults = [
            'master_admin' => ['view_dashboard','view_stats','view_leads','view_all_leads','assign_leads','view_pipeline','view_deals','create_deals','view_verification','toggle_charged','toggle_chargeback','view_payroll','view_users','edit_users','delete_users','view_chat','view_documents','view_spreadsheets','master_override','import_csv','add_leads','disposition_leads','upload_files','view_login_info','create_chats'],
            'admin' => ['view_dashboard','view_stats','view_leads','view_all_leads','assign_leads','view_pipeline','view_deals','create_deals','view_verification','toggle_charged','toggle_chargeback','view_payroll','view_users','edit_users','delete_users','view_chat','view_documents','view_spreadsheets','import_csv','add_leads','disposition_leads','upload_files','view_login_info','create_chats'],
            'fronter' => ['view_leads','view_pipeline','view_chat','view_documents','view_spreadsheets','disposition_leads','create_chats','view_payroll'],
            'fronter_panama' => ['view_leads','view_pipeline','view_chat','view_documents','view_spreadsheets','disposition_leads','create_chats'],
            'closer' => ['view_dashboard','view_leads','view_pipeline','view_deals','view_verification','view_chat','view_documents','view_spreadsheets','disposition_leads','create_deals','create_chats','view_login_info','view_payroll'],
        ];
        $avatar = strtoupper(collect(explode(' ', $this->newUser['name']))->map(fn($w) => substr($w, 0, 1))->join(''));
        $colors = ['#3b82f6','#10b981','#ec4899','#f59e0b','#8b5cf6','#14b8a6','#ef4444','#6366f1'];
        $newUser = User::create([
            'name' => $this->newUser['name'], 'email' => $this->newUser['email'],
            'username' => $this->newUser['username'], 'password' => Hash::make($this->newUser['password']),
            'avatar' => $this->newUser['avatar'] ?: substr($avatar, 0, 2),
            'color' => $this->newUser['color'] ?: $colors[array_rand($colors)], 'status' => 'online',
        ]);
        $newUser->role = $this->newUser['role'];
        $newUser->permissions = $roleDefaults[$this->newUser['role']] ?? $roleDefaults['fronter'];
        $newUser->save();
        $this->newUser = ['name' => '', 'email' => '', 'username' => '', 'password' => '', 'role' => 'fronter', 'avatar' => '', 'color' => '#3b82f6'];
        $this->showAddModal = false;
    }

    public function startEdit($id)
    {
        $u = User::find($id);
        if (! $u) {
            return;
        }

        $this->editingUser = $id;
        $this->editData = [
            'name' => $u->name,
            'email' => $u->email,
            'username' => $u->username,
            'password' => '',
            'avatar' => $u->avatar,
            'color' => $u->color ?: '#3b82f6',
        ];
    }
    public function cancelEdit() { $this->editingUser = null; }

    public function saveEdit()
    {
        if (! $this->editingUser) {
            return;
        }

        $data = [
            'name' => $this->editData['name'],
            'email' => $this->editData['email'],
            'username' => $this->editData['username'],
            'avatar' => $this->editData['avatar'],
            'color' => $this->editData['color'],
        ];
        if ($this->editData['password'] && strlen($this->editData['password']) >= 8) {
            $data['password'] = Hash::make($this->editData['password']);
        }
        User::where('id', $this->editingUser)->update($data);
        $this->editingUser = null;
    }

    public function editUser($id) { $this->startEdit($id); }
    public function updateUser() { $this->saveEdit(); }
    public function changeRole($id, $role) { $this->updateRole($id, $role); }
    public function removeUser($id) { $this->deleteUser($id); }

    public function updateRole($id, $role)
    {
        $currentUser = auth()->user();
        if (!$currentUser || !$currentUser->hasRole('master_admin')) return;

        $roleDefaults = ['master_admin' => ['view_dashboard','view_stats','view_leads','view_all_leads','assign_leads','view_pipeline','view_deals','create_deals','view_verification','toggle_charged','toggle_chargeback','view_payroll','view_users','edit_users','delete_users','view_chat','view_documents','view_spreadsheets','master_override','import_csv','add_leads','disposition_leads','upload_files','view_login_info','create_chats'], 'fronter' => ['view_leads','view_pipeline','view_chat','view_documents','view_spreadsheets','disposition_leads','create_chats','view_payroll'], 'fronter_panama' => ['view_leads','view_pipeline','view_chat','view_documents','view_spreadsheets','disposition_leads','create_chats'], 'closer' => ['view_dashboard','view_leads','view_pipeline','view_deals','view_verification','view_chat','view_documents','view_spreadsheets','disposition_leads','create_deals','create_chats','view_login_info','view_payroll']];
        $roleDefaults['admin'] = array_filter($roleDefaults['master_admin'], fn($p) => $p !== 'master_override');

        $target = User::find($id);
        if (!$target) return;
        $target->role = $role;
        $target->permissions = $roleDefaults[$role] ?? $roleDefaults['fronter'];
        $target->save();
    }

    public function deleteUser($id)
    {
        $currentUser = auth()->user();
        if (!$currentUser || !$currentUser->hasRole('master_admin', 'admin')) return;
        if ($id == auth()->id()) return;

        $target = User::find($id);
        if (!$target) return;
        if ($target->role === 'master_admin' && !$currentUser->hasRole('master_admin')) return;

        $target->delete();
    }

    public function render()
    {
        $currentUser = auth()->user();

        return view('livewire.users', [
            'users' => User::orderBy('name')->get(),
            'currentUser' => $currentUser,
            'isAdmin' => $currentUser?->hasPerm('edit_users') ?? false,
            'isMaster' => $currentUser?->hasRole('master_admin') ?? false,
        ]);
    }
}
