<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\ChatConversation;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversation.{id}', function ($user, $id) {
    return ChatConversation::where('id', $id)
        ->where(fn ($q) => $q->where('customer_id', $user->id)->orWhere('provider_id', $user->id))
        ->exists();
});
