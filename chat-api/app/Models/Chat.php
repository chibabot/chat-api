<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'last_message_time',
        'last_message_preview',
        'created_by',
        'last_message_id',
        'unread_count',
        'message_count'
    ];

    protected $casts = [
        'last_message_time' => 'datetime',
    ];

    /**
     * Get the messages for the chat.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the last message of the chat.
     */
    public function lastMessage()
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    /**
     * Get the users that belong to the chat.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_users')
                    ->withPivot(['is_admin', 'last_read_message_id'])
                    ->withTimestamps();
    }

    /**
     * Get the user who created the chat.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Update last message info for the chat.
     */
    public function updateLastMessageInfo(): void
    {
        $lastMessage = $this->messages()->latest()->first();
        
        if ($lastMessage) {
            $this->update([
                'last_message_time' => $lastMessage->created_at,
                'last_message_preview' => Str::limit($lastMessage->content, 65),
                'last_message_id' => $lastMessage->id,
                'message_count' => $this->message_count + 1
            ]);
        }
    }

    /**
     * Get recent messages with efficient pagination
     */
    public function getRecentMessages($limit = 50, $before_id = null)
    {
        $query = $this->messages()
            ->with(['user' => function($query) {
                $query->select('id', 'name', 'email')
                      ->makeHidden(['phone', 'bio', 'avatar', 'last_activity_at']);
            }])
            ->orderBy('id', 'desc');
            
        if ($before_id) {
            $query->where('id', '<', $before_id);
        }
        
        $messages = $query->limit($limit)->get();
        
        // Ensure personal fields are hidden from each message's user
        $messages->each(function($message) {
            if ($message->user) {
                $message->user->makeHidden(['phone', 'bio', 'avatar', 'last_activity_at']);
            }
        });
        
        return $messages;
    }

    /**
     * Get unread message count for a specific user
     */
    public function getUnreadCount($userId)
    {
        $pivotData = $this->users()->where('users.id', $userId)->first()?->pivot;
        
        if (!$pivotData || !$pivotData->last_read_message_id) {
            return $this->message_count;
        }
        
        return $this->messages()
            ->where('id', '>', $pivotData->last_read_message_id)
            ->count();
    }

    /**
     * Mark messages as read for a user
     */
    public function markAsRead($userId, $messageId = null)
    {
        if (!$messageId) {
            $messageId = $this->last_message_id;
        }
        
        $this->users()->updateExistingPivot($userId, [
            'last_read_message_id' => $messageId
        ]);
        
        return $this;
    }
}