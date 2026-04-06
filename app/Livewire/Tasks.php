<?php
namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Tasks')]
class Tasks extends Component
{
    use WithPagination;

    public string $tab = 'my';
    public int $perPage = 25;
    public ?int $selectedTask = null;

    public function updatedTab() { $this->resetPage(); }
    public function updatedPerPage() { $this->resetPage(); }
    public bool $showCreate = false;
    public string $newNote = '';
    public array $taskForm = ['title' => '', 'type' => 'notes', 'assigned_to' => '', 'client_name' => '', 'priority' => 'medium', 'due_date' => ''];

    public function createTask()
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('master_admin', 'admin')) return;
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
        $user = auth()->user();
        if (!$user) return;
        // Only assigned user or admins can add notes
        if ($task->assigned_to !== $user->id && !$user->hasRole('master_admin', 'admin')) return;
        $notes = json_decode($task->notes ?? '[]', true);
        $notes[] = ['text' => $this->newNote, 'by' => auth()->id(), 'time' => now()->format('M j, Y - g:i A')];
        DB::table('tasks')->where('id', $this->selectedTask)->update(['notes' => json_encode($notes)]);
        $this->newNote = '';
    }

    public string $completionNote = '';
    public bool $taskJustCompleted = false;

    public function completeTask($id)
    {
        $note = trim($this->completionNote);
        if ($note === '') {
            session()->flash('task_error', 'A note is required before completing this task.');
            return;
        }

        $task = DB::table('tasks')->where('id', $id)->first();
        if (!$task) return;
        $user = auth()->user();
        if (!$user) return;
        if ($task->assigned_to !== $user->id && !$user->hasRole('master_admin', 'admin')) return;

        // Append completion note to existing notes
        $notes = json_decode($task->notes ?? '[]', true);
        $notes[] = [
            'text' => $note,
            'by' => auth()->id(),
            'time' => now()->format('M j, Y - g:i A'),
        ];

        $updateData = [
            'status' => 'completed',
            'completed_at' => now()->format('M j, Y - g:i A'),
            'notes' => json_encode($notes),
        ];

        if (\Schema::hasColumn('tasks', 'completed_by_user_id')) {
            $updateData['completed_by_user_id'] = auth()->id();
        }

        DB::table('tasks')->where('id', $id)->update($updateData);

        $this->completionNote = '';
        $this->taskJustCompleted = true;

        // Reset green state after 3 seconds via frontend
    }

    public function reopenTask($id)
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('master_admin', 'admin')) return;
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

        $tasksPaginated = $query->orderBy('id', 'desc')->paginate($this->perPage);
        // Transform paginated items to add decoded notes
        $tasksPaginated->getCollection()->transform(fn($t) => (object) array_merge((array) $t, ['notes' => json_decode($t->notes ?? '[]', true)]));
        $tasks = $tasksPaginated;

        $users = User::all()->keyBy('id');
        $activeTask = $this->selectedTask ? DB::table('tasks')->where('id', $this->selectedTask)->first() : null;
        if ($activeTask) $activeTask->notes = json_decode($activeTask->notes ?? '[]', true);

        // Single aggregation query for tab counts
        $countBase = DB::table('tasks');
        if (!$isAdmin) $countBase->where('assigned_to', $user->id);
        $countRows = (clone $countBase)->selectRaw("
            SUM(CASE WHEN assigned_to = ? AND status = 'open' THEN 1 ELSE 0 END) as my_ct,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_ct,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_ct,
            COUNT(*) as all_ct
        ", [$user->id])->first();
        $myCt = (int) ($countRows->my_ct ?? 0);
        $openCt = (int) ($countRows->open_ct ?? 0);
        $completedCt = (int) ($countRows->completed_ct ?? 0);
        $allCt = (int) ($countRows->all_ct ?? 0);

        return view('livewire.tasks', compact('tasks', 'users', 'activeTask', 'isAdmin', 'myCt', 'openCt', 'completedCt', 'allCt'));
    }
}
