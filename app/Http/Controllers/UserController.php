<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateProfileImageRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request) {}

    /**
     * Display the specified resource.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new UserResource($request->user()),
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request): JsonResponse
    {
        $user = $request->user();


        $user->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => new UserResource($user->fresh()),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function uploadAvatar(UpdateProfileImageRequest $request): JsonResponse
    {
        $user = $request->user();

        // Delete old profile image if exists
        if ($user->profile_image) {
            Storage::disk('public')->delete($user->profile_image);
        }

        // Store new image
        $image = $request->file('profile_image');
        $filename = 'avatars/'.Str::uuid().'.'.$image->getClientOriginalExtension();
        $path = $image->storeAs('', $filename, 'public');

        // Update user record
        $user->update(['profile_image' => $path]);

        return response()->json([
            'success' => true,
            'message' => 'Profile image uploaded successfully',
            'data' => [
                'profile_image' => asset('storage/'.$path),
                'user' => new UserResource($user->fresh()),
            ],
        ], 200);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->profile_image) {
            return response()->json([
                'success' => false,
                'message' => 'No profile image to delete',
            ], 404);
        }

        // Delete image from storage
        Storage::disk('public')->delete($user->profile_image);

        // Update user record
        $user->update(['profile_image' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Profile image deleted successfully',
            'data' => new UserResource($user->fresh()),
        ], 200);
    }
}