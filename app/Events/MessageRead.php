<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when one participant opens a conversation and all unread messages
 * from the other party are bulk-marked as read in a single UPDATE query.
 *
 * Replaces the old pattern of firing one MessageRead event per message,
 * which caused N broadcasts (and N DB writes) per request.
 *
 * Flutter client should listen on the private conversation channel and,
 * when this event arrives, mark all message IDs in the payload as read
 * locally without needing individual events.
 *
 * Channel: private-conversation.{conversationId}
 *
 * Payload:
 * {
 *   "conversation_id": 42,
 *   "message_ids": [101, 102, 103, 104],
 *   "read_by_user_id": 7,
 *   "read_at": "2025-07-20T14:35:00+00:00"
 * }
 */
class MessageRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int   $conversationId,
        public readonly array $messageIds,
        public readonly int   $readByUserId,
    ) {}

    /**
     * Same private channel pattern as MessageRead and NewChatMessage.
     * Flutter clients are already subscribed here — no new subscription needed.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("conversation.{$this->conversationId}"),
        ];
    }

    /**
     * Follows the noun.verb convention used by message.new and message.read.
     */
    public function broadcastAs(): string
    {
        return 'messages.read';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id'  => $this->conversationId,
            'message_ids'      => $this->messageIds,
            'read_by_user_id'  => $this->readByUserId,
            'read_at'          => now()->toIso8601String(),
        ];
    }
}