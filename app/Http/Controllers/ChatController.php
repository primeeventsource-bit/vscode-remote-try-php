<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * GET /api/chats
     * List chats where the current user is a member.
     * Pass ?user_id=X to specify the user.
     */
    public function index(Request $request)
    {
        try {
            $userId = $request->input('user_id');

            $query = Chat::query();

            if ($userId) {
                // members is stored as JSON array; filter where user is in members
                $query->whereRaw("members LIKE ?", ['%"' . $userId . '"%'])
                    ->orWhereRaw("members LIKE ?", ['%' . (int)$userId . '%']);
            }

            $chats = $query->orderBy('updated_at', 'desc')->get();

            return response()->json(['chats' => $chats]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/chats
     * Create a chat/group/channel
     * Body: {name, type, members, created_by}
     */
    public function store(Request $request)
    {
        try {
            $data = $request->only(['name', 'type', 'members', 'created_by']);

            if (isset($data['members']) && is_array($data['members'])) {
                $data['members'] = json_encode($data['members']);
            }

            $chat = Chat::create($data);

            return response()->json(['chat' => $chat], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/chats/{id}/messages
     * Get messages for a chat (paginated, last 100 by default)
     */
    public function messages(Request $request, $id)
    {
        try {
            $chat = Chat::find($id);

            if (!$chat) {
                return response()->json(['error' => 'Chat not found'], 404);
            }

            $limit = (int) $request->input('limit', 100);
            $before = $request->input('before'); // message ID for pagination

            $query = Message::where('chat_id', $id);

            if ($before) {
                $query->where('id', '<', $before);
            }

            $messages = $query->orderBy('id', 'desc')
                ->limit($limit)
                ->get()
                ->reverse()
                ->values();

            return response()->json(['messages' => $messages]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/chats/{id}/messages
     * Send a message
     * Body: {sender_id, text, file_url, file_name, is_system, reply_to}
     */
    public function sendMessage(Request $request, $id)
    {
        try {
            $chat = Chat::find($id);

            if (!$chat) {
                return response()->json(['error' => 'Chat not found'], 404);
            }

            $data = $request->only(['sender_id', 'text', 'file_url', 'file_name', 'is_system', 'reply_to']);
            $data['chat_id'] = $id;

            $message = Message::create($data);

            // Update the chat's updated_at timestamp
            $chat->touch();

            return response()->json(['message' => $message], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/chats/{id}/messages/{msgId}/react
     * Add a reaction: {emoji, userId}
     */
    public function react(Request $request, $id, $msgId)
    {
        try {
            $message = Message::where('chat_id', $id)->where('id', $msgId)->first();

            if (!$message) {
                return response()->json(['error' => 'Message not found'], 404);
            }

            $emoji = $request->input('emoji');
            $userId = $request->input('userId', $request->input('user_id'));

            if (!$emoji || !$userId) {
                return response()->json(['error' => 'emoji and userId are required'], 400);
            }

            $reactions = $message->reactions ?? [];

            // Find existing reaction group for this emoji
            $found = false;
            foreach ($reactions as &$reaction) {
                if ($reaction['emoji'] === $emoji) {
                    if (!in_array($userId, $reaction['users'] ?? [])) {
                        $reaction['users'][] = $userId;
                    }
                    $found = true;
                    break;
                }
            }
            unset($reaction);

            if (!$found) {
                $reactions[] = ['emoji' => $emoji, 'users' => [$userId]];
            }

            $message->update(['reactions' => $reactions]);

            return response()->json(['message' => $message]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * PUT /api/chats/{id}/pin
     * Toggle pin status
     */
    public function togglePin(Request $request, $id)
    {
        try {
            $chat = Chat::find($id);

            if (!$chat) {
                return response()->json(['error' => 'Chat not found'], 404);
            }

            $pinned = $request->has('pinned') ? (bool) $request->input('pinned') : !$chat->pinned;
            $chat->update(['pinned' => $pinned]);

            return response()->json(['chat' => $chat]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/chats/{id}
     * Delete a chat and its messages
     */
    public function destroy($id)
    {
        try {
            $chat = Chat::find($id);

            if (!$chat) {
                return response()->json(['error' => 'Chat not found'], 404);
            }

            // Delete all messages in the chat
            Message::where('chat_id', $id)->delete();

            $chat->delete();

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }
}
