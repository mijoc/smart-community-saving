<?php

namespace App\Http\Controllers;

use App\Support\PublicStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user()->load('member');
        return view('profile.edit', compact('user'));
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $rules = [
            'name'   => ['required', 'string', 'max:255'],
            'email'  => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'  => ['nullable', 'string', 'max:30'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ];

        if ($user->canChangeUsername()) {
            $rules['username'] = ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)];
        }

        $data = $request->validate($rules);

        if (! $user->canChangeUsername()) {
            unset($data['username']);
        }

        if ($request->hasFile('avatar')) {
            $oldPaths = array_unique(array_filter([
                $user->avatar_path,
                $user->member?->photo_path,
            ]));

            $path = PublicStorage::store($request->file('avatar'), 'avatars');
            $data['avatar_path'] = $path;

            foreach ($oldPaths as $old) {
                PublicStorage::delete($old);
            }
        }
        unset($data['avatar']);

        $user->update($data);

        // Mirror name / phone / email / photo to the member profile if linked.
        if ($user->member) {
            $memberData = [
                'phone' => $data['phone'] ?? $user->member->phone,
                'email' => $data['email'],
            ];
            if (isset($data['avatar_path'])) {
                $memberData['photo_path'] = $data['avatar_path'];
            }
            $user->member->update($memberData);
        }

        return back()->with('status', 'Profile updated.');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        return back()->with('status', 'Password changed successfully.');
    }
}
