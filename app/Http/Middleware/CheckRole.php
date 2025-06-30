<?php
// app/Http/Middleware/CheckRole.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $user = $request->user();

        // Check if user is active
        if (!$user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Account is ' . $user->status . '. Please contact administrator.'
            ], 403);
        }

        // Check if user has required role
        if (!in_array($user->role, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions. Required role: ' . implode(' or ', $roles)
            ], 403);
        }

        return $next($request);
    }
}