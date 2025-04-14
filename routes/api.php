<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Api\ApiMessageController;
use App\Http\Controllers\Api\ApiProfileController;
use App\Http\Controllers\Api\ApiChatController;
use App\Http\Controllers\JWTAuthController;
use App\Http\Controllers\AuthController;
use App\Http\Middleware\JWTAuthenticate;
use App\Http\Controllers\Api\StatusController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
// App status
Route::get('/status', StatusController::class);

// JWT Аутентификация
Route::group(['prefix' => 'auth'], function () {
    Route::post('login', [JWTAuthController::class, 'login']);
    Route::post('register', [JWTAuthController::class, 'register']);
    
    Route::group(['middleware' => JWTAuthenticate::class], function () {
        Route::post('logout', [JWTAuthController::class, 'logout']);
        Route::post('refresh', [JWTAuthController::class, 'refresh']);
    });
});

// API маршруты, защищенные JWT аутентификацией
Route::group(['middleware' => JWTAuthenticate::class], function () {
    
    // Маршруты для чатов
    Route::apiResource('chats', ApiChatController::class);
    Route::post('chats/{id}/users', [ApiChatController::class, 'addUser']);
    Route::delete('chats/{id}/users/{userId}', [ApiChatController::class, 'removeUser']);
    
    // Маршруты для сообщений
    Route::apiResource('chats.messages', ApiMessageController::class);
    
    // Маршруты для управления профилем
    Route::prefix('profile')->group(function () {
        Route::get('/', [ApiProfileController::class, 'show']);
        Route::put('/', [ApiProfileController::class, 'update']);
        Route::post('/avatar', [ApiProfileController::class, 'updateAvatar']);
        Route::delete('/avatar', [ApiProfileController::class, 'deleteAvatar']);
        Route::put('/password', [ApiProfileController::class, 'updatePassword']);
    });
    
    // Маршруты суперюзера
    Route::group(['prefix' => 'admin'], function () {
        Route::get('im', [AdminController::class, 'im']);
        Route::get('users', [AdminController::class, 'getUsers']);
        Route::get('chats', [AdminController::class, 'getChats']);
        Route::get('messages', [AdminController::class, 'getMessages']);
        Route::delete('users/{id}', [AdminController::class, 'deleteUser']);
    });
}); 