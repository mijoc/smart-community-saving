<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * SetLocale
 * ---------
 * Picks the active app locale on every request. Resolution order:
 *   1. Authenticated user's `locale` column (their saved preference).
 *   2. `locale` key on the session (set by the language switcher when
 *      a guest picks a language on the login page).
 *   3. `app.locale` (the framework default).
 *
 * Anything outside the supported set falls back to English so a stale
 * session value can't render an empty UI.
 */
class SetLocale
{
    public const SUPPORTED = ['en', 'rw', 'fr'];

    public function handle(Request $request, Closure $next)
    {
        $locale = null;

        if ($user = $request->user()) {
            $locale = $user->locale;
        }

        if (! $locale) {
            $locale = $request->session()->get('locale');
        }

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = config('app.locale', 'en');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
