<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Cache;

class AdminController extends Controller
{
    /**
     * Cache constants
     */
    const ADMIN_CACHE_TTL = 600; // 10 minutes in seconds
    const ADMIN_CACHE_TAG = 'admin';
    
    /**
     * Create a new AdminController instance.
     * Only admins should access these routes.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    /**
     * Get the authenticated user
     * 
     * @return \App\Models\User
     */
    protected function getAuthUser()
    {
        return JWTAuth::parseToken()->authenticate();
    }
    
    /**
     * Admin messenger interface.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function im()
    {
        $user = $this->getAuthUser();
        
        // Check if user is admin
        if (!$user->is_admin) {
            return response()->json(['error' => 'Unauthorized. Admin access required'], 403);
        }
        
        $cacheKey = "admin_dashboard_stats";
        
        $stats = Cache::remember($cacheKey, self::ADMIN_CACHE_TTL, function () {
            return [
                'total_users' => User::count(),
                'total_chats' => Chat::count(),
                'total_messages' => Message::count(),
                'active_users_today' => User::where('last_activity_at', '>=', now()->subDay())->count(),
                'new_users_week' => User::where('created_at', '>=', now()->subWeek())->count(),
                'new_messages_today' => Message::where('created_at', '>=', now()->subDay())->count(),
            ];
        });
        
        return response()->json([
            'stats' => $stats
        ]);
    }
    
    /**
     * Get all users (with pagination).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUsers(Request $request)
    {
        $user = $this->getAuthUser();
        
        // Check if user is admin
        if (!$user->is_admin) {
            return response()->json(['error' => 'Unauthorized. Admin access required'], 403);
        }
        
        $perPage = $request->per_page ?? 20;
        $page = $request->get('page', 1);
        $cacheKey = "admin_users_page_{$page}_per_page_{$perPage}";
        
        $users = Cache::remember($cacheKey, self::ADMIN_CACHE_TTL, function () use ($perPage) {
            return User::paginate($perPage);
        });
        
        return response()->json($users);
    }
    
    /**
     * Get all chats (with pagination).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getChats(Request $request)
    {
        $user = $this->getAuthUser();
        
        // Check if user is admin
        if (!$user->is_admin) {
            return response()->json(['error' => 'Unauthorized. Admin access required'], 403);
        }
        
        $perPage = $request->per_page ?? 20;
        $page = $request->get('page', 1);
        $cacheKey = "admin_chats_page_{$page}_per_page_{$perPage}";
        
        $chats = Cache::remember($cacheKey, self::ADMIN_CACHE_TTL, function () use ($perPage) {
            return Chat::with('creator')
                ->paginate($perPage);
        });
        
        return response()->json($chats);
    }
    
    /**
     * Get all messages (with pagination).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMessages(Request $request)
    {
        $user = $this->getAuthUser();
        
        // Check if user is admin
        if (!$user->is_admin) {
            return response()->json(['error' => 'Unauthorized. Admin access required'], 403);
        }
        
        $perPage = $request->per_page ?? 20;
        $page = $request->get('page', 1);
        $cacheKey = "admin_messages_page_{$page}_per_page_{$perPage}";
        
        $messages = Cache::remember($cacheKey, self::ADMIN_CACHE_TTL, function () use ($perPage) {
            return Message::with(['user', 'chat'])
                ->paginate($perPage);
        });
        
        return response()->json($messages);
    }
    
    /**
     * Delete a user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteUser($id)
    {
        $adminUser = $this->getAuthUser();
        
        // Check if user is admin
        if (!$adminUser->is_admin) {
            return response()->json(['error' => 'Unauthorized. Admin access required'], 403);
        }
        
        // Don't allow deleting yourself
        if ($adminUser->id == $id) {
            return response()->json(['error' => 'Cannot delete yourself'], 400);
        }
        
        $user = User::find($id);
        
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        
        // Delete user's messages
        $user->messages()->delete();
        
        // Remove user from all chats
        $user->chats()->detach();
        
        // Delete chats created by the user (and their messages)
        foreach ($user->createdChats as $chat) {
            $chat->messages()->delete();
            $chat->users()->detach();
            $chat->delete();
        }
        
        // Delete the user
        $user->delete();
        
        // Clear caches after deleting user
        $this->clearAdminCaches();
        
        return response()->json(['message' => 'User deleted successfully']);
    }
    
    /**
     * Clear all admin-related caches
     *
     * @return void
     */
    protected function clearAdminCaches()
    {
        // Clear all admin cache keys 
        Cache::forget('admin_dashboard_stats');
        
        // For large applications, you might want to implement a more robust solution
        // or switch to Redis/Memcached for better cache management
        
        // Clear paginated cache keys
        for ($i = 1; $i <= 10; $i++) { // Clear first 10 pages
            Cache::forget("admin_users_page_{$i}_per_page_20");
            Cache::forget("admin_chats_page_{$i}_per_page_20");
            Cache::forget("admin_messages_page_{$i}_per_page_20");
        }
    }
} 