<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    /**
     * Apply baseline security headers to every response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy-Report-Only' => $this->policy(),
        ];

        foreach ($defaults as $name => $value) {
            if ($response->headers->has($name)) {
                continue;
            }

            $response->headers->set($name, $value);
        }

        return $response;
    }

    /**
     * Permissive policy while rolling CSP out in report-only mode.
     */
    private function policy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "style-src 'self' 'unsafe-inline'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' http://localhost:5173 http://127.0.0.1:5173",
            "connect-src 'self' ws: wss: http://localhost:5173 http://127.0.0.1:5173",
            "media-src 'self' blob:",
        ]);
    }
}
