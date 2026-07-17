<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Kioku\Services\CleanupUserKiokuAudioService;
use App\Domain\Yoyu\Money\Services\CleanupUserYoyuMoneyFilesService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
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
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'emailVerified' => $request->user()->hasVerifiedEmail(),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     *
     * Kioku audio originals and Yoyu Money import files are removed from
     * private storage first (while DB path metadata still exists). FK
     * cascade would otherwise drop the rows without firing Eloquent
     * deleted events, leaving orphan files.
     */
    public function destroy(
        ProfileDeleteRequest $request,
        CleanupUserKiokuAudioService $cleanupKiokuAudio,
        CleanupUserYoyuMoneyFilesService $cleanupYoyuMoneyFiles,
    ): RedirectResponse {
        $user = $request->user();

        $cleanupKiokuAudio->deleteForUser($user);
        $cleanupYoyuMoneyFiles->deleteForUser($user);

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
