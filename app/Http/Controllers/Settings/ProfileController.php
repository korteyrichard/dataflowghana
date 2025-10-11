<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        try {
            // Log the request data for debugging
            \Log::info('Profile update request data:', $request->all());
            
            // Ensure required fields are present
            if (!$request->has('name') || !$request->has('email')) {
                \Log::error('Missing required fields in profile update request');
                return back()->withErrors([
                    'name' => $request->has('name') ? null : 'The name field is required.',
                    'email' => $request->has('email') ? null : 'The email field is required.',
                ])->withInput();
            }
            
            $validated = $request->validated();
            \Log::info('Validated data:', $validated);
            
            $request->user()->fill($validated);

            if ($request->user()->isDirty('email')) {
                $request->user()->email_verified_at = null;
            }

            $request->user()->save();
            
            return to_route('profile.edit');
        } catch (\Exception $e) {
            \Log::error('Error updating profile: ' . $e->getMessage());
            return back()->withErrors(['error' => 'An error occurred while updating your profile.'])->withInput();
        }
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
