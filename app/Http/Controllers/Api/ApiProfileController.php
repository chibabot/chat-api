<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ApiProfileController extends BaseApiController
{
    /**
     * Получить профиль текущего пользователя.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show()
    {
        return $this->successResponse($this->getAuthUser());
    }

    /**
     * Обновить профиль пользователя.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:users,email,'.$this->getAuthUser()->id,
            'bio' => 'sometimes|nullable|string|max:500',
            'phone' => 'sometimes|nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Ошибка валидации', $validator->errors(), 422);
        }

        $user = $this->getAuthUser();
        
        // Обновляем только предоставленные поля
        $updateData = $validator->validated();
        $user->update($updateData);

        return $this->successResponse($user, 'Профиль успешно обновлен');
    }

    /**
     * Обновить аватар пользователя.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateAvatar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Ошибка валидации', $validator->errors(), 422);
        }

        $user = $this->getAuthUser();

        // Удаляем старый аватар, если он есть
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Сохраняем новый аватар
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->avatar = $path;
        $user->save();

        return $this->successResponse([
            'user' => $user,
            'avatar_url' => asset('storage/'.$path)
        ], 'Аватар успешно обновлен');
    }

    /**
     * Удалить аватар пользователя.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAvatar()
    {
        $user = $this->getAuthUser();

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->avatar = null;
        $user->save();

        return $this->successResponse($user, 'Аватар успешно удален');
    }

    /**
     * Обновить пароль пользователя.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Ошибка валидации', $validator->errors(), 422);
        }

        $user = $this->getAuthUser();

        // Проверяем правильность текущего пароля
        if (!Hash::check($request->current_password, $user->password)) {
            return $this->errorResponse('Текущий пароль указан неверно', null, 422);
        }

        // Обновляем пароль
        $user->password = Hash::make($request->password);
        $user->save();

        return $this->successResponse(null, 'Пароль успешно обновлен');
    }
} 