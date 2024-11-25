<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Enums\UserRole;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        // If no roles are specified, allow access
        if (empty($roles)) {
            return $next($request);
        }

        try {
            // Get user role using the User model's cast
            $userRole = $request->user()->role;
            
            // Admin always has access
            if ($userRole === UserRole::ADMIN) {
                return $next($request);
            }

            // Check if user has any of the required roles
            $hasRole = collect($roles)->contains(function ($role) use ($userRole) {
                return $userRole->value === $role;
            });

            if (!$hasRole) {
                return response()->json([
                    'message' => 'Access denied. Required roles: ' . implode(', ', $roles)
                ], 403);
            }

            return $next($request);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error processing user role'
            ], 500);
        }
    }
}