<?php
namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Tasks')]
class Tasks extends Component
{
    public string $tab = 'my';
    public ?int $selectedTask = null;
    public bool $showCreate = false;
    public string $newNote = '';
    public array $taskForm = ['title' => '', 'type' => 'notes', 'assigned_to' => '', 'client_name' => '', 'priority' => 'medium', 'due_date' => ''];

    public function createTask()
    {
        if (!$this->taskForm['title'] || !$this->taskForm['assigned_to']) return;
        DB::table('tasks')->insert([
            'title' => $this->taskForm['title'], 'type' => $this->taskForm['type'],
            'assigned_to' => $this->taskForm['assigned_to'], 'created_by' => auth()->id(),
            'client_name' => $this->taskForm['client_name'], 'priority' => $this->taskForm['priority'],
            'due_date' => $this->taskForm['due_date'] ?: null, 'status' => 'open',
            'notes' => json_encode([['text' => 'Task created', 'by' => auth()->id(), 'time' => now()->format('M j, Y - g:i A')]]),
        ]);
        $this->taskForm = ['title' => '', 'type' => 'notes', 'assigned_to' => '', 'client_name' => '', 'priority' => 'medium', 'due_date' => ''];
        $this->showCreate = false;
    }

    public function addNote()
    {
        if (!$this->newNote || !$this->selectedTask) return;
        $task = DB::table('tasks')->where('id', $this->selectedTask)->first();
        if (!$task) return;
        $notes = json_decode($task->notes ?? '[]', true);
        $notes[] = ['text' => $this->newNote, 'by' => auth()->id(), 'time' => now()->format('M j, Y - g:i A')];
        DB::table('tasks')->where('id', $this->selectedTask)->update(['notes' => json_encode($notes)]);
        $this->newNote = '';
    }

    public function completeTask($id)
    {
        DB::table('tasks')->where('id', $id)->update(['status' => 'completed', 'completed_at' => now()->format('M j, Y - g:i A')]);
    }

    public function reopenTask($id)
    {
        DB::table('tasks')->where('id', $id)->update(['status' => 'open', 'completed_at' => null]);
    }

    public function selectTask($id) { $this->selectedTask = $this->selectedTask === $id ? null : $id; }

    public function render()
    {
        $user = auth()->user();
        $isAdmin = $user->hasPerm('view_all_leads');
        $query = DB::table('tasks');

        if ($this->tab === 'my') $query->where('assigned_to', $user->id)->where('status', 'open');
        elseif ($this->tab === 'open') { if (!$isAdmin) $query->where('assigned_to', $user->id); $query->where('status', 'open'); }
        elseif ($this->tab === 'completed') { if (!$isAdmin) $query->where('assigned_to', $user->id); $query->where('status', 'completed'); }
        else { if (!$isAdmin) $query->where('assigned_to', $user->id); }

        $tasks = $query->orderBy('id', 'desc')->get()->map(fn($t) => (object) array_merge((array) $t, ['notes' => json_decode($t->notes ?? '[]', true)]));
        $users = User::all()->keyBy('id');
        $activeTask = $this->selectedTask ? DB::table('tasks')->where('id', $this->selectedTask)->first() : null;
        if ($activeTask) $activeTask->notes = json_decode($activeTask->notes ?? '[]', true);

        $myCt = DB::table('tasks')->where('assigned_to', $user->id)->where('status', 'open')->count();
        $openCt = $isAdmin ? DB::table('tasks')->where('status', 'open')->count() : $myCt;
        $completedCt = $isAdmin ? DB::table('tasks')->where('status', 'completed')->count() : DB::table('tasks')->where('assigned_to', $user->id)->where('status', 'completed')->count();
        $allCt = $isAdmin ? DB::table('tasks')->count() : DB::table('tasks')->where('assigned_to', $user->id)->count();

        return view('livewire.tasks', compact('tasks', 'users', 'activeTask', 'isAdmin', 'myCt', 'openCt', 'completedCt', 'allCt'));
    }
}
