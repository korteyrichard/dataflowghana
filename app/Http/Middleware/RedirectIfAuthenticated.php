<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();
                
                // Log the redirect attempt
                \Log::info('RedirectIfAuthenticated middleware redirect', [
                    'user_id' => $user->id,
                    'role' => $user->role,
                    'current_url' => $request->url(),
                    'intended_url' => session()->get('url.intended')
                ]);
                
                return redirect()->to(match($user->role) {
                    'admin' => route('admin.dashboard'),
                    default => route('dashboard')
                });
            }
        }

        return $next($request);
    }
}