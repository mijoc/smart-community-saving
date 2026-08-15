<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\EnsureActiveGroup;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\AllowFrameEmbedding;
use App\Http\Middleware\UnderConstruction;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust the Replit (or any) reverse proxy so Laravel honours the
        // X-Forwarded-* headers for scheme/host/port. Without this, redirects
        // and generated URLs use the internal localhost:5000 instead of the
        // public Replit domain seen by the browser.
        $middleware->trustProxies(at: '*', headers:
            \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
        );

        // Allow this app to be embedded in iframes from any origin
        // (needed for the Replit canvas preview pane to work).
        $middleware->web(prepend: [AllowFrameEmbedding::class]);

        // Pick the active UI language on every request (auth user's
        // preference > session > default).
        $middleware->web(append: [SetLocale::class]);

        // Keep the public app presentable during planned launch work. Super
        // admins bypass this gate so they can manage and disable it.
        $middleware->web(append: [UnderConstruction::class]);

        $middleware->alias([
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'app.role'           => RoleMiddleware::class,
            'active.group'       => EnsureActiveGroup::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
