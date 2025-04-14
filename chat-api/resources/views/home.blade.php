@extends('layouts.app')

@section('title', 'Главная страница')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">Добро пожаловать в Чат API</div>

            <div class="card-body">
                <div class="alert alert-info">
                    <p>Это API-система для чатов с поддержкой JWT аутентификации.</p>
                </div>

                <h4>Возможности системы:</h4>
                <ul>
                    <li>Аутентификация и регистрация пользователей</li>
                    <li>Создание и управление чатами</li>
                    <li>Отправка и получение сообщений</li>
                    <li>JWT-токены для работы с API</li>
                </ul>

                <h4>Для начала работы:</h4>
                <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
                    @guest
                        <a href="{{ route('login') }}" class="btn btn-primary me-md-2">Вход</a>
                        <a href="{{ route('register') }}" class="btn btn-success">Регистрация</a>
                    @else
                        <a href="{{ route('chats.index') }}" class="btn btn-primary">Перейти к чатам</a>
                    @endguest
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 