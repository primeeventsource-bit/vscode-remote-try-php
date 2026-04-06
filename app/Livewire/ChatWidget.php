<?php

namespace App\Livewire;

use App\Livewire\Concerns\ChatEngine;
use App\Models\Chat;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * Floating bubble chat widget — uses shared ChatEngine trait
 * for all thread/message/unread/send/GIF/search logic.
 *
 * Only contains bubble-specific features:
 * - deal conversion form
 * - drag-to-move (blade only)
 * - clearChat (back to list)
 */
class ChatWidget extends Component
{
    use ChatEngine;

    // Shared state (used by ChatEngine trait)
    public ?int $selectedChat = null;
    public string $messageInput = '';
    public bool $showNewChatForm = false;
    public string $newChatType = 'dm';
    public string $newChatName = '';
    public array $newChatMembers = [];
    public string $newChatError = '';

    // Bubble-only: search
    public string $chatSearch = '';

    // Bubble-only: deal conversion
    public bool $showDealForm = false;
    public ?int $convertLeadId = null;
    public array $dealForm = [];
    public string $dealFormAdmin = '';
    public string $dealFormError = '';

    // ── Bubble-specific methods ──────────────────────────

    public function clearChat(): void
    {
        $this->selectedChat = null;
        $this->messageInput = '';
    }

    // ── Deal Conversion (bubble-only feature) ────────────

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
            'fee' => '', 'weeks' => '', 'bed_bath' => '', 'usage' => '',
            'asking_sale_price' => '', 'name_on_card' => '', 'card_type' => '',
            'bank' => '', 'card_number' => '', 'exp_date' => '', 'cv2' => '',
            'billing_address' => '', 'verification_num' => '', 'notes' => '',
            'login_info' => '', 'closing_date' => now()->format('Y-m-d'),
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

        if (!$this->dealFormAdmin) { $this->dealFormError = 'Select an Admin.'; return; }
        if (!$this->dealForm['owner_name']) { $this->dealFormError = 'Owner name is required.'; return; }
        if (!$this->dealForm['fee']) { $this->dealFormError = 'Fee is required.'; return; }
        if (empty($this->dealForm['closing_date'])) { $this->dealFormError = 'Closing date is required.'; return; }

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

            $lead->update(['disposition' => 'Converted to Deal']);

            // Send auto-DM to admin
            $adminUser = User::find($adminId);
            $senderName = $user->name ?? 'Closer';
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
                'text' => "💼 New Deal Transferred\n{$deal->owner_name} (Deal #{$deal->id})\nFee: \${$deal->fee}\nAssigned for Verification & Charging\nTransferred by: {$senderName}",
            ]);
            $chat->update(['updated_at' => now()]);

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

    // ── Render ───────────────────────────────────────────

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

        $chats = $this->loadThreadsForUser($user);

        if ($this->selectedChat) {
            $this->markChatAsSeen();
        }

        $messages = $this->loadMessages(100);
        $activeChat = $this->selectedChat ? Chat::find($this->selectedChat) : null;
        $users = User::all()->keyBy('id');
        $currentUserId = (int) $user->id;
        $gifPickerSettings = $this->loadGifSettings();
        $canUseGifPicker = $gifPickerSettings['module_enabled'];
        $unreadCounts = $this->computeUnreadCounts($chats, $user->id);
        $adminUsers = User::whereIn('role', ['master_admin', 'admin'])->get();

        // Search
        $search = $this->searchChats($chats, $this->chatSearch, $users);
        $searchResults = $search['chats'];
        $searchMessageResults = $search['messages'];
        $isSearching = $search['searching'];

        $activeDirectCall = null;
        if ($activeChat && $activeChat->type === 'dm') {
            try { $activeDirectCall = \App\Services\VideoCall\VideoRoomService::getActiveDirectRoom($activeChat->id); } catch (\Throwable $e) {}
        }

        return view('livewire.chat-widget', compact(
            'chats', 'messages', 'activeChat', 'users',
            'gifPickerSettings', 'canUseGifPicker', 'currentUserId', 'unreadCounts', 'adminUsers',
            'searchResults', 'searchMessageResults', 'isSearching', 'activeDirectCall'
        ));
    }
}
