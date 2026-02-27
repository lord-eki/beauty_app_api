<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{ /*
    |--------------------------------------------------------------------------
    | GET /api/conversations
    |--------------------------------------------------------------------------
    | Returns all conversations for the authenticated user.
    */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = ChatConversation::with([
            'customer:id,first_name,last_name,profile_image',
            'provider:id,first_name,last_name,profile_image',
            'businessProfile:id,business_name',
            'messages' => fn($q) => $q->latest('created_at')->limit(1),
        ])
            ->where(function ($q) use ($user) {
                $q->where('customer_id', $user->id)
                  ->orWhere('provider_id', $user->id);
            })
            ->where('status', '!=', 'blocked')
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return $this->success($conversations->through(fn($c) => $this->formatConversation($c, $user->id)));
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/conversations
    |--------------------------------------------------------------------------
    | Start or reopen a conversation with a business.
    | Body: { business_profile_id }
    */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'business_profile_id' => ['required', 'integer', 'exists:business_profiles,id'],
        ]);

        $customer = $request->user();
        $business = BusinessProfile::findOrFail($request->business_profile_id);

        if ($business->user_id === $customer->id) {
            return $this->error('You cannot start a conversation with your own business.', 422);
        }

        $conversation = ChatConversation::firstOrCreate(
            ['customer_id' => $customer->id, 'business_profile_id' => $business->id],
            ['provider_id' => $business->user_id, 'status' => 'active']
        );

        if ($conversation->status === 'closed') {
            $conversation->update(['status' => 'active']);
        }

        $conversation->load([
            'customer:id,first_name,last_name,profile_image',
            'provider:id,first_name,last_name,profile_image',
            'businessProfile:id,business_name',
        ]);

        return $this->success($this->formatConversation($conversation, $customer->id), 'Conversation ready.', 201);
    }

    /*
    |--------------------------------------------------------------------------
    | GET /api/conversations/{conversation}/messages
    |--------------------------------------------------------------------------
    | Returns paginated messages. Auto-marks unread messages as read
    | and broadcasts MessageRead receipts for each.
    */
    public function messages(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);

        $messages = ChatMessage::where('conversation_id', $conversation->id)
            ->with('sender:id,first_name,last_name,profile_image')
            ->orderByDesc('created_at')
            ->paginate(30);

        // Mark unread messages from the other party as read and broadcast receipts
        $unread = ChatMessage::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $request->user()->id)
            ->where('is_read', false)
            ->get();

        foreach ($unread as $msg) {
            $msg->update(['is_read' => true, 'read_at' => now()]);
            broadcast(new MessageRead($msg))->toOthers();
        }

        return $this->success($messages->through(fn($m) => $this->formatMessage($m)));
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/conversations/{conversation}/messages
    |--------------------------------------------------------------------------
    | Send a message. Broadcasts NewChatMessage to the other participant.
    | Body: { content?, message_type? } or multipart with file
    */
    public function sendMessage(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);

        if ($conversation->status === 'blocked') {
            return $this->error('This conversation is blocked.', 403);
        }

        $request->validate([
            'content'      => ['required_without:file', 'nullable', 'string', 'max:2000'],
            'message_type' => ['sometimes', 'in:text,image,file'],
            'file'         => ['required_without:content', 'nullable', 'file', 'max:10240'],
        ]);

        $fileUrl  = null;
        $fileName = null;
        $type     = $request->message_type ?? 'text';

        if ($request->hasFile('file')) {
            $file     = $request->file('file');
            $fileName = $file->getClientOriginalName();
            $fileUrl  = $file->store('chat-files/' . $conversation->id, 'public');
            $type     = str_starts_with($file->getMimeType(), 'image/') ? 'image' : 'file';
        }

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $request->user()->id,
            'message_type'    => $type,
            'content'         => $request->content,
            'file_url'        => $fileUrl ? asset('storage/' . $fileUrl) : null,
            'file_name'       => $fileName,
            'is_read'         => false,
        ]);

        $conversation->update(['last_message_at' => now()]);

        $message->load('sender:id,first_name,last_name,profile_image');

        // Broadcast via Reverb — toOthers() skips the sender's own socket
        broadcast(new NewChatMessage($message, $conversation))->toOthers();

        return $this->success($this->formatMessage($message), 'Message sent.', 201);
    }

    /*
    |--------------------------------------------------------------------------
    | PUT /api/messages/{message}/read
    |--------------------------------------------------------------------------
    | Mark a specific message as read and broadcast the read receipt.
    */
    public function markRead(Request $request, ChatMessage $message): JsonResponse
    {
        $user         = $request->user();
        $conversation = $message->conversation;

        $this->authorizeConversation($user, $conversation);

        if ($message->sender_id === $user->id) {
            return $this->error('You cannot mark your own message as read.', 422);
        }

        if ($message->is_read) {
            return $this->success(null, 'Already marked as read.');
        }

        $message->update(['is_read' => true, 'read_at' => now()]);

        // Broadcast read receipt to the other participant
        broadcast(new MessageRead($message))->toOthers();

        return $this->success(null, 'Message marked as read.');
    }

    /*
    |--------------------------------------------------------------------------
    | POST /api/conversations/{conversation}/typing
    |--------------------------------------------------------------------------
    | Broadcast a typing / online / offline status signal.
    | No DB write — pure real-time signal via presence channel.
    | Body: { status: 'typing'|'online'|'offline' }
    |
    | Flutter should call this when:
    |   - Text field receives focus → 'online'
    |   - User is actively typing   → 'typing'  (debounce ~1.5s)
    |   - Screen is left / closed   → 'offline'
    */
    public function typing(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);

        $request->validate([
            'status' => ['required', 'in:typing,online,offline'],
        ]);

        //  Broadcast on the presence channel — toOthers() skips the caller
        broadcast(new UserOnlineStatus(
            $request->user(),
            $conversation->id,
            $request->status
        ))->toOthers();

        return $this->success(null, 'Status broadcast.');
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE /api/conversations/{conversation}
    |--------------------------------------------------------------------------
    */
    public function close(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);
        $conversation->update(['status' => 'closed']);

        return $this->success(null, 'Conversation closed.');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function authorizeConversation($user, ChatConversation $conversation): void
    {
        if ($user->id !== $conversation->customer_id && $user->id !== $conversation->provider_id) {
            abort(403, 'You do not have access to this conversation.');
        }
    }

    private function formatConversation(ChatConversation $c, int $authUserId): array
    {
        $lastMessage = $c->messages->first();
        $isCustomer  = $authUserId === $c->customer_id;
        $otherParty  = $isCustomer ? $c->provider : $c->customer;

        return [
            'id'              => $c->id,
            'status'          => $c->status,
            'business'        => [
                'id'   => $c->businessProfile?->id,
                'name' => $c->businessProfile?->business_name,
            ],
            'other_party'     => [
                'id'            => $otherParty?->id,
                'name'          => trim(($otherParty?->first_name ?? '') . ' ' . ($otherParty?->last_name ?? '')),
                'profile_image' => $otherParty?->profile_image,
            ],
            'last_message'    => $lastMessage ? [
                'content'    => $lastMessage->content,
                'type'       => $lastMessage->message_type,
                'created_at' => $lastMessage->created_at?->toIso8601String(),
                'is_mine'    => $lastMessage->sender_id === $authUserId,
            ] : null,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'created_at'      => $c->created_at?->toIso8601String(),
        ];
    }

    private function formatMessage(ChatMessage $m): array
    {
        return [
            'id'           => $m->id,
            'sender'       => [
                'id'            => $m->sender?->id,
                'name'          => trim(($m->sender?->first_name ?? '') . ' ' . ($m->sender?->last_name ?? '')),
                'profile_image' => $m->sender?->profile_image,
            ],
            'message_type' => $m->message_type,
            'content'      => $m->content,
            'file_url'     => $m->file_url,
            'file_name'    => $m->file_name,
            'is_read'      => $m->is_read,
            'read_at'      => $m->read_at?->toIso8601String(),
            'created_at'   => $m->created_at?->toIso8601String(),
        ];
    }

    private function success(mixed $data, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    private function error(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
