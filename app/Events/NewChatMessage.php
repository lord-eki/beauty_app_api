<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\ChatMessage;
use App\Models\ChatConversation;

class NewChatMessage implements ShouldBroadcast
{
   use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ChatMessage      $message,
        public readonly ChatConversation $conversation
    ) {}

    /**
     * Broadcast on a private channel scoped to the conversation.
     * Both participants are authorised in channels.php.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("conversation.{$this->conversation->id}"),
        ];
    }

    /**
     * Custom event name received by the Flutter/JS client.
     */
    public function broadcastAs(): string
    {
        return 'message.new';
    }

    /**
     * Payload sent to the client — keep it lean.
     */
    public function broadcastWith(): array
    {
        $sender = $this->message->sender;

        return [
            'id'              => $this->message->id,
            'conversation_id' => $this->conversation->id,
            'sender'          => [
                'id'            => $sender?->id,
                'name'          => trim(($sender?->first_name ?? '') . ' ' . ($sender?->last_name ?? '')),
                'profile_image' => $sender?->profile_image,
            ],
            'message_type'    => $this->message->message_type,
            'content'         => $this->message->content,
            'file_url'        => $this->message->file_url,
            'file_name'       => $this->message->file_name,
            'is_read'         => false,
            'created_at'      => $this->message->created_at?->toIso8601String(),
        ];
    }

    /**
     * Only queue the broadcast job if the message was persisted.
     */
    public function broadcastWhen(): bool
    {
        return $this->message->exists;
    }
}
