@extends('layouts.app')

@section('title', 'Редактировать профиль')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12 mb-4">
            <h1>Редактирование профиля</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="row mb-4">
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
                                <div class="mb-3">
                                    <label for="avatar" class="form-label">Загрузить аватар</label>
                                    <input type="file" name="avatar" id="avatar" class="form-control @error('avatar') is-invalid @enderror">
                                    @error('avatar')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Имя</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name ?? '') }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email ?? '') }}" required>
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <hr>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">Новый пароль (оставьте пустым, чтобы не менять)</label>
                                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password">
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password_confirmation" class="form-label">Подтверждение пароля</label>
                                    <input type="password" class="form-control" id="password_confirmation" name="password_confirmation">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Текущий пароль (необходим для сохранения изменений)</label>
                                    <input type="password" class="form-control @error('current_password') is-invalid @enderror" id="current_password" name="current_password">
                                    @error('current_password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('profile.show') }}" class="btn btn-secondary">Отмена</a>
                            <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 