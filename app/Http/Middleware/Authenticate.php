<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // Добавляем отладочную информацию
        \Log::info('Authenticate middleware redirectTo', [
            'uri' => $request->getUri(),
            'path' => $request->path(),
            'is_api' => $request->is('api/*'),
            'expects_json' => $request->expectsJson(),
            'session_id' => session()->getId(),
            'auth_check' => auth()->check(),
            'cookies' => $request->cookies->all(),
            'session_driver' => config('session.driver')
        ]);
        
        // Для API-запросов возвращаем null (401 ошибка)
        if ($request->is('api/*') || $request->expectsJson()) {
            return null;
        }
        
        // Для веб-маршрутов перенаправляем на страницу логина
        return route('login');
    }
} 