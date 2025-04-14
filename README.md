# Laravel 12 REST API с JWT Authentication

REST API приложение на Laravel 12 с использованием JWT для аутентификации, MySQL для хранения данных с кэшированием запросов.

## Функциональность

- Регистрация и аутентификация пользователей через JWT
- Полноценный REST API с ресурсами
- Кэширование запросов через Cache tags
- Работа с базой данных MySQL

## Требования

- PHP 8.2+
- Composer
- Docker и Docker Compose
- MySQL 9.2

## Установка и запуск

### Через Docker

```bash
# Клонирование репозитория
git clone https://github.com/chibabot/chat-api.git

# Копирование .env файла
cp .env.example .env

# Запуск через Docker
docker-compose up -d

# Применение миграций
docker-compose exec app php artisan migrate

# Генерация ключа приложения
docker-compose exec app php artisan key:generate

# Генерация JWT секретного ключа
docker-compose exec app php artisan jwt:secret
```

### Через Laravel Sail

```bash
# Настройка Laravel Sail
php artisan sail:install

# Запуск через Laravel Sail
./vendor/bin/sail up -d

# Применение миграций
./vendor/bin/sail artisan migrate

# Генерация ключей
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan jwt:secret
```

## API Документация

### Аутентификация

```
POST /api/auth/register                     # Регистрация нового пользователя
POST /api/auth/login                        # Аутентификация и получение JWT токена
POST /api/auth/logout                       # Выход (инвалидация токена)
POST /api/auth/refresh                      # Обновление JWT токена
```

### Профиль

```
GET /api/profile                            # Получение информации о профиле
PUT /api/profile                            # Обновление информации в профиле
PUT /api/profile/password                   # Обновление пароля пользователя
POST /api/profile/avatar                    # Добавление нового аватара в профиль
DELETE /api/profile/avatar                  # Удаление аватара в профиле
```

### Чаты

```
GET /api/chats                              # Получение списка чатов пользователя
POST /api/chats                             # Создание нового чата
GET /api/chats/{id}                         # Получение подробной информации о чате
PUT /api/chats/{id}                         # Обновление чата
DELETE /api/chats/{id}                      # Удаление чата
POST /api/chats/users                       # Добавление новых пользователей в чат
DELETE /api/chats/{id}/users/{userId}       # Исключение пользователя из чата
```

### Сообщения

```
GET /api/chats/{chat_id}/messages           # Получение сообщений из чата
POST /api/chats/{chat_id}/messages          # Создание нового сообщения в чате
PUT /api/chats/{chat_id}/messages/{id}      # Обновление сообщения в чате
DELETE /api/chats/{chat_id}/messages/{id}   # Удаление сообщения из чата
```

### Панель суперпользователя

```
GET /api/admin/chats                        # Получение всех чатов
GET /api/admin/messages                     # Получение всех сообщений
GET /api/admin/users                        # Получение всех пользователей
DELETE /api/admin/users/{id}                # Удаление пользователя
```

### Статус приложения

```
GET /api/status                             # Статус приложения и соединения с базой
```

## Тестирование

```bash
# Запуск тестов
docker-compose exec app php artisan test

# Или через Sail
./vendor/bin/sail artisan test
```
