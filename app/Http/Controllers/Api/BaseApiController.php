<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;

class BaseApiController extends Controller
{
    /**
     * Аутентифицированный пользователь.
     *
     * @var \App\Models\User|null
     */
    protected $user;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Не используем middleware в конструкторе, так как это может вызвать ошибки
        // В маршрутах уже указан middleware JWTAuthenticate::class
    }

    /**
     * Получить аутентифицированного пользователя.
     *
     * @return \App\Models\User|null
     */
    protected function getAuthUser()
    {
        if ($this->user) {
            return $this->user;
        }
        
        // Пробуем получить пользователя из текущей аутентификации
        $this->user = Auth::guard('api')->user();
        
        if (!$this->user) {
            // Запасной вариант: пробуем получить пользователя напрямую через JWTAuth
            try {
                if ($token = JWTAuth::getToken()) {
                    $this->user = JWTAuth::authenticate($token);
                    
                    // Если получили пользователя, устанавливаем его в Auth
                    if ($this->user) {
                        Auth::guard('api')->setUser($this->user);
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Error getting user in BaseApiController', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        // Логируем информацию о пользователе для отладки
        \Log::info('BaseApiController getAuthUser', [
            'has_user' => !is_null($this->user),
            'user_id' => $this->user ? $this->user->id : null,
            'path' => request()->path(),
            'auth_guard_check' => Auth::guard('api')->check()
        ]);
        
        return $this->user;
    }

    /**
     * Успешный ответ.
     *
     * @param  mixed  $data
     * @param  string  $message
     * @param  int  $code
     * @return \Illuminate\Http\JsonResponse
     */
    protected function successResponse($data = null, string $message = 'Операция выполнена успешно', int $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Ответ с ошибкой.
     *
     * @param  string  $message
     * @param  mixed  $errors
     * @param  int  $code
     * @return \Illuminate\Http\JsonResponse
     */
    protected function errorResponse(string $message, $errors = null, int $code = 400)
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
} 