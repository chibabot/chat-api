<?php

namespace App\Http\Controllers\Api;

use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class ApiMessageController extends BaseApiController
{
    /**
     * Cache tags for message data
     */
    const CACHE_TAG = 'messages';
    const CACHE_TTL = 1800; // 30 minutes in seconds

    /**
     * Получить сообщения для конкретного чата с пагинацией.
     *
     * @param  int  $chatId
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index($chatId, Request $request)
    {
        // Проверяем, является ли пользователь участником этого чата
        $chat = $this->getAuthUser()->chats()->find($chatId);
        
        if (!$chat) {
            return $this->errorResponse('Чат не найден или у вас нет доступа к нему', null, 404);
        }

        $perPage = $request->get('per_page', 50);
        $orderBy = $request->get('order_by', 'desc');
        $userId = $this->getAuthUser()->id;
        $page = $request->get('page', 1);
        
        $cacheKey = "chat_{$chatId}_messages_page_{$page}_per_page_{$perPage}_order_{$orderBy}_user_{$userId}";
        
        $result = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($chatId, $perPage, $orderBy) {
            $messages = Message::where('chat_id', $chatId)
                ->with(['user' => function($query) {
                    $query->select('id', 'name', 'email');
                }])
                ->orderBy('created_at', $orderBy)
                ->paginate($perPage);

            return [
                'current_page' => $messages->currentPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
                'last_page' => $messages->lastPage(),
                'data' => $messages->items(),
            ];
        });

        return $this->successResponse($result);
    }

    /**
     * Создать новое сообщение в чате.
     *
     * @param  int  $chatId
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store($chatId, Request $request)
    {
        // Проверяем, является ли пользователь участником этого чата
        $chat = $this->getAuthUser()->chats()->find($chatId);
        
        if (!$chat) {
            return $this->errorResponse('Чат не найден или у вас нет доступа к нему', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Ошибка валидации', $validator->errors(), 422);
        }

        $message = Message::create([
            'chat_id' => $chatId,
            'user_id' => $this->getAuthUser()->id,
            'content' => $request->content,
        ]);
        
        // Обновляем время последнего сообщения в чате
        $chat->update([
            'last_message_time' => now(),
        ]);
        
        // Загружаем отношения пользователя для ответа
        $message->load('user');
        
        // Очищаем кэш сообщений для этого чата и кэш самого чата
        $this->clearChatMessageCaches($chatId);

        return $this->successResponse($message, 'Сообщение успешно отправлено', 201);
    }

    /**
     * Получить конкретное сообщение.
     *
     * @param  int  $chatId
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($chatId, $id)
    {
        // Проверяем, является ли пользователь участником этого чата
        $chat = $this->getAuthUser()->chats()->find($chatId);
        
        if (!$chat) {
            return $this->errorResponse('Чат не найден или у вас нет доступа к нему', null, 404);
        }
        
        $userId = $this->getAuthUser()->id;
        $cacheKey = "chat_{$chatId}_message_{$id}_user_{$userId}";
        
        $message = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($chatId, $id) {
            return Message::where('chat_id', $chatId)
                ->where('id', $id)
                ->with('user')
                ->first();
        });

        if (!$message) {
            return $this->errorResponse('Сообщение не найдено', null, 404);
        }

        return $this->successResponse($message);
    }

    /**
     * Обновить существующее сообщение.
     * Только автор сообщения может обновить его.
     *
     * @param  int  $chatId
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($chatId, $id, Request $request)
    {
        // Проверяем, является ли пользователь участником этого чата
        $chat = $this->getAuthUser()->chats()->find($chatId);
        
        if (!$chat) {
            return $this->errorResponse('Чат не найден или у вас нет доступа к нему', null, 404);
        }
        
        $message = Message::where('chat_id', $chatId)
            ->where('id', $id)
            ->first();

        if (!$message) {
            return $this->errorResponse('Сообщение не найдено', null, 404);
        }
        
        // Только автор может редактировать сообщение
        if ($message->user_id !== $this->getAuthUser()->id) {
            return $this->errorResponse('Вы можете редактировать только свои сообщения', null, 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Ошибка валидации', $validator->errors(), 422);
        }

        $message->update([
            'content' => $request->content,
            'is_edited' => true
        ]);
        
        // Загружаем отношения пользователя для ответа
        $message->load('user');
        
        // Очищаем кэши
        $this->clearMessageCaches($chatId, $id);

        return $this->successResponse($message, 'Сообщение успешно обновлено');
    }

    /**
     * Удалить сообщение.
     * Только автор или администратор чата может удалить сообщение.
     *
     * @param  int  $chatId
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($chatId, $id)
    {
        // Проверяем, является ли пользователь участником этого чата
        $userChat = $this->getAuthUser()->chats()->find($chatId);
        
        if (!$userChat) {
            return $this->errorResponse('Чат не найден или у вас нет доступа к нему', null, 404);
        }
        
        $message = Message::where('chat_id', $chatId)
            ->where('id', $id)
            ->first();

        if (!$message) {
            return $this->errorResponse('Сообщение не найдено', null, 404);
        }
        
        // Проверяем, имеет ли пользователь права на удаление сообщения
        $isAdmin = $userChat->pivot->is_admin;
        $isAuthor = $message->user_id == $this->getAuthUser()->id;
        
        if (!$isAdmin && !$isAuthor) {
            return $this->errorResponse('У вас нет прав для удаления этого сообщения', null, 403);
        }

        $message->delete();
        
        // Очищаем кэши
        $this->clearMessageCaches($chatId, $id);
        
        return $this->successResponse(null, 'Сообщение успешно удалено');
    }
    
    /**
     * Очищает кэш для конкретного сообщения и связанных с ним кэшей
     *
     * @param  int  $chatId
     * @param  int  $messageId
     * @return void
     */
    protected function clearMessageCaches($chatId, $messageId)
    {
        // Очищаем кэш конкретного сообщения
        $cacheKey = "chat_{$chatId}_message_{$messageId}_*";
        Cache::forget($cacheKey);
        
        // Очищаем кэш сообщений чата, т.к. список сообщений изменился
        $this->clearChatMessageCaches($chatId);
    }
    
    /**
     * Очищает кэш списка сообщений для конкретного чата
     *
     * @param  int  $chatId
     * @return void
     */
    protected function clearChatMessageCaches($chatId)
    {
        // Очищаем кэш сообщений для данного чата
        $cacheKey = "chat_{$chatId}_messages_*";
        Cache::forget($cacheKey);
        
        // Также нужно очистить кэш самого чата, т.к. в нем могут быть последние сообщения
        $chatCacheKey = "chat_{$chatId}_*";
        Cache::forget($chatCacheKey);
    }
} 