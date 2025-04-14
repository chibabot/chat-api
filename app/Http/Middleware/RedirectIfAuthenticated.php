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

        // Добавляем отладочную информацию
        \Log::info('RedirectIfAuthenticated middleware', [
            'uri' => $request->getUri(),
            'path' => $request->path(),
            'guards' => $guards,
            'is_api' => $request->is('api/*'),
            'expects_json' => $request->expectsJson()
        ]);

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                // Добавляем отладочную информацию
                \Log::info('User is authenticated, redirecting from login', [
                    'guard' => $guard,
                    'user_id' => Auth::guard($guard)->id()
                ]);
                
                // Если это API запрос, не делаем перенаправление
                if ($request->is('api/*') || $request->expectsJson()) {
                    return $next($request);
                }
                
                return redirect(RouteServiceProvider::HOME);
            }
        }

        return $next($request);
    }
} 