<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
use Carbon\Carbon;

class ChatsAndMessagesSeeder extends Seeder
{
    // Константы для настройки сидера
    const TOTAL_CHATS = 1000;         // Общее количество чатов
    const MESSAGES_PER_CHAT = 100;  // Количество сообщений в одном чате
    const BATCH_SIZE = 500;           // Размер пакета для массовой вставки сообщений
    const PREVIEW_LENGTH = 60;        // Максимальная длина превью (без многоточия)
    
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('ru_RU');
        
        // Получаем всех пользователей системы
        $users = User::all();
        
        // Если пользователей нет, создаем хотя бы одного тестового
        if ($users->isEmpty()) {
            User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);
            
            $users = User::all();
        }
        
        $this->command->info('Начинаем создание ' . self::TOTAL_CHATS . ' чатов...');
        $successfulChats = 0;
        $failedChats = 0;
        
        // Создаем заданное количество чатов
        for ($i = 1; $i <= self::TOTAL_CHATS; $i++) {
            $this->command->info("Создаем чат {$i}/" . self::TOTAL_CHATS . " [Успешно: {$successfulChats}, С ошибкой: {$failedChats}]");
            
            // Начинаем транзакцию для каждого чата
            DB::beginTransaction();
            
            try {
                // Проверяем, есть ли хотя бы один пользователь
                if ($users->isEmpty()) {
                    throw new \Exception("Нет доступных пользователей для создания чата");
                }
                
                // Выбираем случайного пользователя в качестве создателя
                $creator = $users->random();
                
                // Создаем чат с безопасным превью сообщения
                $preview = $faker->sentence(1, true); // Уменьшаем до 1 слова для короткого превью
                if (mb_strlen($preview) > self::PREVIEW_LENGTH) {
                    $preview = mb_substr($preview, 0, self::PREVIEW_LENGTH) . '...';
                }
                
                $chat = Chat::create([
                    'title' => $faker->realText(30),
                    'created_by_user_id' => $creator->id,
                    'last_message_time' => now(),
                    'last_message_preview' => $preview,
                    'message_count' => self::MESSAGES_PER_CHAT
                ]);
                
                // Добавляем пользователей в чат
                // Определяем минимально необходимое количество пользователей (минимум 1)
                $usersCount = $users->count();
                $minUsersNeeded = 1;
                $maxUsersToAdd = min(5, $usersCount);
                
                // Выбираем от 1 до максимально возможного количества пользователей
                $chatUsersCount = rand($minUsersNeeded, $maxUsersToAdd);
                $chatUsers = $users->random($chatUsersCount);
                
                foreach ($chatUsers as $user) {
                    $chat->users()->attach($user->id, [
                        'is_admin' => $user->id === $chat->created_by_user_id || $faker->boolean(20),
                        'last_read_message_id' => null
                    ]);
                }
                
                // Генерируем сообщения для этого чата
                $this->command->info("  Создаем " . self::MESSAGES_PER_CHAT . " сообщений для чата {$i}");
                
                // Создаем массив случайных дат за последние 30 дней
                $now = now();
                $startDate = $now->copy()->subDays(30);
                $interval = $now->timestamp - $startDate->timestamp;
                
                $createdMessages = 0;
                $lastMessageId = null;
                $lastMessageDate = null;
                $lastMessageContent = null;
                $lastMessageUserId = null;
                
                // Создаем сообщения пакетами для оптимизации
                for ($batch = 0; $batch < self::MESSAGES_PER_CHAT; $batch += self::BATCH_SIZE) {
                    $batchSize = min(self::BATCH_SIZE, self::MESSAGES_PER_CHAT - $batch);
                    $messageData = [];
                    
                    for ($j = 0; $j < $batchSize; $j++) {
                        try {
                            // Выбираем отправителя
                            if ($chatUsers->count() === 1) {
                                $sender = $chatUsers->first();
                            } else {
                                $sender = $chatUsers->random();
                            }
                            
                            // Генерируем случайную дату в хронологическом порядке
                            $timestamp = $startDate->timestamp + ($interval * ($batch + $j) / self::MESSAGES_PER_CHAT);
                            $date = Carbon::createFromTimestamp($timestamp);
                            $lastMessageDate = $date;
                            
                            // Генерируем короткий текст для оптимизации
                            $content = $faker->realText(rand(10, 100));
                            $lastMessageContent = $content;
                            $lastMessageUserId = $sender->id;
                            
                            $messageData[] = [
                                'chat_id' => $chat->id,
                                'user_id' => $sender->id,
                                'content' => $content,
                                'created_at' => $date,
                                'updated_at' => $date,
                            ];
                            
                        } catch (\Exception $e) {
                            $this->command->info("    [ОШИБКА] При подготовке сообщения в пакете: " . $e->getMessage());
                        }
                    }
                    
                    // Вставляем пакет сообщений
                    if (!empty($messageData)) {
                        try {
                            // Disable message created event temporarily for batch inserts
                            Message::unsetEventDispatcher();
                            
                            // Insert messages and get the last insert ID
                            DB::table('messages')->insert($messageData);
                            $lastInsertId = DB::getPdo()->lastInsertId();
                            $firstInsertId = $lastInsertId - count($messageData) + 1;
                            
                            if (!$lastMessageId) {
                                $firstMessageId = $firstInsertId;
                            }
                            
                            $lastMessageId = $lastInsertId;
                            $createdMessages += count($messageData);
                            
                            // Обновляем прогресс
                            if ($batch % (self::BATCH_SIZE * 5) === 0 && $batch > 0) {
                                $progress = round(($batch / self::MESSAGES_PER_CHAT) * 100);
                                $this->command->info("    Прогресс: {$progress}% ({$createdMessages} сообщений создано)");
                            }
                        } catch (\Exception $e) {
                            $this->command->info("    [ОШИБКА] При вставке пакета сообщений: " . $e->getMessage());
                        }
                    }
                }
                
                // Обновляем информацию о последнем сообщении в чате
                if ($createdMessages > 0 && $lastMessageDate) {
                    // Устанавливаем правильные данные
                    $content = $lastMessageContent;
                    if (mb_strlen($content) > self::PREVIEW_LENGTH) {
                        $content = mb_substr($content, 0, self::PREVIEW_LENGTH) . '...';
                    }
                    
                    $chat->last_message_time = $lastMessageDate;
                    $chat->last_message_preview = $content;
                    $chat->last_message_id = $lastMessageId;
                    $chat->message_count = $createdMessages;
                    $chat->save();
                    
                    // Restore event dispatcher
                    Message::setEventDispatcher(app('events'));
                }
                
                // Если не удалось создать ни одного сообщения, выбрасываем исключение
                if ($createdMessages === 0) {
                    throw new \Exception("Не удалось создать ни одного сообщения для чата {$i}");
                }
                
                DB::commit();
                $successfulChats++;
                $this->command->info("  Чат {$i} успешно создан с {$createdMessages} сообщениями.");
                
            } catch (\Exception $e) {
                DB::rollBack();
                $failedChats++;
                $this->command->error("Ошибка при создании чата {$i}: " . $e->getMessage());
                
                // Выводим сокращенный стек ошибки
                $trace = explode("\n", $e->getTraceAsString());
                $shortTrace = array_slice($trace, 0, 3);
                $this->command->info("  [СТЕК ОШИБКИ]: " . implode("\n  ", $shortTrace));
            }
        }
        
        $this->command->info("Создание тестовых данных завершено!");
        $this->command->info("Итог: Успешно создано {$successfulChats} чатов с примерно " . 
                           ($successfulChats * self::MESSAGES_PER_CHAT) . " сообщениями");
        $this->command->info("Чатов с ошибками: {$failedChats}");
    }
} 