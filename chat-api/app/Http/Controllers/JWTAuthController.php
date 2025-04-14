<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Validator;

class JWTAuthController extends Controller
{
    /**
     * Create a new JWTAuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Убираем двойную проверку аутентификации
        // Будем использовать только middleware из маршрутов
    }

    /**
     * Аутентификация пользователя и выдача JWT токена.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        try {
            // Добавляем отладочную информацию
            \Log::info('JWTAuthController@login attempt', [
                'email' => $request->email,
                'has_password' => !empty($request->password),
                'ip' => $request->ip(),
                'headers' => collect($request->headers->all())->only(['user-agent', 'content-type'])
            ]);

            // Валидация входящих данных
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                \Log::warning('JWTAuthController@login validation failed', [
                    'errors' => $validator->errors()->toArray()
                ]);
                
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Проверяем существование пользователя
            $user = User::where('email', $request->email)->first();
            if (!$user) {
                \Log::warning('JWTAuthController@login user not found', [
                    'email' => $request->email
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Пользователь не найден'
                ], 404);
            }
            
            // Проверяем пароль
            if (!Hash::check($request->password, $user->password)) {
                \Log::warning('JWTAuthController@login invalid password', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Неверный пароль'
                ], 401);
            }
            
            // Создаем JWT токен
            $token = JWTAuth::fromUser($user);
            
            \Log::info('JWTAuthController@login successful', [
                'user_id' => $user->id,
                'email' => $user->email,
                'token_length' => strlen($token)
            ]);
            
            // Авторизуем пользователя также в web-сессии
            // (важно для перенаправления после JWT аутентификации)
            \Illuminate\Support\Facades\Auth::guard('web')->login($user);
            
            // Проверяем, является ли запрос веб-запросом на аутентификацию (не API)
            if ($request->wantsJson()) {
                return $this->respondWithToken($token, $user);
            } else {
                // Для веб-запросов перенаправляем на страницу чатов
                \Log::info('Redirecting after JWT auth to chats', [
                    'user_id' => $user->id,
                    'auth_check_web' => \Illuminate\Support\Facades\Auth::guard('web')->check()
                ]);
                
                return redirect()->route('chats.index')
                    ->withCookie(cookie(
                        'auth_token', // имя
                        $token, // значение
                        60, // время жизни в минутах
                        null, // путь
                        null, // домен
                        false, // secure
                        true // http only
                    ));
            }

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Не удалось создать токен',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка сервера',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Регистрация нового пользователя.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        try {
            // Валидация входящих данных
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Создание пользователя
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);
            
            // Создаем JWT токен
            $token = JWTAuth::fromUser($user);
            
            return $this->respondWithToken($token, $user);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка сервера',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Выход пользователя (инвалидация токена).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        try {
            // Инвалидация текущего токена
            JWTAuth::invalidate(JWTAuth::getToken());
            
            return response()->json([
                'success' => true,
                'message' => 'Вы успешно вышли из системы'
            ]);
            
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Не удалось выйти из системы',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка сервера',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обновление токена.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        try {
            // Получаем текущий токен
            $currentToken = JWTAuth::getToken();
            
            if (!$currentToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Токен отсутствует'
                ], 401);
            }
            
            // Обновляем токен
            $newToken = JWTAuth::refresh($currentToken);
            
            // Получаем пользователя
            $user = JWTAuth::setToken($newToken)->toUser();
            
            return $this->respondWithToken($newToken, $user);
            
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Токен истек и не может быть обновлен',
                'error' => $e->getMessage()
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Токен недействителен',
                'error' => $e->getMessage()
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Не удалось обновить токен',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка сервера',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Возвращает ответ с JWT-токеном и информацией о пользователе.
     *
     * @param  string  $token
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token, $user)
    {
        $response = [
            'success' => true,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl', 60) * 60
        ];
        
        // В режиме разработки добавим информацию для отладки
        if (app()->environment('local', 'development')) {
            $tokenParts = explode('.', $token);
            $response['debug'] = [
                'token_length' => strlen($token),
                'header' => isset($tokenParts[0]) ? base64_decode($tokenParts[0]) : null,
                'how_to_use' => 'Add to your API requests: Authorization: Bearer ' . $token
            ];
        }
        
        return response()->json($response);
    }
} 