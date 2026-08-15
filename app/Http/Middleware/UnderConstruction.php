<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the public application behind a polished construction screen while
 * allowing super admins to keep working and manage the setting.
 */
class UnderConstruction
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! filter_var(SystemSetting::get('under_construction_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return $next($request);
        }

        // Let unauthenticated visitors reach the normal auth flow. The home
        // route will redirect them to the login page, while the auth routes
        // remain available during construction.
        if (! $request->user()) {
            return $next($request);
        }

        // Super admins and group admins must always be able to keep working
        // and manage the application while launch work is in progress.
        if ($request->user()?->hasAnyRole(['super_admin', 'group_admin'])) {
            return $next($request);
        }

        // Keep the page itself, login, locale switching and PWA metadata
        // reachable so administrators can authenticate and visitors can use
        // the language selector.
        if ($request->routeIs('under-construction', 'login', 'logout', 'locale.switch', 'pwa.manifest')) {
            return $next($request);
        }

        return response()->view('under-construction', [
            'message' => SystemSetting::get(
                'under_construction_message',
                'We are putting the finishing touches on your experience. Please check back soon.'
            ),
            'appName' => SystemSetting::get('app_name', config('app.name')),
            'appLogo' => SystemSetting::publicUrl(SystemSetting::get('app_logo')),
            'user' => $request->user(),
        ], 503);
    }
}