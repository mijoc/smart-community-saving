<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:80'],
            'password' => ['required', 'string'],
        ]);

        $credentials = [
            'username' => $data['username'],
            'password' => $data['password'],
        ];

        // Accept an email as a compatibility fallback for accounts created
        // before username login was introduced.
        $authenticated = Auth::attempt($credentials, $request->boolean('remember'));
        if (! $authenticated && str_contains($data['username'], '@')) {
            $authenticated = Auth::attempt([
                'email' => $data['username'],
                'password' => $data['password'],
            ], $request->boolean('remember'));
        }

        if (! $authenticated) {
            throw ValidationException::withMessages([
                'username' => 'Those credentials do not match our records.',
            ]);
        }

        $user = $request->user();

        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'username' => 'Your account is disabled. Contact a super admin.',
            ]);
        }

        $request->session()->regenerate();

        // Resolve initial group context.
        if (! $user->isSuperAdmin()) {
            $accessible = $user->accessibleGroups();

            if ($accessible->isEmpty()) {
                Auth::logout();
                throw ValidationException::withMessages([
                    'username' => 'You are not assigned to any group yet. Please contact a super admin.',
                ]);
            }

            if ($accessible->count() === 1) {
                $request->session()->put('active_group_id', $accessible->first()->id);
            } else {
                return redirect()->route('groups.select');
            }
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        $request->session()->forget('active_group_id');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
