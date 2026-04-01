<?php

namespace App\Livewire;

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ChatWidget extends Component
{
    public ?int $selectedChat = null;
    public string $messageInput = '';
    public bool $showNewChatForm = false;
    public string $newChatType = 'dm';
    public string $newChatName = '';
    public array $newChatMembers = [];

    private function moduleEnabled(string $key, bool $default = true): bool
    {
        $raw = DB::table('crm_settings')->where('key', $key)->value('value');
        if ($raw === null) {
            return $default;
        }

        $decoded = json_decode($raw, true);
        return is_bool($decoded) ? $decoded : $default;
    }

    private function canUseChat(): bool
    {
        $user = auth()->user();
        return $this->moduleEnabled('chat.module_enabled') && ($user?->hasPerm('view_chat') ?? false);
    }

    public function selectChat(int $id): void
    {
        if (!$this->canUseChat()) {
            return;
        }

        $this->selectedChat = $id;
        $this->messageInput = '';
    }

    public function clearChat(): void
    {
        $this->selectedChat = null;
        $this->messageInput = '';
    }

    public function toggleNewChatForm(): void
    {
        if (!$this->canUseChat()) {
            return;
        }

        $this->showNewChatForm = !$this->showNewChatForm;
        if ($this->showNewChatForm) {
            $this->newChatType = 'dm';
            $this->newChatName = '';
            $this->newChatMembers = [];
        }
    }

    public function createNewChat(): void
    {
        if (!$this->canUseChat() || !auth()->user()?->hasPerm('create_chats')) {
            return;
        }

        $name = trim($this->newChatName);
        if (!$name || empty($this->newChatMembers)) {
            return;
        }

        $members = array_merge($this->newChatMembers, [auth()->id()]);
        $members = array_unique(array_filter($members));

        // Create the chat
        $chat = Chat::create([
            'name'    => $name,
            'type'    => $this->newChatType,
            'members' => $members,
        ]);

        $this->selectedChat = $chat->id;
        $this->showNewChatForm = false;
        $this->newChatName = '';
        $this->newChatMembers = [];
        $this->messageInput = '';
    }

    public function sendMessage(): void
    {
        if (!$this->canUseChat()) {
            return;
        }

        $text = trim($this->messageInput);
        if (!$text || !$this->selectedChat) {
            return;
        }

        Message::create([
            'chat_id'   => $this->selectedChat,
            'sender_id' => auth()->id(),
            'text'      => $text,
        ]);

        Chat::where('id', $this->selectedChat)->update(['updated_at' => now()]);
        $this->messageInput = '';
    }

    public function render()
    {
        if (!$this->canUseChat()) {
            return view('livewire.chat-widget', [
                'chats' => collect(),
                'messages' => collect(),
                'activeChat' => null,
                'users' => collect(),
            ]);
        }

        $user = auth()->user();

        $chats = Chat::orderBy('updated_at', 'desc')->get()->filter(function ($c) use ($user) {
            $members = is_array($c->members) ? $c->members : json_decode($c->members ?? '[]', true);
            return in_array($user->id, $members) || in_array((string) $user->id, $members);
        });

        $messages = $this->selectedChat
            ? Message::where('chat_id', $this->selectedChat)->orderBy('id')->limit(100)->get()
            : collect();

        $activeChat = $this->selectedChat ? Chat::find($this->selectedChat) : null;
        $users      = User::all()->keyBy('id');

        return view('livewire.chat-widget', compact('chats', 'messages', 'activeChat', 'users'));
    }
}
