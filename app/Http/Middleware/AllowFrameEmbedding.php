<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowFrameEmbedding
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Allow embedding in iframes from any origin (Replit canvas preview).
        $response->headers->set('Content-Security-Policy', "frame-ancestors *");
        $response->headers->remove('X-Frame-Options');

        // Required when the parent page uses Cross-Origin-Embedder-Policy: require-corp.
        // Without this header the browser silently blocks the iframe load.
        $response->headers->set('Cross-Origin-Resource-Policy', 'cross-origin');

        // Ensure this page is never treated as a cross-origin isolated opener,
        // which would break navigation inside the iframe.
        $response->headers->set('Cross-Origin-Opener-Policy', 'unsafe-none');

        // Allow all cross-origin requests to load resources from this page.
        $response->headers->set('Access-Control-Allow-Origin', '*');

        return $response;
    }
}
