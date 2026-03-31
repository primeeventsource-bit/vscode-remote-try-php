<?php
namespace App\Livewire;

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Chat')]
class ChatPage extends Component
{
    public ?int $selectedChat = null;
    public string $messageInput = '';

    public function selectChat($id) { $this->selectedChat = $id; }

    public function sendMessage()
    {
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
