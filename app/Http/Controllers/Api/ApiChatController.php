<?php

namespace App\Http\Controllers\Api;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ApiChatController extends BaseApiController
{
    /**
     * Cache tags for chat data
     */
    const CACHE_TAG = 'chats';
    const CACHE_TTL = 3600; // 1 hour in seconds

    /**
     * Получить все чаты пользователя с пагинацией.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $userId = $this->getAuthUser()->id;
        $page = $request->get('page', 1);
        $cacheKey = "user_{$userId}_chats_page_{$page}_per_page_{$perPage}";
        
        $chats = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($perPage) {
            return $this->getAuthUser()
                ->chats()
                ->withCount('messages')
                ->orderBy('last_message_time', 'desc')
                ->paginate($perPage);
        });
        
        return $this->successResponse($chats);
    }

    /**
     * Создать новый чат.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'users' => 'sometimes|array',
            'users.*' => 'exists:users,id'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Ошибка валидации', $validator->errors(), 422);
        }

        DB::beginTransaction();
        
        try {
            // Создаем новый чат
            $chat = Chat::create([
                'title' => $request->title,
                'created_by_user_id' => $this->getAuthUser()->id,
                'last_message_time' => now(),
                'last_message_preview' => "{$request->title} был успешно создан."
            ]);
            
            // Добавляем создателя как администратора чата
            $chat->users()->attach($this->getAuthUser()->id, ['is_admin' => true]);
            
            // Добавляем других пользователей, если они указаны
            if ($request->has('users') && is_array($request->users)) {
                $userIds = array_diff($request->users, [$this->getAuthUser()->id]);
                if (!empty($userIds)) {
                    $chat->users()->attach(array_fill_keys($userIds, ['is_admin' => false]));
                }
            }
            
            // Загружаем пользователей для ответа
            $chat->load(['users' => function($query) {
                $query->select('users.id', 'name', 'email', 'avatar');
            }]);
            
            DB::commit();

            // Очищаем кэш для всех пользователей в этом чате
            $this->clearChatUserCaches($chat);
            
            return $this->successResponse($chat, 'Чат успешно создан', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Не удалось создать чат', $e->getMessage(), 500);
        }
    }

    /**
     * Получить конкретный чат с его сообщениями.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id, Request $request)
    {
        $userId = $this->getAuthUser()->id;
        $perPage = $request->get('per_page', 20);
        $page = $request->get('page', 1);
        $cacheKey = "chat_{$id}_user_{$userId}_page_{$page}_per_page_{$perPage}";
        
        $result = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($id, $request, $perPage) {
            // Проверяем, является ли пользователь участником этого чата
            $chat = $this->getAuthUser()->chats()->find($id);
            
            if (!$chat) {
                return $this->errorResponse('Чат не найден или у вас нет доступа к нему', null, 404);
            }

            // Загружаем связанных пользователей и сообщения
            $chat->load([
                'users' => function($query) {
                    $query->select('users.id', 'name', 'email', 'avatar');
                }
            ]);
            
            // Получаем последние сообщения для предпросмотра
            $messages = $chat->messages()
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
                
            return $this->successResponse([
                'chat' => $chat,
                'messages' => $messages
            ]);
        });
        
        return $result;
    }

    /**
     * Обновить информацию о чате.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($id, Request $request)
    {
        // Проверяем, является ли пользователь участником и администратором этого чата
        $chat = $this->getAuthUser()->chats()->find($id);
        
        if (!$chat) {
            return $this->errorResponse('Чат не найден или у вас нет доступа к нему', null, 404);
        }
        
        // Проверяем права администратора
        $isAdmin = $chat->pivot->is_admin;
        if (!$isAdmin) {
            return $this->errorResponse('У вас нет прав для редактирования этого чата', null, 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Ошибка валидации', $validator->errors(), 422);
        }

        // Обновляем только предоставленные поля
        $updateData = array_filter($request->only('title'));
        
        if (!empty($updateData)) {
            $chat->update($updateData);
            
            // Очищаем кэш для этого чата
            $this->clearChatCaches($chat);
        }
        
        // Загружаем пользователей для ответа
        $chat->load(['users' => function($query) {
            $query->select('users.id', 'name', 'email', 'avatar');
        }]);

        return $this->successResponse($chat, 'Чат успешно обновлен');
    }

    /**
     * Удалить чат.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        // Проверяем, является ли пользователь администратором этого чата
        $chat = $this->getAuthUser()->chats()->find($id);
        
        if (!$chat) {
            return $this->errorResponse('Чат не найден или у вас нет доступа к нему', null, 404);
        }
        
        // Проверяем права администратора
        $isAdmin = $chat->pivot->is_admin;
        if (!$isAdmin) {
            return $this->errorResponse('У вас нет прав для удаления этого чата', null, 403);
        }

        DB::beginTransaction();
        
        try {
            // Сохраняем информацию о пользователях чата перед удалением
            $userIds = $chat->users()->pluck('users.id')->toArray();
            
            // Удаляем все связи с пользователями
            $chat->users()->detach();
            
            // Удаляем все сообщения чата
            $chat->messages()->delete();
            
            // Удаляем сам чат
            $chat->delete();

            DB::commit();
            
            // Очищаем кэш
            $this->clearChatCaches($id);
            foreach ($userIds as $userId) {
                $this->clearUserCaches($userId);
            }
            
            return $this->successResponse(null, 'Чат успешно удален');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Не удалось удалить чат', $e->getMessage(), 500);
        }
    }

    /**
     * Добавить пользователя в чат.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addUser($id, Request $request)
    {
        // Проверяем, является ли пользователь администратором этого чата
        $chat = $this->getAuthUser()->chats()->find($id);
        
        if (!$chat) {
            return $this->errorResponse('Чат не найден или у вас нет доступа к нему', null, 404);
        }
        
        // Проверяем права администратора
        $isAdmin = $chat->pivot->is_admin;
        if (!$isAdmin) {
            return $this->errorResponse('У вас нет прав для добавления пользователей в этот чат', null, 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'is_admin' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Ошибка валидации', $validator->errors(), 422);
        }

        // Проверяем, не находится ли пользователь уже в чате
        if ($chat->users()->where('users.id', $request->user_id)->exists()) {
            return $this->errorResponse('Пользователь уже добавлен в этот чат', null, 422);
        }

        // Добавляем пользователя в чат
        $isAdmin = $request->has('is_admin') ? $request->is_admin : false;
        $chat->users()->attach($request->user_id, ['is_admin' => $isAdmin]);
        
        // Получаем информацию о добавленном пользователе
        $addedUser = User::select('id', 'name', 'email', 'avatar')->find($request->user_id);

        return $this->successResponse([
            'user' => $addedUser,
            'is_admin' => $isAdmin
        ], 'Пользователь успешно добавлен в чат');
    }

    /**
     * Удалить пользователя из чата.
     *
     * @param  int  $id
     * @param  int  $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeUser($id, $userId)
    {
        // Проверяем, является ли пользователь администратором этого чата
        $chat = $this->getAuthUser()->chats()->find($id);
        
        if (!$chat) {
            return $this->errorResponse('Чат не найден или у вас нет доступа к нему', null, 404);
        }
        
        // Проверяем права администратора (или если пользователь удаляет сам себя)
        $isAdmin = $chat->pivot->is_admin;
        $isSelfRemoval = $userId == $this->getAuthUser()->id;
        
        if (!$isAdmin && !$isSelfRemoval) {
            return $this->errorResponse('У вас нет прав для удаления пользователей из этого чата', null, 403);
        }

        // Проверяем, есть ли пользователь в чате
        if (!$chat->users()->where('users.id', $userId)->exists()) {
            return $this->errorResponse('Пользователь не найден в этом чате', null, 404);
        }

        // Проверяем, не пытается ли обычный пользователь удалить администратора
        if (!$isSelfRemoval && !$isAdmin) {
            $targetUser = $chat->users()->where('users.id', $userId)->first();
            if ($targetUser && $targetUser->pivot->is_admin) {
                return $this->errorResponse('Вы не можете удалить администратора чата', null, 403);
            }
        }

        // Удаляем пользователя из чата
        $chat->users()->detach($userId);

        return $this->successResponse(null, 'Пользователь успешно удален из чата');
    }

    /**
     * Очищает кэш для конкретного чата
     *
     * @param  int  $chatId
     * @return void
     */
    protected function clearChatCaches($chatId)
    {
        // Find and delete all chat-related cache keys
        $this->forgetCachePattern("chat_{$chatId}_*");
    }

    /**
     * Очищает кэш для конкретного пользователя
     *
     * @param  int  $userId
     * @return void
     */
    protected function clearUserCaches($userId)
    {
        // Find and delete all user-related cache keys
        $this->forgetCachePattern("user_{$userId}_*");
    }

    /**
     * Очищает кэш для всех пользователей в конкретном чате
     *
     * @param  \App\Models\Chat  $chat
     * @return void
     */
    protected function clearChatUserCaches(Chat $chat)
    {
        // Очищаем кэш для данного чата
        $this->clearChatCaches($chat->id);
        
        // Очищаем кэш для каждого пользователя в чате
        $userIds = $chat->users()->pluck('users.id')->toArray();
        foreach ($userIds as $userId) {
            $this->clearUserCaches($userId);
        }
    }
    
    /**
     * Utility method to forget cache keys by pattern
     * This is a simple approach without regex support
     *
     * @param  string  $pattern
     * @return void
     */
    protected function forgetCachePattern($pattern)
    {
        // For file or database cache drivers that don't support tags,
        // we can only clear keys directly. This is a basic implementation.
        // For production apps, consider implementing a more robust solution
        // or switching to Redis/Memcached for better tag support.
        Cache::forget($pattern);
    }
} 