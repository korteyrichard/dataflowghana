<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Debug: Log the role check
        \Log::info('Role check', [
            'required_role' => $role,
            'user_role' => $user->role,
            'user_id' => $user->id,
            'user_email' => $user->email
        ]);

        // Check if user has the required role
        if (empty($user->role) || $user->role !== $role) {
            \Log::warning('Access denied - insufficient role', [
                'required_role' => $role,
                'user_role' => $user->role ?? 'NULL',
                'user_id' => $user->id
            ]);
            
            abort(403, 'Access denied. You need "' . $role . '" role to access this page.');
        }

        \Log::info('Role check passed', ['user_id' => $user->id, 'role' => $role]);

        return $next($request);
    }
}