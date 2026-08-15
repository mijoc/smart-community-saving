<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user()->load('member');
        $prefs = $user->preferences ?? [];
        return view('settings.edit', compact('user', 'prefs'));
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'locale'                      => ['required', 'in:en,rw,fr'],
            'notify_activity'             => ['nullable', 'boolean'],
            'notify_contributions'        => ['nullable', 'boolean'],
            'notify_loans'                => ['nullable', 'boolean'],
            'notify_announcements'        => ['nullable', 'boolean'],
            'show_phone_in_directory'     => ['nullable', 'boolean'],
        ]);

        $user->locale = $data['locale'];
        $user->preferences = [
            'notify_activity'         => (bool) ($data['notify_activity']         ?? false),
            'notify_contributions'    => (bool) ($data['notify_contributions']    ?? false),
            'notify_loans'            => (bool) ($data['notify_loans']            ?? false),
            'notify_announcements'    => (bool) ($data['notify_announcements']    ?? false),
            'show_phone_in_directory' => (bool) ($data['show_phone_in_directory'] ?? false),
        ];
        $user->save();

        // Persist locale in session too so the current page reflects the change immediately.
        session(['locale' => $data['locale']]);
        app()->setLocale($data['locale']);

        return back()->with('status', __('Settings saved.'));
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('status', __('Password changed successfully.'));
    }
}
