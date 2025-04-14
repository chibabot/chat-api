<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_id', 
        'user_id',
        'content'
    ];

    /**
     * Get the chat that owns the message.
     */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    /**
     * Get the user that sent the message.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted()
    {
        // When a new message is created, update the chat's last message info
        static::created(function ($message) {
            $chat = $message->chat;
            
            // Update the chat with the new message info
            $chat->update([
                'last_message_time' => $message->created_at,
                'last_message_preview' => \Illuminate\Support\Str::limit($message->content, 100),
                'last_message_id' => $message->id,
                'message_count' => $chat->message_count + 1
            ]);
        });
        
        // When a message is deleted, update the chat's message count
        static::deleted(function ($message) {
            $chat = Chat::find($message->chat_id);
            if ($chat) {
                // Decrement message count
                $chat->decrement('message_count');
                
                // If this was the last message, update the last message info
                if ($chat->last_message_id === $message->id) {
                    $newLastMessage = Message::where('chat_id', $chat->id)
                        ->orderBy('created_at', 'desc')
                        ->first();
                    
                    if ($newLastMessage) {
                        $chat->update([
                            'last_message_time' => $newLastMessage->created_at,
                            'last_message_preview' => \Illuminate\Support\Str::limit($newLastMessage->content, 100),
                            'last_message_id' => $newLastMessage->id
                        ]);
                    } else {
                        // No messages left in the chat
                        $chat->update([
                            'last_message_time' => null,
                            'last_message_preview' => null,
                            'last_message_id' => null
                        ]);
                    }
                }
            }
        });
    }
}