<?php
namespace App\Livewire;

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Chat')]
class ChatPage extends Component
{
    public ?int $selectedChat = null;
    public string $messageInput = '';

    private function moduleEnabled(string $key, bool $default = true): bool
    {
        $raw = DB::table('crm_settings')->where('key', $key)->value('value');
        if ($raw === null) {
            return $default;
        }

        $decoded = json_decode($raw, true);
        return is_bool($decoded) ? $decoded : $default;
    }

    private function canAccessChat(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        if ($user->hasRole('master_admin')) {
            return $this->moduleEnabled('chat.module_enabled');
        }

        return $this->moduleEnabled('chat.module_enabled') && $user->hasPerm('view_chat');
    }

    public function mount(): void
    {
        if (!$this->canAccessChat()) {
            $this->redirectRoute('dashboard');
            session()->flash('error', 'Chat module is disabled or you do not have access.');
        }
    }

    public function selectChat($id) { $this->selectedChat = $id; }

    public function newChat() {
        return redirect()->route('dashboard')->with('info', 'Chat creation feature coming soon');
    }

    public function sendMessage()
    {
        if (!$this->canAccessChat()) {
            return;
        }

        if (!$this->messageInput || !$this->selectedChat) return;
        Message::create([
            'chat_id' => $this->selectedChat,
            'sender_id' => auth()->id(),
            'text' => $this->messageInput,
        ]);
        Chat::where('id', $this->selectedChat)->update(['updated_at' => now()]);
        $this->messageInput = '';
    }

    public function render()
    {
        $user = auth()->user();
        $chats = Chat::orderBy('updated_at', 'desc')->get()->filter(function($c) use ($user) {
            $members = is_array($c->members) ? $c->members : json_decode($c->members ?? '[]', true);
            return in_array($user->id, $members) || in_array((string) $user->id, $members);
        });

        $messages = $this->selectedChat
            ? Message::where('chat_id', $this->selectedChat)->orderBy('id')->limit(200)->get()
            : collect();

        $activeChat = $this->selectedChat ? Chat::find($this->selectedChat) : null;
        $users = User::all()->keyBy('id');

        return view('livewire.chat-page', compact('chats', 'messages', 'activeChat', 'users'));
    }
}
