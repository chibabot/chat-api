<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Auth;

class JWTAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Детальное логирование для отладки
        \Log::info('JWTAuthenticate middleware начинает проверку', [
            'has_auth_header' => $request->hasHeader('Authorization'),
            'auth_header' => $request->header('Authorization'),
            'has_cookie' => $request->cookies->has('auth_token'),
            'path' => $request->path(),
            'method' => $request->method()
        ]);

        try {
            // Проверяем наличие токена в заголовке
            if ($request->hasHeader('Authorization')) {
                $authHeader = $request->header('Authorization');
                if (strpos($authHeader, 'Bearer ') === 0) {
                    $token = substr($authHeader, 7);
                    \Log::info('Токен получен из заголовка Authorization', ['token_length' => strlen($token)]);
                    
                    JWTAuth::setToken($token);
                    $user = JWTAuth::authenticate();
                    
                    if ($user) {
                        // Устанавливаем пользователя в систему аутентификации
                        Auth::guard('api')->setUser($user);
                        
                        \Log::info('Аутентификация успешна через заголовок Authorization', [
                            'user_id' => $user->id,
                            'email' => $user->email
                        ]);
                        
                        return $next($request);
                    }
                }
            }
            
            // Если токен не найден в заголовке или аутентификация не удалась, 
            // проверяем cookie
            if ($request->cookies->has('auth_token')) {
                $token = $request->cookies->get('auth_token');
                \Log::info('Токен получен из cookie', ['token_length' => strlen($token)]);
                
                JWTAuth::setToken($token);
                $user = JWTAuth::authenticate();
                
                if ($user) {
                    // Устанавливаем пользователя в систему аутентификации
                    Auth::guard('api')->setUser($user);
                    
                    \Log::info('Аутентификация успешна через cookie', [
                        'user_id' => $user->id,
                        'email' => $user->email
                    ]);
                    
                    return $next($request);
                }
            }
            
            // Если ни один из способов не сработал, возвращаем ошибку
            \Log::warning('JWT токен не найден или недействителен');
            return response()->json([
                'success' => false,
                'message' => 'Токен не предоставлен или недействителен'
            ], 401);
            
        } catch (TokenExpiredException $e) {
            \Log::warning('Токен истек', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Срок действия токена истек'
            ], 401);
        } catch (TokenInvalidException $e) {
            \Log::warning('Токен недействителен', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false, 
                'message' => 'Токен недействителен'
            ], 401);
        } catch (JWTException $e) {
            \Log::warning('Ошибка JWT', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка токена: ' . $e->getMessage()
            ], 401);
        } catch (Exception $e) {
            \Log::error('Непредвиденная ошибка JWT аутентификации', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка аутентификации: ' . $e->getMessage()
            ], 401);
        }
    }
} 