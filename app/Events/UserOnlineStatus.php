<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class UserOnlineStatus implements ShouldBroadcast
{
     use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly User   $user,
        public readonly int    $conversationId,
        public readonly string $status  
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("presence.conversation.{$this->conversationId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.status';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id'         => $this->user->id,
            'name'            => trim($this->user->first_name . ' ' . $this->user->last_name),
            'profile_image'   => $this->user->profile_image,
            'status'          => $this->status,
            'conversation_id' => $this->conversationId,
        ];
    }
}
