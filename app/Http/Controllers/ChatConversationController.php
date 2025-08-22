<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatConversationRequest;
use App\Http\Requests\UpdateChatConversationRequest;
use App\Models\ChatConversation;

class ChatConversationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreChatConversationRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ChatConversation $chatConversation)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateChatConversationRequest $request, ChatConversation $chatConversation)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ChatConversation $chatConversation)
    {
        //
    }
}
