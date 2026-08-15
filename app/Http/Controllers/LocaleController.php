<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;

/**
 * Switches the UI language. POSTed to from the flag dropdown in the
 * topbar (logged-in users) and the guest layout (login page).
 *
 *  - Authenticated users → persist on `users.locale` so it follows them
 *    across sessions and devices.
 *  - Guests → store on the session only.
 */
class LocaleController extends Controller
{
    public function switch(Request $req)
    {
        $data = $req->validate([
            'locale'      => 'required|in:'.implode(',', SetLocale::SUPPORTED),
            'redirect_to' => 'nullable|string',
        ]);

        $req->session()->put('locale', $data['locale']);

        if ($user = $req->user()) {
            $user->locale = $data['locale'];
            $user->save();
        }

        $back = $data['redirect_to'] ?? url()->previous();
        return redirect()->to($back ?: '/');
    }
}
