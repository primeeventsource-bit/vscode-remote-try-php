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
    public string $newChatError = '';

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
        if (!$user) {
            return false;
        }

        if ($user->hasRole('master_admin')) {
            return $this->moduleEnabled('chat.module_enabled');
        }

        return $this->moduleEnabled('chat.module_enabled') && $user->hasPerm('view_chat');
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
            $this->newChatError = '';
        }
    }

    public function createNewChat(): void
    {
        $user = auth()->user();
        if (!$this->canUseChat() || (!$user?->hasPerm('create_chats') && !$user?->hasRole('master_admin'))) {
            $this->newChatError = 'You do not have permission to create chats.';
            return;
        }

        $this->newChatError = '';
        $selectedMembers = array_values(array_unique(array_map('intval', $this->newChatMembers)));
        if (empty($selectedMembers)) {
            $this->newChatError = $this->newChatType === 'dm'
                ? 'Select one person to start a direct message.'
                : 'Select at least one member to create a group chat.';
            return;
        }

        if ($this->newChatType === 'dm') {
            $selectedMembers = [reset($selectedMembers)];
            $otherUser = User::find($selectedMembers[0]);
            if (!$otherUser) {
                $this->newChatError = 'The selected user could not be found.';
                return;
            }
            $name = $otherUser?->name ?? 'Direct Message';
        } else {
            $name = trim($this->newChatName);
            if ($name === '') {
                $this->newChatError = 'Enter a group name before creating the chat.';
                return;
            }
        }

        $members = array_merge($selectedMembers, [$user->id]);
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
        $this->newChatError = '';
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
