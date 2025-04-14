<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

class PopulateOptimizationFields extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:populate-optimization-fields {--chunk=100 : Chunk size for processing chats}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate the new optimization fields in chats and chat_users tables';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $chunkSize = $this->option('chunk');
        $this->info("Starting to populate optimization fields with chunk size: {$chunkSize}");
        
        $totalChats = Chat::count();
        $bar = $this->output->createProgressBar($totalChats);
        $bar->start();
        
        Chat::chunk($chunkSize, function($chats) use ($bar) {
            foreach ($chats as $chat) {
                DB::beginTransaction();
                try {
                    // Get the last message for this chat
                    $lastMessage = Message::where('chat_id', $chat->id)
                        ->orderBy('created_at', 'desc')
                        ->first();
                    
                    // Get message count
                    $messageCount = Message::where('chat_id', $chat->id)->count();
                    
                    if ($lastMessage) {
                        $chat->last_message_id = $lastMessage->id;
                        $chat->last_message_time = $lastMessage->created_at;
                        $chat->last_message_preview = \Illuminate\Support\Str::limit($lastMessage->content, 100);
                    }
                    
                    $chat->message_count = $messageCount;
                    $chat->save();
                    
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("Error processing chat ID {$chat->id}: " . $e->getMessage());
                }
                
                $bar->advance();
            }
        });
        
        $bar->finish();
        $this->newLine();
        $this->info('Optimization fields have been populated successfully.');
    }
} 