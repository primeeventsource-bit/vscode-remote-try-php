<?php

namespace App\Livewire;

use App\Models\Chat;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ChatWidget extends Component
{
    public ?int $selectedChat = null;
    public string $messageInput = '';
    public string $chatSearch = '';
    public bool $showNewChatForm = false;
    public string $newChatType = 'dm';
    public string $newChatName = '';
    public array $newChatMembers = [];
    public string $newChatError = '';
    public bool $showDealForm = false;
    public ?int $convertLeadId = null;
    public array $dealForm = [];
    public string $dealFormAdmin = '';
    public string $dealFormError = '';

    private function getSetting(string $key, mixed $default): mixed
    {
        try {
            $raw = DB::table('crm_settings')->where('key', $key)->value('value');
            return $raw === null ? $default : json_decode($raw, true);
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public function selectChat($id): void
    {
        $this->selectedChat = (int) $id;
        $this->messageInput = '';
        $this->showNewChatForm = false;

        // isActiveChat = true → markAllAsSeen → unreadCount = 0
        $this->markChatAsSeen();
    }

    private function markChatAsSeen(): void
    {
        if (!$this->selectedChat) return;

        try {
            $now = now()->toDateTimeString();

            // Mark all incoming messages in this chat as seen
            Message::where('chat_id', $this->selectedChat)
                ->where('sender_id', '!=', auth()->id())
                ->whereNull('seen_at')
                ->update([
                    'seen_at' => $now,
                    'delivered_at' => DB::raw("COALESCE(delivered_at, '$now')"),
                ]);
        } catch (\Throwable $e) {
            // seen_at column may not exist yet
        }
    }

    /**
     * Called by wire:poll.15s.keep-alive to refresh unread counts
     * without re-rendering the full component (preserves Alpine state)
     */
    public function refreshUnreadCounts(): void
    {
        // This method just needs to exist — Livewire will re-render
        // the component which recalculates $unreadCounts in render()
    }

    public function clearChat(): void
    {
        $this->selectedChat = null;
        $this->messageInput = '';
    }

    public function startDirectCall(): void
    {
        if (!$this->selectedChat) return;
        $chat = Chat::find($this->selectedChat);
        if (!$chat || $chat->type !== 'dm') return;
        try {
            $room = \App\Services\VideoCall\VideoRoomService::createOrReuseDirectRoom($chat, auth()->user());
            if ($room) {
                \App\Services\VideoCall\VideoRoomService::joinRoom($room, auth()->user());
                $this->redirect('/video-call/' . $room->uuid);
            }
        } catch (\Throwable $e) { report($e); }
    }

    public function startDirectAudioCall(): void
    {
        if (!$this->selectedChat) return;
        $chat = Chat::find($this->selectedChat);
        if (!$chat || $chat->type !== 'dm') return;
        try {
            $room = \App\Services\VideoCall\VideoRoomService::createOrReuseDirectRoom($chat, auth()->user(), 'audio');
            if ($room) {
                \App\Services\VideoCall\VideoRoomService::joinRoom($room, auth()->user());
                $this->redirect('/video-call/' . $room->uuid);
            }
        } catch (\Throwable $e) { report($e); }
    }

    public function toggleNewChatForm(): void
    {
        $this->showNewChatForm = !$this->showNewChatForm;
        if ($this->showNewChatForm) {
            $this->selectedChat = null;
            $this->newChatType = 'dm';
            $this->newChatName = '';
            $this->newChatMembers = [];
            $this->newChatError = '';
        }
    }

    public function createNewChat(): void
    {
        $user = auth()->user();
        if (!$user) return;

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
            $name = $otherUser->name ?? 'Direct Message';

            // Prevent duplicate DMs
            $existing = Chat::where('type', 'dm')->get()->first(function ($c) use ($user, $selectedMembers) {
                $m = is_array($c->members) ? $c->members : json_decode($c->members ?? '[]', true);
                $ids = array_map('intval', array_values($m));
                sort($ids);
                $t = [$user->id, $selectedMembers[0]];
                sort($t);
                return $ids === $t;
            });
            if ($existing) {
                $this->selectedChat = $existing->id;
                $this->showNewChatForm = false;
                $this->newChatMembers = [];
                return;
            }
        } else {
            $name = trim($this->newChatName) ?: 'Group Chat';
        }

        try {
            $members = array_values(array_unique(array_merge($selectedMembers, [$user->id])));
            $chat = Chat::create([
                'name' => $name,
                'type' => $this->newChatType,
                'members' => $members,
                'created_by' => $user->id,
            ]);
            $this->selectedChat = $chat->id;
            $this->showNewChatForm = false;
            $this->newChatName = '';
            $this->newChatMembers = [];
            $this->messageInput = '';
            $this->newChatError = '';
        } catch (\Throwable $e) {
            Log::error('Chat creation failed (widget)', ['error' => $e->getMessage()]);
            $this->newChatError = 'Failed to create chat. Please try again.';
        }
    }

    public function sendMessage(): void
    {
        $text = trim($this->messageInput);
        if (!$text || !$this->selectedChat) return;

        try {
            $msgData = [
                'chat_id' => $this->selectedChat,
                'message_type' => 'text',
                'sender_id' => auth()->id(),
                'text' => $text,
            ];

            // Sender sees own message immediately
            try { $msgData['seen_at'] = now(); $msgData['delivered_at'] = now(); } catch (\Throwable $e) {}

            Message::create($msgData);
            Chat::where('id', $this->selectedChat)->update(['updated_at' => now()]);
            $this->messageInput = '';
        } catch (\Throwable $e) {
            Log::error('Message send failed (widget)', ['error' => $e->getMessage()]);
        }
    }

    public function sendGif(array $gif): void
    {
        if (!$this->selectedChat || empty($gif['url'])) return;

        try {
            Message::create([
                'chat_id' => $this->selectedChat,
                'message_type' => 'gif',
                'sender_id' => auth()->id(),
                'gif_url' => $gif['url'],
                'gif_preview_url' => $gif['preview_url'] ?? $gif['url'],
                'gif_provider' => $gif['provider'] ?? 'giphy',
                'gif_external_id' => $gif['id'] ?? null,
                'gif_title' => $gif['title'] ?? 'GIF',
            ]);
            Chat::where('id', $this->selectedChat)->update(['updated_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('GIF send failed (widget)', ['error' => $e->getMessage()]);
        }
    }

    public function openDealForm($leadId): void
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('closer', 'master_admin', 'admin')) return;

        $lead = Lead::find($leadId);
        if (!$lead) return;

        $this->convertLeadId = $lead->id;
        $this->dealForm = [
            'owner_name' => $lead->owner_name ?? '',
            'primary_phone' => $lead->phone1 ?? '',
            'secondary_phone' => $lead->phone2 ?? '',
            'email' => '',
            'mailing_address' => '',
            'city_state_zip' => trim(($lead->city ?? '') . ' ' . ($lead->st ?? '') . ' ' . ($lead->zip ?? '')),
            'resort_name' => $lead->resort ?? '',
            'resort_city_state' => $lead->resort_location ?? '',
            'fee' => '',
            'weeks' => '',
            'bed_bath' => '',
            'usage' => '',
            'asking_sale_price' => '',
            'name_on_card' => '',
            'card_type' => '',
            'bank' => '',
            'card_number' => '',
            'exp_date' => '',
            'cv2' => '',
            'billing_address' => '',
            'verification_num' => '',
            'notes' => '',
            'login_info' => '',
            'closing_date' => now()->format('Y-m-d'),
        ];
        $this->dealFormAdmin = '';
        $this->dealFormError = '';
        $this->showDealForm = true;
    }

    public function submitDeal(): void
    {
        $user = auth()->user();
        if (!$user || !$user->hasRole('closer', 'master_admin', 'admin')) return;
        if (!$this->convertLeadId) return;

        if (!$this->dealFormAdmin) {
            $this->dealFormError = 'Select an Admin to transfer the deal to.';
            return;
        }
        if (!$this->dealForm['owner_name']) {
            $this->dealFormError = 'Owner name is required.';
            return;
        }
        if (!$this->dealForm['fee']) {
            $this->dealFormError = 'Fee amount is required.';
            return;
        }
        if (empty($this->dealForm['closing_date'])) {
            $this->dealFormError = 'Closing date is required.';
            return;
        }

        $lead = Lead::find($this->convertLeadId);
        if (!$lead) return;

        try {
            $adminId = (int) $this->dealFormAdmin;

            $deal = Deal::create([
                'timestamp' => now()->format('n/j/Y'),
                'fronter' => $lead->original_fronter ?? $lead->assigned_to,
                'closer' => $user->id,
                'assigned_admin' => $adminId,
                'owner_name' => $this->dealForm['owner_name'],
                'primary_phone' => $this->dealForm['primary_phone'],
                'secondary_phone' => $this->dealForm['secondary_phone'],
                'email' => $this->dealForm['email'],
                'mailing_address' => $this->dealForm['mailing_address'],
                'city_state_zip' => $this->dealForm['city_state_zip'],
                'resort_name' => $this->dealForm['resort_name'],
                'resort_city_state' => $this->dealForm['resort_city_state'],
                'fee' => $this->dealForm['fee'] ?: 0,
                'weeks' => $this->dealForm['weeks'],
                'bed_bath' => $this->dealForm['bed_bath'],
                'usage' => $this->dealForm['usage'],
                'asking_sale_price' => $this->dealForm['asking_sale_price'],
                'name_on_card' => $this->dealForm['name_on_card'],
                'card_type' => $this->dealForm['card_type'],
                'bank' => $this->dealForm['bank'],
                'card_number' => $this->dealForm['card_number'],
                'exp_date' => $this->dealForm['exp_date'],
                'cv2' => $this->dealForm['cv2'],
                'billing_address' => $this->dealForm['billing_address'],
                'verification_num' => $this->dealForm['verification_num'],
                'notes' => $this->dealForm['notes'],
                'login_info' => $this->dealForm['login_info'],
                'closing_date' => $this->dealForm['closing_date'] ?? null,
                'status' => 'in_verification',
                'charged' => 'no',
                'charged_back' => 'no',
            ]);

            // Mark lead as converted
            $lead->update(['disposition' => 'Converted to Deal']);

            // Send auto-DM to the admin
            $adminUser = User::find($adminId);
            $senderName = $user->name ?? 'Closer';
            $dealName = $deal->owner_name ?? 'Unknown';

            // Find or create DM with admin
            $chat = Chat::where('type', 'dm')->get()->first(function ($c) use ($user, $adminId) {
                $m = is_array($c->members) ? $c->members : json_decode($c->members ?? '[]', true);
                $ids = array_map('intval', array_values($m));
                sort($ids);
                $t = [$user->id, $adminId];
                sort($t);
                return $ids === $t;
            });

            if (!$chat) {
                $chat = Chat::create([
                    'name' => $adminUser->name ?? 'Admin',
                    'type' => 'dm',
                    'members' => array_values([$user->id, $adminId]),
                    'created_by' => $user->id,
                ]);
            }

            Message::create([
                'chat_id' => $chat->id,
                'sender_id' => $user->id,
                'message_type' => 'text',
                'text' => "💼 New Deal Transferred\n{$dealName} (Deal #{$deal->id})\nFee: \${$deal->fee}\nAssigned for Verification & Charging\nTransferred by: {$senderName}",
            ]);
            $chat->update(['updated_at' => now()]);

            // Reset form
            $this->showDealForm = false;
            $this->convertLeadId = null;
            $this->dealForm = [];
            $this->dealFormAdmin = '';
            $this->dealFormError = '';

        } catch (\Throwable $e) {
            Log::error('Deal conversion failed', ['error' => $e->getMessage()]);
            $this->dealFormError = 'Failed to create deal: ' . $e->getMessage();
        }
    }

    public function closeDealForm(): void
    {
        $this->showDealForm = false;
        $this->convertLeadId = null;
        $this->dealForm = [];
        $this->dealFormAdmin = '';
        $this->dealFormError = '';
    }

    public function render()
    {
        $user = auth()->user();
        if (!$user) {
            return view('livewire.chat-widget', [
                'chats' => collect(), 'messages' => collect(), 'activeChat' => null,
                'users' => collect(), 'gifPickerSettings' => [], 'canUseGifPicker' => false,
                'currentUserId' => 0, 'unreadCounts' => collect(),
            ]);
        }

        try {
            $chats = Chat::orderBy('updated_at', 'desc')->get()->filter(function ($c) use ($user) {
                $members = is_array($c->members) ? $c->members : json_decode($c->members ?? '[]', true);
                return in_array($user->id, $members) || in_array((string) $user->id, $members);
            });
        } catch (\Throwable $e) {
            $chats = collect();
        }

        // isActiveChat: if user is viewing a chat, mark as seen
        if ($this->selectedChat) {
            $this->markChatAsSeen();
        }

        try {
            $messages = $this->selectedChat
                ? Message::where('chat_id', $this->selectedChat)->orderBy('id')->limit(100)->get()
                : collect();
        } catch (\Throwable $e) {
            $messages = collect();
        }

        $activeChat = $this->selectedChat ? Chat::find($this->selectedChat) : null;
        $users = User::all()->keyBy('id');
        $currentUserId = (int) $user->id;

        // GIF settings with safe defaults
        $canUseGifPicker = true;
        $gifPickerSettings = [
            'module_enabled' => true, 'trending_enabled' => true, 'movies_enabled' => true,
            'search_enabled' => true, 'recent_enabled' => true, 'favorites_enabled' => true,
            'provider' => 'giphy', 'results_limit' => 24,
        ];

        // Unread counts
        $unreadCounts = collect();
        if ($chats->isNotEmpty()) {
            try {
                $unreadCounts = DB::table('messages')
                    ->whereIn('chat_id', $chats->pluck('id'))
                    ->where('sender_id', '!=', $user->id)
                    ->whereNull('seen_at')
                    ->selectRaw('chat_id, count(*) as cnt')
                    ->groupBy('chat_id')
                    ->pluck('cnt', 'chat_id');
            } catch (\Throwable $e) {
                // seen_at column may not exist — that's OK
            }
        }

        $adminUsers = User::whereIn('role', ['master_admin', 'admin'])->get();

        // Search results
        $searchResults = collect();
        $searchMessageResults = collect();
        $isSearching = trim($this->chatSearch) !== '';

        if ($isSearching) {
            $q = '%' . trim($this->chatSearch) . '%';

            // Search chats by name
            $searchResults = $chats->filter(function ($c) use ($q, $users) {
                $name = $c->name ?? '';
                $members = is_array($c->members) ? $c->members : json_decode($c->members ?? '[]', true);
                // Match chat name
                if (stripos($name, trim($this->chatSearch)) !== false) return true;
                // Match member names
                foreach ($members as $mid) {
                    $mu = $users->get((int) $mid);
                    if ($mu && stripos($mu->name ?? '', trim($this->chatSearch)) !== false) return true;
                    if ($mu && stripos($mu->email ?? '', trim($this->chatSearch)) !== false) return true;
                }
                return false;
            });

            // Search messages
            try {
                $chatIds = $chats->pluck('id');
                $searchMessageResults = Message::whereIn('chat_id', $chatIds)
                    ->where('text', 'like', $q)
                    ->orderByDesc('id')
                    ->limit(20)
                    ->get();
            } catch (\Throwable $e) {
                $searchMessageResults = collect();
            }
        }

        // Check for active direct call on current DM
        $activeDirectCall = null;
        if ($activeChat && $activeChat->type === 'dm') {
            try {
                $activeDirectCall = \App\Services\VideoCall\VideoRoomService::getActiveDirectRoom($activeChat->id);
            } catch (\Throwable $e) {}
        }

        return view('livewire.chat-widget', compact(
            'chats', 'messages', 'activeChat', 'users',
            'gifPickerSettings', 'canUseGifPicker', 'currentUserId', 'unreadCounts', 'adminUsers',
            'searchResults', 'searchMessageResults', 'isSearching', 'activeDirectCall'
        ));
    }
}
