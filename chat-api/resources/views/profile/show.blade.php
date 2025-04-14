@extends('layouts.app')

@section('title', 'Профиль')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12 mb-4">
            <h1>Профиль пользователя</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div class="mb-3">
                                @if(isset($user) && $user->avatar)
                                    <img src="{{ asset('storage/' . $user->avatar) }}" alt="Аватар" class="img-fluid rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                                @else
                                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white" style="width: 150px; height: 150px; margin: 0 auto;">
                                        <span style="font-size: 48px;">
                                            {{ isset($user) ? substr($user->name, 0, 1) : 'U' }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        
                        <div class="col-md-8">
                            <h3>{{ isset($user) ? $user->name : 'Имя пользователя' }}</h3>
                            <p class="text-muted">{{ isset($user) ? $user->email : 'email@example.com' }}</p>
                            
                            <div class="mt-4">
                                <h5>Информация</h5>
                                <div class="mb-2">
                                    <strong>Дата регистрации:</strong> 
                                    {{ isset($user) && $user->created_at ? $user->created_at->format('d.m.Y') : 'Не указана' }}
                                </div>
                                <div class="mb-2">
                                    <strong>Активных чатов:</strong> 
                                    {{ isset($chatCount) ? $chatCount : '0' }}
                                </div>
                                <div class="mb-2">
                                    <strong>Последний вход:</strong> 
                                    {{ isset($user) && $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Не указан' }}
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <a href="{{ route('profile.edit') }}" class="btn btn-primary">Редактировать профиль</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 