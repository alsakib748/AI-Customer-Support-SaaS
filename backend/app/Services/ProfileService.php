<?php
// app/Services/ProfileService.php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileService
{
    /**
     * Get user profile
     */
    public function getProfile(User $user): array
    {
        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'timezone' => $user->timezone,
            'language' => $user->language,
            'preferences' => $user->preferences,
            'is_active' => $user->is_active,
            'email_verified_at' => $user->email_verified_at,
            'last_login_at' => $user->last_login_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    /**
     * Update user profile
     */
    public function updateProfile(User $user, array $data): array
    {
        $validator = Validator::make($data, [
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'phone' => 'nullable|string|max:20',
            'timezone' => 'nullable|string|timezone',
            'language' => 'nullable|string|in:en,bn,es,fr,de,ja,zh',
            'preferences' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        // Handle preferences
        if (isset($validated['preferences'])) {
            $currentPreferences = $user->preferences ?? [];
            $validated['preferences'] = array_merge($currentPreferences, $validated['preferences']);
        }

        $user->update($validated);

        return $this->getProfile($user->fresh());
    }

    /**
     * Update user avatar
     */
    public function updateAvatar(User $user, $file): string
    {
        $validator = Validator::make(['avatar' => $file], [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Delete old avatar
        if ($user->avatar_url) {
            $oldPath = str_replace('/storage/', '', $user->avatar_url);
            Storage::disk('public')->delete($oldPath);
        }

        // Store new avatar
        $path = $file->store('avatars', 'public');
        $url = Storage::url($path);

        $user->update(['avatar_url' => $url]);

        return $url;
    }
}
