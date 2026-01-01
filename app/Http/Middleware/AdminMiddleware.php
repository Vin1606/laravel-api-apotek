<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     * Hanya admin yang bisa mengakses route yang dilindungi middleware ini
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya admin yang bisa mengakses fitur ini.',
            ], 403);
        }

        return $next($request);
    }
}
